$ErrorActionPreference = 'Stop'
$csv = (Get-ChildItem -Path 'example/excel_export' -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1).FullName
$rows = Import-Csv -Path $csv
$i = 0
foreach ($r in $rows) {
  $i++
  if ($i -gt 20) { break }
  $item = [string]$r.'Item No.'
  $empty = [string]::IsNullOrWhiteSpace($item)
  Write-Output ($i.ToString() + '|item=' + $item + '|empty=' + $empty)
}
