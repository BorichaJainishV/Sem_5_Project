param(
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$LogPath = "$PSScriptRoot\..\storage\logs\drop_scheduler.log",
    [switch]$DryRun,
    [string]$WebhookUrl,
    [string]$WebhookAuthHeader,
    [string]$WebhookAuthValue
)

# Auto-skip controls:
# - If `storage/scheduler_suspend.json` exists and contains {"suspended": true, "until": "ISO8601"}, the run is skipped.
# - If Apache/httpd or MySQL/MariaDB processes are not running, the run is skipped to avoid task noise.
# - Set env var DROP_SCHEDULER_FORCE_RUN=1 to bypass checks (use with caution).

function Get-SkipReason {
    param()

    $repoRoot = Resolve-Path "$PSScriptRoot\.."
    $suspendFile = Join-Path $repoRoot "storage\scheduler_suspend.json"

    # Respect explicit force-run env var
    if ($env:DROP_SCHEDULER_FORCE_RUN -eq '1') {
        return $null
    }

    if (Test-Path $suspendFile) {
        try {
            $content = Get-Content $suspendFile -Raw | ConvertFrom-Json -ErrorAction Stop
            if ($content.suspended -eq $true) {
                if ($content.until) {
                    $until = [DateTime]::Parse($content.until)
                    if ([DateTime]::UtcNow -lt $until.ToUniversalTime()) {
                        return "suspended until $($content.until)"
                    }
                } else {
                    return "suspended (no until)"
                }
            }
        } catch {
            # ignore parse errors and continue
        }
    }

    # Check for common web server processes (httpd/apache) and DB (mysqld/mariadb)
    $webProc = Get-Process -Name httpd,apache,apache2 -ErrorAction SilentlyContinue | Select-Object -First 1
    $dbProc = Get-Process -Name mysqld,mariadb,mysqld64 -ErrorAction SilentlyContinue | Select-Object -First 1

    if (-not $webProc) {
        return 'web-server-not-running'
    }
    if (-not $dbProc) {
        return 'database-not-running'
    }

    return $null
}

function Send-DropWebhook {
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

    $payload = [ordered]@{
        title = $Title
        message = $Message
        context = $Context
    }

    $headers = @{}
    if ($AuthHeader -and $AuthValue) {
        $headers[$AuthHeader] = $AuthValue
    }

    try {
        Invoke-RestMethod -Uri $Url -Method Post -Body ($payload | ConvertTo-Json -Depth 6) -Headers $headers -ContentType 'application/json' | Out-Null
    } catch {
        Write-Warning "Webhook notification failed: $($_.Exception.Message)"
    }
}

function Get-EventFromStdout {
    param([string]$Stdout)

    $result = @{}
    if (-not $Stdout) {
        return $result
    }

    if ($Stdout -match '(?s)Activation result:\s*(\{.+)') {
        $json = $Matches[1]
        try {
            $parsed = $json | ConvertFrom-Json -ErrorAction Stop
            $slug = $parsed.state.drop_slug
            $result.type = 'activated'
            $result.slug = $slug
            $result.raw = $parsed
        } catch {}
    } elseif ($Stdout -match '(?s)Deactivation result:\s*(\{.+)') {
        $json = $Matches[1]
        try {
            $parsed = $json | ConvertFrom-Json -ErrorAction Stop
            $slug = $parsed.state.active_slug
            $result.type = 'deactivated'
            $result.slug = $slug
            $result.raw = $parsed
        } catch {}
    }

    return $result
}

$repoRoot = Resolve-Path "$PSScriptRoot\.."
$script = Join-Path $repoRoot "scripts/drop_scheduler.php"
if (-not (Test-Path $PhpPath)) {
    Write-Error "PHP executable not found at $PhpPath"
    exit 1
}
if (-not (Test-Path $script)) {
    Write-Error "Scheduler script not found at $script"
    exit 1
}

# Determine whether we should skip this run
$skipReason = Get-SkipReason
if ($skipReason) {
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $msg = "[$timestamp] Skipping drop_scheduler run: $skipReason"
    $logDir = Split-Path $LogPath -Parent
    if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir | Out-Null }
    $msg | Out-File -FilePath $LogPath -Encoding utf8 -Append
    # Exit cleanly (0) so Task Scheduler doesn't treat this as an error
    exit 0
}

$logDir = Split-Path $LogPath -Parent
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
$argsList = @($script)
if ($DryRun.IsPresent) {
    $argsList += '--dry-run'
}

Push-Location $repoRoot
try {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $PhpPath
    $psi.Arguments = [string]::Join(' ', $argsList)
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false
    $proc = [System.Diagnostics.Process]::Start($psi)
    $stdout = $proc.StandardOutput.ReadToEnd()
    $stderr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()
    $exitCode = $proc.ExitCode
    $eventData = Get-EventFromStdout -Stdout $stdout

    $logBlock = @()
    $logBlock += "[$timestamp] php drop_scheduler (exit $exitCode)"
    if ($DryRun) {
        $logBlock += "[mode] dry-run"
    }
    if ($stdout.Trim()) {
        $logBlock += "[stdout]"
        $logBlock += $stdout.TrimEnd()
    }
    if ($stderr.Trim()) {
        $logBlock += "[stderr]"
        $logBlock += $stderr.TrimEnd()
    }
    $logBlock += ""

    $logBlock | Out-File -FilePath $LogPath -Encoding utf8 -Append

    if ($exitCode -ne 0) {
        Write-Error "Scheduler returned $exitCode"
        exit $exitCode
    }

    if ($WebhookUrl -and $eventData.type) {
        $title = "Drop $($eventData.type)"
        $message = "drop_scheduler reported $($eventData.type) for slug '$($eventData.slug)'"
        $context = @{
            timestamp = $timestamp
            slug = $eventData.slug
            dry_run = [bool]$DryRun
            exit_code = $exitCode
            raw = $eventData.raw
        }
        Send-DropWebhook -Url $WebhookUrl -Title $title -Message $message -Context $context -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue
    }
}
finally {
    Pop-Location
}
