$ErrorActionPreference = 'Stop'

$rate = 87.5
$csvPath = (Get-ChildItem -Path 'example/excel_export' -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1).FullName
$outSql = 'example/final_tools_update_from_sheet.sql'

function Parse-Num($v) {
    if ($null -eq $v) { return $null }
    $s = [string]$v
    $s = $s.Trim()
    if ($s -eq '') { return $null }
    $s = $s -replace ',', '.'
    if ($s -notmatch '^-?\d+(\.\d+)?$') { return $null }
    return [double]::Parse($s, [System.Globalization.CultureInfo]::InvariantCulture)
}

function SqlEsc([string]$s) {
    if ($null -eq $s) { return '' }
    return $s.Replace("'", "''")
}

$rows = Import-Csv -Path $csvPath
$first = $rows | Select-Object -First 1
Write-Output ("CSV=" + $csvPath)
if ($first) {
    Write-Output ("FIRST_COLS=" + (($first.PSObject.Properties | Select-Object -ExpandProperty Name) -join '|'))
    $fp = $first.PSObject.Properties
    $v2 = if ($fp.Count -gt 1) { [string]$fp[1].Value } else { '' }
    $v4 = if ($fp.Count -gt 3) { [string]$fp[3].Value } else { '' }
    Write-Output ("FIRST_ITEM=" + [string]$first.'Item No.' + "|P2=" + $v2 + "|P4=" + $v4)
}
$dict = @{}
$emptyStreak = 0

foreach ($r in $rows) {
    $props = $r.PSObject.Properties
    if ($props.Count -lt 2) { continue }
    $no = [string]$r.'No.'
    $item = [string]$r.'Item No.'
    $optRaw = if ($props.Count -gt 3) { [string]$props[3].Value } else { '' }
    $retRaw = if ($props.Count -gt 4) { [string]$props[4].Value } else { '' }
    $qtyRaw = if ($props.Count -gt 5) { [string]$props[5].Value } else { '' }
    $note = if ($props.Count -gt 6) { [string]$props[6].Value } else { '' }

    if ([string]::IsNullOrWhiteSpace($item)) {
        $emptyStreak++
        if ($emptyStreak -gt 10000 -and $dict.Count -gt 0) { break }
        continue
    }
    $emptyStreak = 0
    if ($item -eq 'Item No.') { continue }

    $optUsd = Parse-Num $optRaw
    $retKgs = Parse-Num $retRaw
    $qty = Parse-Num $qtyRaw

    if (-not $dict.ContainsKey($item)) {
        $dict[$item] = [pscustomobject]@{
            item = $item
            no = $no
            optUsd = $optUsd
            retKgs = $retKgs
            qty = $qty
            note = $note
        }
    } else {
        $cur = $dict[$item]
        if ($cur.optUsd -eq $null -and $optUsd -ne $null) { $cur.optUsd = $optUsd }
        if ($cur.retKgs -eq $null -and $retKgs -ne $null) { $cur.retKgs = $retKgs }
        if ($cur.qty -eq $null -and $qty -ne $null) { $cur.qty = $qty }
        if ([string]::IsNullOrWhiteSpace($cur.note) -and -not [string]::IsNullOrWhiteSpace($note)) { $cur.note = $note }
    }
}

$lines = @()
$lines += "-- Generated from sheet: Цены - Инструменты.csv"
$lines += "-- purchase_price = opt_usd * 87.5"
$lines += "-- price_per_meter/price_1_4 = retail_kgs if set, else purchase_price"
$lines += "START TRANSACTION;"

foreach ($k in ($dict.Keys | Sort-Object)) {
    $x = $dict[$k]
    $purchase = if ($x.optUsd -ne $null) { [Math]::Round($x.optUsd * $rate, 2) } else { $null }
    $sell = if ($x.retKgs -ne $null) { [Math]::Round($x.retKgs, 2) } elseif ($purchase -ne $null) { $purchase } else { $null }
    $name = SqlEsc $x.item
    $desc = SqlEsc $x.note

    $set = @("roll_length=1")
    if ($purchase -ne $null) { $set += "purchase_price=$purchase"; $set += "delivery_price=$purchase" }
    if ($sell -ne $null) {
        $set += "price_per_meter=$sell"
        $set += "price_1_4=$sell"
        $set += "price_5_9=$sell"
        $set += "price_20_plus=$sell"
    }
    if (-not [string]::IsNullOrWhiteSpace($desc)) { $set += "description='$desc'" }

    $setSql = $set -join ', '
    $lines += "UPDATE products SET $setSql WHERE name='$name' LIMIT 1;"

    $cols = @("name","roll_length")
    $vals = @("'$name'","1")
    if ($purchase -ne $null) { $cols += "purchase_price"; $vals += "$purchase"; $cols += "delivery_price"; $vals += "$purchase" }
    if ($sell -ne $null) {
        $cols += "price_per_meter"; $vals += "$sell"
        $cols += "price_1_4"; $vals += "$sell"
        $cols += "price_5_9"; $vals += "$sell"
        $cols += "price_20_plus"; $vals += "$sell"
    }
    if (-not [string]::IsNullOrWhiteSpace($desc)) { $cols += "description"; $vals += "'$desc'" }

    $lines += "INSERT INTO products (" + ($cols -join ', ') + ") SELECT " + ($vals -join ', ') + " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='$name');"
}

$lines += "COMMIT;"
Set-Content -Path $outSql -Encoding UTF8 -Value ($lines -join [Environment]::NewLine)

Write-Output ("ITEMS=" + $dict.Count)
Write-Output ("OUT=" + $outSql)

