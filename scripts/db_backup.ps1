<#
Creates a timestamped mysqldump and archives key runtime JSON files.

Usage examples:
  # Prompt for password interactively
  .\scripts\db_backup.ps1 -BackupDir D:\backups

  # Provide password on the command line (less secure)
  .\scripts\db_backup.ps1 -Password mysecret -BackupDir D:\backups

#>
param(
    [string]$MysqlDumpPath = 'C:\xampp\mysql\bin\mysqldump.exe',
    [string]$DbUser = 'root',
    [string]$DbName = 'mystic',
    [string]$BackupDir = 'D:\backups',
    [string]$Password = ''
)

if (-not (Test-Path $MysqlDumpPath)) {
    Write-Error "mysqldump not found at $MysqlDumpPath. Update -MysqlDumpPath to your MySQL bin location."
    exit 2
}

New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null

$t = Get-Date -Format yyyyMMdd_HHmmss
$sqlFile = Join-Path $BackupDir ("${DbName}_dump_$t.sql")
$zipFile = Join-Path $BackupDir ("${DbName}_backup_$t.zip")

if ($Password -ne '') {
    $pwdArg = "-p$Password"
} else {
    # no inline password -> will prompt the user
    $pwdArg = '-p'
}

Write-Output "Creating SQL dump: $sqlFile"
& $MysqlDumpPath -u $DbUser $pwdArg --single-transaction --quick --routines --triggers --events $DbName > $sqlFile

if ($LASTEXITCODE -ne 0) {
    Write-Error "mysqldump failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}

# Collect runtime JSONs and logs
$tempDir = Join-Path $BackupDir ("${DbName}_artifact_$t")
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

$itemsToCopy = @('storage\drop_waitlists.json','storage\drop_promotions_state.json','storage\logs\drop_scheduler.log')
foreach ($it in $itemsToCopy) {
    if (Test-Path $it) {
        Copy-Item $it $tempDir -Force -ErrorAction SilentlyContinue
    }
}

# Add the SQL dump into the folder for zipping
Copy-Item $sqlFile $tempDir -Force

Write-Output "Creating archive $zipFile"
Compress-Archive -Path (Join-Path $tempDir '*') -DestinationPath $zipFile -Force

Write-Output "Backup complete: $zipFile"

# Cleanup intermediate folder
Remove-Item $tempDir -Recurse -Force

exit 0
