$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$rate = 87.5
$xlsx = (Get-ChildItem -Path 'example' -Filter '*.xlsx' | Where-Object { $_.Name -notlike '~$*' } | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
$outSql = 'example/final_tools_update_from_instruments_xlsx.sql'

function Get-EntryText([System.IO.Compression.ZipArchive]$z, [string]$name) {
    $entry = $z.GetEntry($name)
    if (-not $entry) { return $null }
    $sr = New-Object System.IO.StreamReader($entry.Open())
    try { return $sr.ReadToEnd() } finally { $sr.Dispose() }
}

function ColToIndex([string]$letters) {
    $sum = 0
    foreach ($ch in $letters.ToCharArray()) { $sum = $sum * 26 + ([int][char]$ch - [int][char]'A' + 1) }
    return $sum
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

function SqlEsc([string]$s) {
    if ($null -eq $s) { return '' }
    return $s.Replace("'", "''")
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($xlsx)
try {
    $shared = @()
    [xml]$sharedXml = Get-EntryText $zip 'xl/sharedStrings.xml'
    if ($sharedXml -and $sharedXml.sst.si) {
        foreach ($si in $sharedXml.sst.si) {
            if ($si.t) { $shared += [string]$si.t; continue }
            $parts = @(); foreach ($r in $si.r) { $parts += [string]$r.t }; $shared += ($parts -join '')
        }
    }

    [xml]$rels = Get-EntryText $zip 'xl/_rels/workbook.xml.rels'
    $sheetPath = $null
    foreach ($rel in $rels.Relationships.Relationship) {
        $target = [string]$rel.Target
        if ($target -match 'worksheets/sheet1.xml') { $sheetPath = 'xl/' + $target.TrimStart('/'); break }
    }
    if (-not $sheetPath) { throw 'sheet1.xml not found' }

    [xml]$sx = Get-EntryText $zip $sheetPath
    $sxNs = New-Object System.Xml.XmlNamespaceManager($sx.NameTable)
    $sxNs.AddNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    $rowNodes = $sx.SelectNodes('//d:sheetData/d:row', $sxNs)

    $data = @{}
    foreach ($row in $rowNodes) {
        $vals = @{}
        foreach ($c in $row.SelectNodes('d:c', $sxNs)) {
            $ref = [string]$c.GetAttribute('r')
            if ($ref -notmatch '^([A-Z]+)(\d+)$') { continue }
            $col = ColToIndex $matches[1]
            $type = [string]$c.GetAttribute('t')
            $vNode = $c.SelectSingleNode('d:v', $sxNs)
            $raw = if ($vNode) { [string]$vNode.InnerText } else { '' }
            if ($type -eq 's' -and $raw -match '^\d+$') {
                $idx = [int]$raw
                $val = if ($idx -lt $shared.Count) { $shared[$idx] } else { '' }
            } else {
                $val = $raw
            }
            $vals[$col] = $val
        }
        $item = if ($vals.ContainsKey(2)) { [string]$vals[2] } else { '' } # Item No.
        $opt = if ($vals.ContainsKey(4)) { [string]$vals[4] } else { '' }  # опт
        $ret = if ($vals.ContainsKey(5)) { [string]$vals[5] } else { '' }  # рознь
        if ([string]::IsNullOrWhiteSpace($item) -or $item -eq 'Item No.') { continue }
        if (-not $data.ContainsKey($item)) {
            $data[$item] = [pscustomobject]@{ item=$item; opt=Parse-Num $opt; ret=Parse-Num $ret }
        } else {
            $cur = $data[$item]
            if ($cur.opt -eq $null -and (Parse-Num $opt) -ne $null) { $cur.opt = Parse-Num $opt }
            if ($cur.ret -eq $null -and (Parse-Num $ret) -ne $null) { $cur.ret = Parse-Num $ret }
        }
    }

    $lines = @()
    $lines += "-- Generated from Инструменты.xlsx"
    $lines += "-- purchase_price = опт(USD) * 87.5"
    $lines += "-- price_per_meter = рознь(KGS) if filled, else purchase"
    $lines += "START TRANSACTION;"

    foreach ($k in ($data.Keys | Sort-Object)) {
        $x = $data[$k]
        $purchase = if ($x.opt -ne $null) { [Math]::Round($x.opt * $rate, 2) } else { $null }
        $sell = if ($x.ret -ne $null) { [Math]::Round($x.ret, 2) } elseif ($purchase -ne $null) { $purchase } else { $null }
        if ($purchase -eq $null -and $sell -eq $null) { continue }
        $name = SqlEsc $x.item
        $set = @("roll_length=1")
        if ($purchase -ne $null) { $set += "purchase_price=$purchase"; $set += "delivery_price=$purchase" }
        if ($sell -ne $null) { $set += "price_per_meter=$sell" }
        $setSql = $set -join ', '
        $lines += "UPDATE products SET $setSql WHERE name='$name' LIMIT 1;"
        $cols = @("name","roll_length")
        $vals = @("'$name'","1")
        if ($purchase -ne $null) { $cols += "purchase_price"; $vals += "$purchase"; $cols += "delivery_price"; $vals += "$purchase" }
        if ($sell -ne $null) { $cols += "price_per_meter"; $vals += "$sell" }
        $lines += "INSERT INTO products (" + ($cols -join ', ') + ") SELECT " + ($vals -join ', ') + " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE name='$name');"
    }

    $lines += "COMMIT;"
    Set-Content -Path $outSql -Encoding UTF8 -Value ($lines -join [Environment]::NewLine)
    Write-Output ("ITEMS=" + $data.Count)
    Write-Output ("OUT=" + $outSql)
}
finally {
    $zip.Dispose()
}

