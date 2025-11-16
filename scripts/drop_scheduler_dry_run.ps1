param(
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$LogPath = "$PSScriptRoot\..\storage\logs\drop_scheduler_dryrun.log"
)

$runner = Join-Path $PSScriptRoot 'run_drop_scheduler.ps1'
if (-not (Test-Path $runner)) {
    Write-Error "Runner script not found at $runner"
    exit 1
}

& $runner -PhpPath $PhpPath -LogPath $LogPath -DryRun
