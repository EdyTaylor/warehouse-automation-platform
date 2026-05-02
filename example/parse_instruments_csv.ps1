$ErrorActionPreference = 'Stop'

$path = 'E:\Приложение\public_html\example\excel_export\Цены - Инструменты.csv'
$rows = Import-Csv -Path $path
Write-Output ("ROWS=" + $rows.Count)

$rows | Select-Object -First 12 | ForEach-Object {
    $n = $_.'No.'
    $item = $_.'Item No.'
    $opt = $_.'продажа опт'
    $ret = $_.'продажа розница'
    Write-Output ($n + "|" + $item + "|" + $opt + "|" + $ret)
}

