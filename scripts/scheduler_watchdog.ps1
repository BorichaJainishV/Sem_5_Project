param(
    [string]$LogPath = "$PSScriptRoot\..\storage\logs\drop_scheduler.log",
    [int]$MaxLagMinutes = 5,
    [string]$TaskName,
    [switch]$AutoRestart,
    [string]$WebhookUrl,
    [string]$WebhookAuthHeader,
    [string]$WebhookAuthValue
)

function Send-WatchdogWebhook {
    param(
        [string]$Url,
        [string]$Title,
        [string]$Message,
        [hashtable]$Context,
        [string]$AuthHeader,
        [string]$AuthValue
    )

    if (-not $Url) {
        return
    }

    $headers = @{}
    if ($AuthHeader -and $AuthValue) {
        $headers[$AuthHeader] = $AuthValue
    }

    $payload = [ordered]@{
        title = $Title
        message = $Message
        context = $Context
    }

    try {
        Invoke-RestMethod -Uri $Url -Method Post -Body ($payload | ConvertTo-Json -Depth 6) -Headers $headers -ContentType 'application/json' | Out-Null
    } catch {
        Write-Warning "Watchdog webhook failed: $($_.Exception.Message)"
    }
}

if (-not (Test-Path $LogPath)) {
    $msg = "Scheduler log not found at $LogPath"
    Write-Error $msg
    Send-WatchdogWebhook -Url $WebhookUrl -Title 'Drop scheduler watchdog' -Message $msg -Context @{ status = 'missing_log'; log_path = $LogPath } -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue
    exit 1
}

$tail = Get-Content -Path $LogPath -Tail 200
$timestampLine = $tail | Where-Object { $_ -match '^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]' } | Select-Object -Last 1
if (-not $timestampLine) {
    $msg = 'Unable to find timestamp in scheduler log.'
    Write-Error $msg
    Send-WatchdogWebhook -Url $WebhookUrl -Title 'Drop scheduler watchdog' -Message $msg -Context @{ status = 'invalid_log'; log_path = $LogPath } -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue
    exit 1
}

if ($timestampLine -notmatch '^\[(?<ts>[^\]]+)]') {
    $msg = "Unrecognized log line: $timestampLine"
    Write-Error $msg
    Send-WatchdogWebhook -Url $WebhookUrl -Title 'Drop scheduler watchdog' -Message $msg -Context @{ status = 'parse_error'; line = $timestampLine } -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue
    exit 1
}

$lastTime = Get-Date $Matches['ts']
$lag = (Get-Date) - $lastTime
$lagMinutes = [math]::Round($lag.TotalMinutes, 2)

if ($lag.TotalMinutes -le $MaxLagMinutes) {
    Write-Output "Scheduler healthy. Last run $lagMinutes minutes ago."
    exit 0
}

$context = @{
    status = 'stale'
    last_run = $lastTime.ToString('u')
    lag_minutes = $lagMinutes
    threshold_minutes = $MaxLagMinutes
}
$message = "Scheduler stale: last run $lagMinutes minutes ago (threshold $MaxLagMinutes)."
Write-Warning $message

if ($AutoRestart -and $TaskName) {
    try {
        Start-ScheduledTask -TaskName $TaskName
        $context['restart_triggered'] = $true
    } catch {
        $context['restart_triggered'] = $false
        $context['restart_error'] = $_.Exception.Message
        Write-Warning ("Failed to restart task {0}: {1}" -f $TaskName, $_.Exception.Message)
    }
}

Send-WatchdogWebhook -Url $WebhookUrl -Title 'Drop scheduler watchdog' -Message $message -Context $context -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue

exit 1
