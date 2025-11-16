param(
    [ValidateSet('disable','enable','remove','status')]
    [string]$Action = 'status',
    [string]$TaskPrefix = 'Mystic'
)

$taskNames = @(
    "$TaskPrefix Drop Scheduler",
    "$TaskPrefix Drop Watchdog",
    "$TaskPrefix Drop Failsafe"
)

function Invoke-TaskAction {
    param(
        [string]$Name,
        [string]$Action
    )

    $task = Get-ScheduledTask -TaskName $Name -ErrorAction SilentlyContinue
    if (-not $task) {
        Write-Warning "Task '$Name' not found."
        return
    }

    switch ($Action) {
        'disable' {
            Disable-ScheduledTask -TaskName $Name | Out-Null
            Write-Output "Disabled $Name"
        }
        'enable' {
            Enable-ScheduledTask -TaskName $Name | Out-Null
            Write-Output "Enabled $Name"
        }
        'remove' {
            Unregister-ScheduledTask -TaskName $Name -Confirm:$false
            Write-Output "Removed $Name"
        }
        'status' {
            $info = Get-ScheduledTaskInfo -TaskName $Name
            Write-Output ("{0}: state={1}, lastRun={2}, nextRun={3}" -f $Name, $task.State, $info.LastRunTime, $info.NextRunTime)
        }
    }
}

foreach ($name in $taskNames) {
    Invoke-TaskAction -Name $name -Action $Action
}
