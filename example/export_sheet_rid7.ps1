$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$xlsx = Get-ChildItem -Path $baseDir -Filter '*.xlsx' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$zip = [System.IO.Compression.ZipFile]::OpenRead($xlsx.FullName)

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

try {
    $outDir = Join-Path $baseDir 'excel_export'
    if (!(Test-Path $outDir)) { New-Item -Path $outDir -ItemType Directory | Out-Null }

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
        if ([string]$rel.Id -eq 'rId7') { $sheetPath = 'xl/' + ([string]$rel.Target).TrimStart('/'); break }
    }
    if (-not $sheetPath) { throw 'rId7 not found' }

    [xml]$sx = Get-EntryText $zip $sheetPath
    $sxNs = New-Object System.Xml.XmlNamespaceManager($sx.NameTable)
    $sxNs.AddNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    $rowNodes = $sx.SelectNodes('//d:sheetData/d:row', $sxNs)

    $rows = @()
    $maxCol = 0
    foreach ($row in $rowNodes) {
        $map = @{}
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
            $map[$col] = $val
            if ($col -gt $maxCol) { $maxCol = $col }
        }
        $rows += ,$map
    }

    $csvPath = Join-Path $outDir 'Инструменты.csv'
    $writer = New-Object System.IO.StreamWriter($csvPath, $false, [System.Text.Encoding]::UTF8)
    try {
        foreach ($r in $rows) {
            $vals = @()
            for ($i=1; $i -le $maxCol; $i++) {
                $v = if ($r.ContainsKey($i)) { [string]$r[$i] } else { '' }
                $v = $v.Replace('"','""')
                if ($v.Contains(',') -or $v.Contains('"') -or $v.Contains("`n") -or $v.Contains("`r")) { $vals += '"' + $v + '"' } else { $vals += $v }
            }
            $writer.WriteLine(($vals -join ','))
        }
    } finally {
        $writer.Dispose()
    }

    Write-Output ("EXPORTED|rId7|rows=" + $rows.Count + "|cols=" + $maxCol)
}
finally {
    $zip.Dispose()
}

