param(
    [string]$TaskPrefix = "Mystic",
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$WorkingDirectory,
    [string]$WebhookUrl,
    [string]$WebhookAuthHeader,
    [string]$WebhookAuthValue,
    [int]$SchedulerIntervalSeconds = 60,
    [int]$WatchdogIntervalMinutes = 5,
    [int]$FailsafeIntervalSeconds = 60,
    [string]$ProbeUrl = "http://localhost/index.php",
    [switch]$DryRun
)

if (-not $WorkingDirectory) {
    $WorkingDirectory = (Resolve-Path "$PSScriptRoot\..")
}

function New-DropTaskAction {
    param(
        [string]$ScriptPath,
        [string[]]$Arguments
    )

    $argString = @('-ExecutionPolicy', 'Bypass', '-File', $ScriptPath) + $Arguments
    return New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ($argString -join ' ') -WorkingDirectory $WorkingDirectory
}

function Register-DropTask {
    param(
        [string]$Name,
        [Microsoft.Management.Infrastructure.CimInstance]$Trigger,
        [Microsoft.Management.Infrastructure.CimInstance]$Action,
        [switch]$DryRun
    )

    $definition = New-ScheduledTask -Action $Action -Trigger $Trigger -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable)
    if ($DryRun) {
        Write-Output "[dry-run] Would register task '$Name'"
        return
    }
    if (Get-ScheduledTask -TaskName $Name -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $Name -Confirm:$false
    }
    Register-ScheduledTask -TaskName $Name -InputObject $definition | Out-Null
}

$runnerScript = Join-Path $WorkingDirectory 'scripts\run_drop_scheduler.ps1'
$watchdogScript = Join-Path $WorkingDirectory 'scripts\scheduler_watchdog.ps1'
$failsafeScript = Join-Path $WorkingDirectory 'scripts\auto_revert_failsafe.ps1'

$commonWebhookArgs = @()
if ($WebhookUrl) { $commonWebhookArgs += @('-WebhookUrl', $WebhookUrl) }
if ($WebhookAuthHeader -and $WebhookAuthValue) {
    $commonWebhookArgs += @('-WebhookAuthHeader', $WebhookAuthHeader, '-WebhookAuthValue', $WebhookAuthValue)
}

$schedulerArgs = @('-PhpPath', ('"' + $PhpPath + '"')) + $commonWebhookArgs
$schedulerInterval = New-TimeSpan -Seconds $SchedulerIntervalSeconds
$longDuration = New-TimeSpan -Days 3650
Register-DropTask -Name "$TaskPrefix Drop Scheduler" -Trigger (New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval $schedulerInterval -RepetitionDuration $longDuration) -Action (New-DropTaskAction -ScriptPath $runnerScript -Arguments $schedulerArgs) -DryRun:$DryRun

$watchdogArgs = @('-LogPath', 'storage\logs\drop_scheduler.log', '-MaxLagMinutes', $WatchdogIntervalMinutes, '-TaskName', ("$TaskPrefix Drop Scheduler"), '-AutoRestart') + $commonWebhookArgs
$watchdogInterval = New-TimeSpan -Minutes $WatchdogIntervalMinutes
Register-DropTask -Name "$TaskPrefix Drop Watchdog" -Trigger (New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval $watchdogInterval -RepetitionDuration $longDuration) -Action (New-DropTaskAction -ScriptPath $watchdogScript -Arguments $watchdogArgs) -DryRun:$DryRun

$failsafeArgs = @('-PhpPath', ('"' + $PhpPath + '"'), '-ProbeUrl', ('"' + $ProbeUrl + '"')) + $commonWebhookArgs
$failsafeInterval = New-TimeSpan -Seconds $FailsafeIntervalSeconds
Register-DropTask -Name "$TaskPrefix Drop Failsafe" -Trigger (New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval $failsafeInterval -RepetitionDuration $longDuration) -Action (New-DropTaskAction -ScriptPath $failsafeScript -Arguments $failsafeArgs) -DryRun:$DryRun

if ($DryRun) {
    Write-Output "Dry run complete. No tasks registered."
} else {
    Write-Output "Scheduled tasks registered under prefix '$TaskPrefix'."
}
