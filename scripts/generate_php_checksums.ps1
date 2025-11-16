<#
Generate SHA1 checksums for all PHP files in the repository and write to docs/php_checksums.txt

Usage:
  powershell -ExecutionPolicy Bypass -File scripts/generate_php_checksums.ps1
#>
$out = 'docs/php_checksums.txt'
New-Item -ItemType Directory -Path (Split-Path $out) -Force | Out-Null
Get-ChildItem -Recurse -Filter '*.php' | Sort-Object FullName | ForEach-Object {
    $path = $_.FullName
    $hash = Get-FileHash -Algorithm SHA1 -Path $path
    "{0}  {1}" -f $hash.Hash, ($path -replace '\\', '/')
} | Set-Content -Path $out -Encoding utf8

Write-Output "Wrote PHP checksum manifest to $out"
