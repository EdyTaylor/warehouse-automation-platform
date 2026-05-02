$ErrorActionPreference = 'Stop'
$f = Get-ChildItem 'example/excel_export' -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1
$rows = Import-Csv -Path $f.FullName
$first = $rows | Select-Object -First 1
$first | ConvertTo-Json -Depth 3
