<#
Compare the migration file for flash_banners with the CREATE TABLE block found in the SQL dump.

Usage:
  .\scripts\diff_migration_vs_dump.ps1
  .\scripts\diff_migration_vs_dump.ps1 -DumpFile mystic_clothing.sql -MigrationFile database/migrations/2025_11_14_add_drop_banner_fields.sql
#>
param(
    [string]$DumpFile = 'mystic_clothing.sql',
    [string]$MigrationFile = 'database/migrations/2025_11_14_add_drop_banner_fields.sql'
)

if (-not (Test-Path $DumpFile)) {
    Write-Error "Dump file not found: $DumpFile"
    exit 2
}
if (-not (Test-Path $MigrationFile)) {
    Write-Error "Migration file not found: $MigrationFile"
    exit 2
}

$lines = Get-Content $DumpFile
$start = ($lines | Select-String -Pattern "CREATE TABLE `flash_banners`" -SimpleMatch).LineNumber
if (-not $start) {
    Write-Error "CREATE TABLE `flash_banners` not found in $DumpFile"
    exit 3
}

# find the end: the line that begins with ') ENGINE=' after the start
$end = $null
for ($i = $start; $i -le $lines.Count; $i++) {
    if ($lines[$i-1] -match '^\) ENGINE=') { $end = $i; break }
}
if (-not $end) { $end = $start + 200 }

$dumpBlock = $lines[($start-1)..($end-1)] -join "`n"
$dumpTmp = [IO.Path]::GetTempFileName() + '_flash_banners_dump.sql'
Set-Content -Path $dumpTmp -Value $dumpBlock -Encoding utf8

$migrationContent = Get-Content $MigrationFile -Raw
$migrationTmp = [IO.Path]::GetTempFileName() + '_migration.sql'
Set-Content -Path $migrationTmp -Value $migrationContent -Encoding utf8

Write-Output "Dump CREATE TABLE block saved to: $dumpTmp"
Write-Output "Migration file copied to: $migrationTmp"

Write-Output "Running side-by-side diff (PowerShell Compare-Object):"
$left = Get-Content $dumpTmp
$right = Get-Content $migrationTmp
Compare-Object -ReferenceObject $left -DifferenceObject $right -SyncWindow 0 | Format-Table -AutoSize

Write-Output "If you prefer a unified textual diff, open the two temp files in an editor to inspect differences."

exit 0
