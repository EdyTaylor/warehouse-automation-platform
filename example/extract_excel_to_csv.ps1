$ErrorActionPreference = 'Stop'

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$xlsxFile = Get-ChildItem -Path $baseDir -Filter '*.xlsx' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $xlsxFile) {
    throw "No .xlsx file found in $baseDir"
}
$xlsxPath = $xlsxFile.FullName
$outDir = Join-Path $baseDir 'excel_export'

if (!(Test-Path $outDir)) {
    New-Item -Path $outDir -ItemType Directory | Out-Null
}

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

try {
    Write-Output ("WORKBOOK|" + $xlsxPath)
    $wb = $excel.Workbooks.Open($xlsxPath)
    foreach ($ws in $wb.Worksheets) {
        $safeName = ($ws.Name -replace '[\\/:*?"<>|]', '_')
        $csvPath = Join-Path $outDir ($safeName + '.csv')
        $ws.Copy() | Out-Null
        $tempWb = $excel.ActiveWorkbook
        $tempWb.SaveAs($csvPath, 6)
        $tempWb.Close($false)
        Write-Output ("EXPORTED|" + $ws.Name + "|" + $csvPath)
    }
    $wb.Close($false)
}
finally {
    $excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
}

