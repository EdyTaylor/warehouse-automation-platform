$ErrorActionPreference = 'Stop'

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$productsSqlPath = Join-Path $baseDir 'products.sql'
$exportDir = Join-Path $baseDir 'excel_export'
$outSql = Join-Path $baseDir 'final_db_update_from_new_excel.sql'
$outUnmatched = Join-Path $baseDir 'new_excel_unmatched.csv'
$rate = 87.5

function Normalize-Name([string]$s) {
    if ([string]::IsNullOrWhiteSpace($s)) { return '' }
    $x = $s.ToLower().Trim()
    $x = $x -replace 'system\.xml\.xmlelement', ''
    $x = $x -replace '❌', ''
    $x = $x -replace '[^a-z0-9]+', ' '
    $x = $x -replace '\s+', ' '
    return $x.Trim()
}

function Parse-Num($v) {
    if ($null -eq $v) { return $null }
    $s = [string]$v
    $s = $s.Trim()
    if ($s -eq '') { return $null }
    $s = $s -replace ',', '.'
    if ($s -notmatch '^-?\d+(\.\d+)?$') { return $null }
    return [double]::Parse($s, [System.Globalization.CultureInfo]::InvariantCulture)
}

function To-Kgs([double]$usd) {
    return [Math]::Round($usd * $rate, 2)
}

$sqlText = Get-Content -Path $productsSqlPath -Raw -Encoding UTF8
$nameMatches = [regex]::Matches($sqlText, "\(\s*\d+\s*,\s*'((?:\\'|[^'])*)'")

$products = @()
foreach ($m in $nameMatches) {
    $name = $m.Groups[1].Value.Replace("\'", "'")
    $norm = Normalize-Name $name
    if ($norm -eq '') { continue }
    $products += [pscustomobject]@{
        name = $name
        norm = $norm
    }
}

$headers = 1..45 | ForEach-Object { "c$_" }
$sheetFiles = Get-ChildItem -Path $exportDir -Filter '*.csv' | Select-Object -ExpandProperty FullName

$updates = @{}
$unmatched = @()

foreach ($path in $sheetFiles) {
    $sheetFile = Split-Path -Leaf $path
    if (!(Test-Path $path)) { continue }
    $rows = Get-Content -Path $path -Encoding UTF8 | ConvertFrom-Csv -Header $headers
    foreach ($r in $rows) {
        $rawName = [string]$r.c3
        if ([string]::IsNullOrWhiteSpace($rawName)) { continue }
        if ($rawName -match '^№$|^System\.Xml\.XmlElement$') { continue }
        if ($rawName -match '^LLumar$|^Luxfil|^Hexis$|^КПМФ$|^ORACAL$|^Mono$|^VERSA$|^Membrane$|^OLFA$|^Pro Bond$|^Праймер$') { continue }
        if ($rawName -match '^\d+(\.\d+)?$') { continue }

        $norm = Normalize-Name $rawName
        if ($norm -eq '') { continue }

        $target = $products | Where-Object { $_.norm -eq $norm } | Select-Object -First 1
        if (-not $target) { continue }
        if (-not $target) {
            $unmatched += [pscustomobject]@{ sheet=$sheetFile; excel_name=$rawName }
            continue
        }

        $rollLength = Parse-Num $r.c6
        $purchaseUsd = Parse-Num $r.c9
        $deliveryUsd = Parse-Num $r.c10
        $otrezUsd = Parse-Num $r.c12
        $p14Usd = Parse-Num $r.c13
        $p59Usd = Parse-Num $r.c14
        $p20Usd = Parse-Num $r.c15

        # "Другое" (tools/primer) has different columns
        if ($sheetFile -match 'Другое|Other|Rugoe|Dru') {
            $purchaseUsd = Parse-Num $r.c7
            $deliveryUsd = Parse-Num $r.c8
            $p14Usd = Parse-Num $r.c9
            $p59Usd = Parse-Num $r.c10
            $p20Usd = Parse-Num $r.c11
            $otrezUsd = $p14Usd
            $rollLength = 1
        }

        $set = @()
        if ($rollLength -and $rollLength -gt 0) { $set += "roll_length=$([Math]::Round($rollLength,2).ToString([System.Globalization.CultureInfo]::InvariantCulture))" }
        if ($purchaseUsd -ne $null) { $set += "purchase_price=$(To-Kgs $purchaseUsd)" }
        if ($deliveryUsd -ne $null) { $set += "delivery_price=$(To-Kgs $deliveryUsd)" }
        if ($otrezUsd -ne $null) { $set += "price_per_meter=$(To-Kgs $otrezUsd)" }
        if ($p14Usd -ne $null) { $set += "price_1_4=$(To-Kgs $p14Usd)" }
        if ($p59Usd -ne $null) { $set += "price_5_9=$(To-Kgs $p59Usd)" }
        if ($p20Usd -ne $null) { $set += "price_20_plus=$(To-Kgs $p20Usd)" }

        if ($set.Count -eq 0) { continue }
        $updates[$target.name] = $set
    }
}

$sqlLines = @()
$sqlLines += "-- Auto-generated from updated Excel + products.sql"
$sqlLines += "-- USD -> KGS rate: 87.5"
$sqlLines += "START TRANSACTION;"

foreach ($key in ($updates.Keys | Sort-Object)) {
    $setSql = ($updates[$key] -join ', ')
    $safeName = $key.Replace("'", "''")
    $sqlLines += "UPDATE products SET $setSql WHERE name='$safeName' LIMIT 1;"
}

$sqlLines += "COMMIT;"
Set-Content -Path $outSql -Encoding UTF8 -Value ($sqlLines -join [Environment]::NewLine)

if ($unmatched.Count -gt 0) {
    $unmatched | Sort-Object sheet, excel_name | Export-Csv -Path $outUnmatched -NoTypeInformation -Encoding UTF8
} else {
    Set-Content -Path $outUnmatched -Encoding UTF8 -Value "sheet,excel_name`n"
}

Write-Output ("UPDATES|" + $updates.Count)
Write-Output ("UNMATCHED|" + $unmatched.Count)
Write-Output ("SQL|" + $outSql)
Write-Output ("UNMATCHED_FILE|" + $outUnmatched)

