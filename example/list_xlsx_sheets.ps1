$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$xlsx = Get-ChildItem -Path $baseDir -Filter '*.xlsx' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$zip = [System.IO.Compression.ZipFile]::OpenRead($xlsx.FullName)
function Get-EntryText([System.IO.Compression.ZipArchive]$z, [string]$name) {
    $entry = $z.GetEntry($name)
    $sr = New-Object System.IO.StreamReader($entry.Open())
    try { return $sr.ReadToEnd() } finally { $sr.Dispose() }
}
try {
    [xml]$wb = Get-EntryText $zip 'xl/workbook.xml'
    $wbNs = New-Object System.Xml.XmlNamespaceManager($wb.NameTable)
    $wbNs.AddNamespace('d', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    $wbNs.AddNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    $nodes = $wb.SelectNodes('//d:sheets/d:sheet', $wbNs)
    foreach ($s in $nodes) {
        $name = [string]$s.GetAttribute('name')
        $rid = [string]$s.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
        Write-Output ("SHEET|" + $name + "|RID|" + $rid)
    }
}
finally {
    $zip.Dispose()
}

