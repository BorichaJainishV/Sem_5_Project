 ' VBScript launcher to run the PowerShell wrapper hidden
 ' Usage: scheduled task should run: wscript.exe "D:\XAMPP\htdocs\Sem_5_Project\scripts\run_drop_scheduler_launcher.vbs"

Set WshShell = CreateObject("WScript.Shell")
scriptPath = "D:\XAMPP\htdocs\Sem_5_Project\scripts\run_drop_scheduler.ps1"
phpPath = "" ' optional: not used here; wrapper sets php path by default

 ' Build the command to run PowerShell in hidden window
 cmd = "powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File " & Chr(34) & scriptPath & Chr(34)

On Error Resume Next
WshShell.Run cmd, 0, False
