$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$xlsx = Get-ChildItem -Path $baseDir -Filter '*.xlsx' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $xlsx) { throw "No xlsx found" }

$outDir = Join-Path $baseDir 'excel_export'
if (!(Test-Path $outDir)) { New-Item -Path $outDir -ItemType Directory | Out-Null }

$zip = [System.IO.Compression.ZipFile]::OpenRead($xlsx.FullName)

function Get-EntryText([System.IO.Compression.ZipArchive]$z, [string]$name) {
    $entry = $z.GetEntry($name)
    if (-not $entry) { return $null }
    $sr = New-Object System.IO.StreamReader($entry.Open())
    try { return $sr.ReadToEnd() } finally { $sr.Dispose() }
}

function ColToIndex([string]$letters) {
    $sum = 0
    foreach ($ch in $letters.ToCharArray()) {
        $sum = $sum * 26 + ([int][char]$ch - [int][char]'A' + 1)
    }
    return $sum
}

try {
    $shared = @()
    $sharedXmlText = Get-EntryText $zip 'xl/sharedStrings.xml'
    if ($sharedXmlText) {
        [xml]$sharedXml = $sharedXmlText
        foreach ($si in $sharedXml.sst.si) {
            if ($si.t) {
                $shared += [string]$si.t
            } elseif ($si.r) {
                $parts = @()
                foreach ($r in $si.r) { $parts += [string]$r.t }
                $shared += ($parts -join '')
            } else {
                $shared += ''
            }
        }
    }

    [xml]$wb = Get-EntryText $zip 'xl/workbook.xml'
    [xml]$rels = Get-EntryText $zip 'xl/_rels/workbook.xml.rels'

    $relMap = @{}
    foreach ($rel in $rels.Relationships.Relationship) {
        $relMap[[string]$rel.Id] = 'xl/' + ([string]$rel.Target).TrimStart('/')
    }

    $wbNs = New-Object System.Xml.XmlNamespaceManager($wb.NameTable)
    $wbNs.AddNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    $wbNs.AddNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    $sheetNodes = $wb.SelectNodes('//d:sheets/d:sheet', $wbNs)

    foreach ($sheet in $sheetNodes) {
        $sheetName = [string]$sheet.GetAttribute('name')
        $rid = [string]$sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
        if (-not $relMap.ContainsKey($rid)) { continue }
        $sheetPath = $relMap[$rid]
        $sheetText = Get-EntryText $zip $sheetPath
        if (-not $sheetText) { continue }
        [xml]$sx = $sheetText

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

        $safe = ($sheetName -replace '[\\/:*?"<>|]', '_')
        $csvPath = Join-Path $outDir ($safe + '.csv')
        $writer = New-Object System.IO.StreamWriter($csvPath, $false, [System.Text.Encoding]::UTF8)
        try {
            foreach ($r in $rows) {
                $vals = @()
                for ($i = 1; $i -le $maxCol; $i++) {
                    $v = if ($r.ContainsKey($i)) { [string]$r[$i] } else { '' }
                    $v = $v.Replace('"','""')
                    if ($v.Contains(',') -or $v.Contains('"') -or $v.Contains("`n") -or $v.Contains("`r")) {
                        $vals += '"' + $v + '"'
                    } else {
                        $vals += $v
                    }
                }
                $writer.WriteLine(($vals -join ','))
            }
        } finally {
            $writer.Dispose()
        }
        Write-Output ("EXPORTED|" + $sheetName + "|" + $rows.Count)
    }
}
finally {
    $zip.Dispose()
}

