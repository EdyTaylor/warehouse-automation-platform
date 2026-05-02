$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName Microsoft.VisualBasic

$rate = 87.5
$csv = (Get-ChildItem -Path 'example/excel_export' -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1).FullName
$outSql = 'example/final_tools_update_from_instruments_xlsx.sql'

function Parse-Num([string]$s) {
    if ([string]::IsNullOrWhiteSpace($s)) { return $null }
    $x = $s.Trim().Replace(',', '.')
    if ($x -notmatch '^-?\d+(\.\d+)?$') { return $null }
    return [double]::Parse($x, [System.Globalization.CultureInfo]::InvariantCulture)
}
function SqlEsc([string]$s) { if ($null -eq $s) { return '' } ; return $s.Replace("'", "''") }

$map = @{}
$emptyStreak = 0

$p = New-Object Microsoft.VisualBasic.FileIO.TextFieldParser($csv, [System.Text.Encoding]::UTF8)
$p.SetDelimiters(',')
$p.HasFieldsEnclosedInQuotes = $true

try {
    if (-not $p.EndOfData) { [void]$p.ReadFields() } # skip header
    while (-not $p.EndOfData) {
        $f = $p.ReadFields()
        if ($null -eq $f -or $f.Length -lt 5) { continue }
        $item = $f[1]
        if ([string]::IsNullOrWhiteSpace($item)) {
            $emptyStreak++
            if ($emptyStreak -gt 10000 -and $map.Count -gt 0) { break }
            continue
        }
        $emptyStreak = 0
        $opt = Parse-Num $f[3]
        $ret = Parse-Num $f[4]

        if (-not $map.ContainsKey($item)) {
            $map[$item] = [pscustomobject]@{item=$item;opt=$opt;ret=$ret}
        } else {
            $x = $map[$item]
            if ($x.opt -eq $null -and $opt -ne $null) { $x.opt = $opt }
            if ($x.ret -eq $null -and $ret -ne $null) { $x.ret = $ret }
        }
    }
}
finally {
    $p.Close()
}

$lines = @()
$lines += "-- Generated from tools sheet csv"
$lines += "-- purchase_price = opt(USD) * 87.5"
$lines += "-- price_per_meter = retail(KGS) when filled"
$lines += "START TRANSACTION;"
foreach ($k in ($map.Keys | Sort-Object)) {
    $x = $map[$k]
    $purchase = if ($x.opt -ne $null) { [Math]::Round($x.opt * $rate, 2) } else { $null }
    $sell = if ($x.ret -ne $null) { [Math]::Round($x.ret, 2) } else { $null }
    if ($purchase -eq $null -and $sell -eq $null) { continue }
    $name = SqlEsc $x.item
    $set = @("roll_length=1")
    if ($purchase -ne $null) { $set += "purchase_price=$purchase"; $set += "delivery_price=$purchase" }
    if ($sell -ne $null) { $set += "price_per_meter=$sell" }
    $lines += "UPDATE products SET " + ($set -join ', ') + " WHERE name='$name' LIMIT 1;"
    $cols = @("name","roll_length")
    $vals = @("'$name'","1")
    if ($purchase -ne $null) { $cols += "purchase_price"; $vals += "$purchase"; $cols += "delivery_price"; $vals += "$purchase" }
    if ($sell -ne $null) { $cols += "price_per_meter"; $vals += "$sell" }
    $lines += "INSERT INTO products (" + ($cols -join ', ') + ") SELECT " + ($vals -join ', ') + " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='$name');"
}
$lines += "COMMIT;"
Set-Content -Path $outSql -Encoding UTF8 -Value ($lines -join [Environment]::NewLine)

Write-Output ("CSV=" + $csv)
Write-Output ("ITEMS=" + $map.Count)
Write-Output ("OUT=" + $outSql)

