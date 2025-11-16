param(
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$ProbeUrl = "http://localhost/index.php",
    [int]$ProbeRetries = 1,
    [int]$RetryDelaySeconds = 5,
    [switch]$ExpectCountdown,
    [string]$WebhookUrl,
    [string]$WebhookAuthHeader,
    [string]$WebhookAuthValue
)

$repoRoot = Resolve-Path "$PSScriptRoot\.."
$probeScript = Join-Path $repoRoot 'scripts/drop_probe.php'
$schedulerScript = Join-Path $repoRoot 'scripts/drop_scheduler.php'

if (-not (Test-Path $PhpPath)) {
    Write-Error "PHP executable not found at $PhpPath"
    exit 1
}
if (-not (Test-Path $probeScript)) {
    Write-Error "drop_probe.php not found at $probeScript"
    exit 1
}
if (-not (Test-Path $schedulerScript)) {
    Write-Error "drop_scheduler.php not found at $schedulerScript"
    exit 1
}

function Invoke-DropWebhook {
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
        Write-Warning "Failsafe webhook failed: $($_.Exception.Message)"
    }
}

function Invoke-PhpUtility {
    param(
        [string]$PhpExe,
        [string]$ScriptPath,
        [string[]]$Arguments,
        [string]$WorkingDir
    )

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $PhpExe
    $psi.Arguments = ([string]::Join(' ', @($ScriptPath) + $Arguments))
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false
    $psi.WorkingDirectory = $WorkingDir

    $proc = [System.Diagnostics.Process]::Start($psi)
    $stdout = $proc.StandardOutput.ReadToEnd()
    $stderr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()

    return [pscustomobject]@{
        ExitCode = $proc.ExitCode
        Stdout = $stdout
        Stderr = $stderr
    }
}

function Run-Probe {
    param(
        [string]$PhpExe,
        [string]$ScriptPath,
        [string]$Url,
        [switch]$ExpectCountdown,
        [string]$WorkingDir
    )

    $args = @("--url=$Url", '--expect-state=live')
    if ($ExpectCountdown) {
        $args += '--expect-countdown=true'
    }

    return Invoke-PhpUtility -PhpExe $PhpExe -ScriptPath $ScriptPath -Arguments $args -WorkingDir $WorkingDir
}

$attempts = $ProbeRetries + 1
for ($i = 0; $i -lt $attempts; $i++) {
    $probeResult = Run-Probe -PhpExe $PhpPath -ScriptPath $probeScript -Url $ProbeUrl -ExpectCountdown:$ExpectCountdown -WorkingDir $repoRoot
    if ($probeResult.ExitCode -eq 0) {
        Write-Output "Probe succeeded on attempt $($i + 1)."
        exit 0
    }

    Write-Warning "Probe attempt $($i + 1) failed (exit $($probeResult.ExitCode))."
    if ($i -lt $attempts - 1) {
        Start-Sleep -Seconds $RetryDelaySeconds
    }
}

Write-Warning 'All probe attempts failed. Triggering drop scheduler sync.'
$schedulerResult = Invoke-PhpUtility -PhpExe $PhpPath -ScriptPath $schedulerScript -Arguments @('--force-env') -WorkingDir $repoRoot

if ($schedulerResult.Stdout) {
    Write-Output $schedulerResult.Stdout.TrimEnd()
}
if ($schedulerResult.Stderr) {
    Write-Warning $schedulerResult.Stderr.TrimEnd()
}

$context = @{
    probe_url = $ProbeUrl
    probe_retries = $ProbeRetries
    probe_stdout = $probeResult.Stdout
    probe_stderr = $probeResult.Stderr
    scheduler_exit = $schedulerResult.ExitCode
    scheduler_stdout = $schedulerResult.Stdout
    scheduler_stderr = $schedulerResult.Stderr
}

Invoke-DropWebhook -Url $WebhookUrl -Title 'Drop failsafe triggered' -Message 'Auto-revert fired drop scheduler sync.' -Context $context -AuthHeader $WebhookAuthHeader -AuthValue $WebhookAuthValue

if ($schedulerResult.ExitCode -ne 0) {
    Write-Error "Scheduler restart failed with exit $($schedulerResult.ExitCode)."
    exit $schedulerResult.ExitCode
}

exit 1
