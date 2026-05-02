$ErrorActionPreference = 'Stop'

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$xlsxFile = Get-ChildItem -Path $baseDir -Filter '*.xlsx' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $xlsxFile) { throw "No .xlsx file found" }

$xlsxPath = $xlsxFile.FullName
$outDir = Join-Path $baseDir 'excel_export'
if (!(Test-Path $outDir)) { New-Item -Path $outDir -ItemType Directory | Out-Null }

$connString = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$xlsxPath;Extended Properties='Excel 12.0 Xml;HDR=YES;IMEX=1';"
$conn = New-Object System.Data.OleDb.OleDbConnection($connString)
$conn.Open()

try {
    $schema = $conn.GetOleDbSchemaTable([System.Data.OleDb.OleDbSchemaGuid]::Tables, $null)
    foreach ($row in $schema.Rows) {
        $sheetName = [string]$row.TABLE_NAME
        if (-not $sheetName.EndsWith('$') -and -not $sheetName.EndsWith('$''')) { continue }
        $clean = $sheetName.Trim("'")
        $safeName = (($clean -replace '\$$','') -replace '[\\/:*?"<>|]', '_')
        $query = "SELECT * FROM [$clean]"
        $adapter = New-Object System.Data.OleDb.OleDbDataAdapter($query, $conn)
        $table = New-Object System.Data.DataTable
        [void]$adapter.Fill($table)
        $csvPath = Join-Path $outDir ($safeName + '.csv')
        $table | Export-Csv -Path $csvPath -NoTypeInformation -Encoding UTF8
        Write-Output ("EXPORTED|" + $safeName + "|" + $table.Rows.Count)
    }
}
finally {
    $conn.Close()
}

