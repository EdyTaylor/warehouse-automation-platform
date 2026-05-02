$ErrorActionPreference = 'Stop'

$dir = 'example/excel_export'
$file = Get-ChildItem -Path $dir -Filter '*.csv' | Sort-Object Length -Descending | Select-Object -First 1
if (-not $file) { throw 'CSV file not found' }

Write-Output ("FILE=" + $file.FullName)
Write-Output ("SIZE=" + $file.Length)

$rows = Import-Csv -Path $file.FullName
Write-Output ("ROWS=" + $rows.Count)

$first = $rows | Select-Object -First 1
if ($first) {
    $names = $first.PSObject.Properties | Select-Object -ExpandProperty Name
    Write-Output ("COLS=" + ($names -join '|'))
}

$rows | Select-Object -First 10 | ForEach-Object {
    $props = $_.PSObject.Properties
    Write-Output ("PROP_COUNT=" + $props.Count)
    $c1 = if ($props.Count -gt 0) { [string]$props[0].Value } else { '' }
    $c2 = if ($props.Count -gt 1) { [string]$props[1].Value } else { '' }
    $c4 = if ($props.Count -gt 3) { [string]$props[3].Value } else { '' }
    $c5 = if ($props.Count -gt 4) { [string]$props[4].Value } else { '' }
    Write-Output ($c1 + '|' + $c2 + '|' + $c4 + '|' + $c5)
}

