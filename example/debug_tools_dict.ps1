$ErrorActionPreference = 'Stop'
$csv = (Get-ChildItem -Path 'example/excel_export' -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1).FullName
$rows = Import-Csv -Path $csv
$dict = @{}
$i = 0
foreach ($r in $rows) {
  $i++
  if ($i -gt 2000) { break }
  $item = [string]$r.'Item No.'
  if ([string]::IsNullOrWhiteSpace($item)) { continue }
  if ($item -eq 'Item No.') { continue }
  if (-not $dict.ContainsKey($item)) { $dict[$item] = 1 }
}
Write-Output ("I=" + $i)
Write-Output ("DICT=" + $dict.Count)
$dict.Keys | Select-Object -First 10 | ForEach-Object { Write-Output $_ }
