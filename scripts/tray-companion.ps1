# Personal Operations Hub - Native Windows System Tray Companion (PowerShell 5.1 Compatible)
param(
    [int]$Port = 8000,
    [string]$BindHost = "127.0.0.1"
)

$ErrorActionPreference = "SilentlyContinue"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName Microsoft.VisualBasic

$phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    [System.Windows.Forms.MessageBox]::Show("PHP executable not found in PATH.", "Personal Operations Hub", "OK", "Error") | Out-Null
    exit 1
}
$phpExe = $phpCmd.Source
$HubUrl = "http://${BindHost}:${Port}"

# Synthesize a crisp indigo tray icon dynamically (no external .ico dependency required)
$bmp = New-Object System.Drawing.Bitmap 16, 16
$gfx = [System.Drawing.Graphics]::FromImage($bmp)
$gfx.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$brushBg = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(79, 70, 229))
$penCheck = New-Object System.Drawing.Pen ([System.Drawing.Color]::White, 2.0)
$gfx.FillEllipse($brushBg, 1, 1, 14, 14)
$gfx.DrawLines($penCheck, @(
    (New-Object System.Drawing.Point 4, 8),
    (New-Object System.Drawing.Point 7, 11),
    (New-Object System.Drawing.Point 12, 5)
))
$gfx.Dispose()
$trayIconHandle = $bmp.GetHicon()
$trayIcon = [System.Drawing.Icon]::FromHandle($trayIconHandle)

$notifyIcon = New-Object System.Windows.Forms.NotifyIcon
$notifyIcon.Icon = $trayIcon
$notifyIcon.Text = "Personal Operations Hub (Checking status...)"
$notifyIcon.Visible = $true

$contextMenu = New-Object System.Windows.Forms.ContextMenuStrip

$statusHeaderItem = New-Object System.Windows.Forms.ToolStripMenuItem("Status: Initializing...")
$statusHeaderItem.Enabled = $false
$contextMenu.Items.Add($statusHeaderItem) | Out-Null
$contextMenu.Items.Add((New-Object System.Windows.Forms.ToolStripSeparator)) | Out-Null

$openHubItem = New-Object System.Windows.Forms.ToolStripMenuItem("Open Personal Operations Hub")
$openHubItem.Add_Click({
    Start-Process $HubUrl
})
$contextMenu.Items.Add($openHubItem) | Out-Null

$quickAddItem = New-Object System.Windows.Forms.ToolStripMenuItem("Quick Capture Task (Smart Shorthand)...")
$quickAddItem.Add_Click({
    $raw = [Microsoft.VisualBasic.Interaction]::InputBox(
        "Enter task shorthand (e.g. 'Finish OS report tomorrow 17:00 !high #University ~90m'):",
        "Personal Operations Hub - Quick Capture",
        ""
    )
    if (-not [string]::IsNullOrWhiteSpace($raw)) {
        $out = & $phpExe artisan ops:quick-add "$raw" --no-interaction 2>&1 | Out-String
        $notifyIcon.ShowBalloonTip(3500, "Task Captured", $out.Trim(), [System.Windows.Forms.ToolTipIcon]::Info)
        Update-HubTrayStatus
    }
})
$contextMenu.Items.Add($quickAddItem) | Out-Null

$contextMenu.Items.Add((New-Object System.Windows.Forms.ToolStripSeparator)) | Out-Null

$startBgItem = New-Object System.Windows.Forms.ToolStripMenuItem("Start Background Services")
$startBgItem.Add_Click({
    $startScript = Join-Path $PSScriptRoot "start-background.ps1"
    Start-Process -FilePath "powershell" -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$startScript`"" -WindowStyle Hidden -Wait
    $notifyIcon.ShowBalloonTip(3000, "Operations Hub", "Background HTTP, Scheduler & Queue workers started.", [System.Windows.Forms.ToolTipIcon]::Info)
    Update-HubTrayStatus
})
$contextMenu.Items.Add($startBgItem) | Out-Null

$stopBgItem = New-Object System.Windows.Forms.ToolStripMenuItem("Stop Background Services")
$stopBgItem.Add_Click({
    $stopScript = Join-Path $PSScriptRoot "stop-background.ps1"
    Start-Process -FilePath "powershell" -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$stopScript`"" -WindowStyle Hidden -Wait
    $notifyIcon.ShowBalloonTip(3000, "Operations Hub", "Background services stopped cleanly.", [System.Windows.Forms.ToolTipIcon]::Info)
    Update-HubTrayStatus
})
$contextMenu.Items.Add($stopBgItem) | Out-Null

$backupNowItem = New-Object System.Windows.Forms.ToolStripMenuItem("Create Verified SQLite Backup Now")
$backupNowItem.Add_Click({
    $out = & $phpExe artisan ops:backup-database --no-interaction 2>&1 | Out-String
    $notifyIcon.ShowBalloonTip(3500, "SQLite Backup Verified", "Snapshot created in storage\app\backups.", [System.Windows.Forms.ToolTipIcon]::Info)
})
$contextMenu.Items.Add($backupNowItem) | Out-Null

$contextMenu.Items.Add((New-Object System.Windows.Forms.ToolStripSeparator)) | Out-Null

$exitItem = New-Object System.Windows.Forms.ToolStripMenuItem("Exit Tray Companion")
$exitItem.Add_Click({
    $timer.Stop()
    $notifyIcon.Visible = $false
    $notifyIcon.Dispose()
    [System.Windows.Forms.Application]::Exit()
})
$contextMenu.Items.Add($exitItem) | Out-Null

$notifyIcon.ContextMenuStrip = $contextMenu
$notifyIcon.Add_DoubleClick({
    Start-Process $HubUrl
})

function Update-HubTrayStatus {
    try {
        $jsonRaw = & $phpExe artisan ops:status --json --no-interaction 2>$null | Select-Object -Last 1
        if ($jsonRaw) {
            $data = $jsonRaw | ConvertFrom-Json
            if ($data.ok) {
                $focusLabel = "Idle"
                if ($data.active_focus) {
                    $mins = [math]::Floor(([int]$data.active_focus.remaining_seconds) / 60)
                    $secs = ([int]$data.active_focus.remaining_seconds) % 60
                    $focusLabel = ("{0} {1:D2}:{2:D2}" -f $data.active_focus.mode, $mins, $secs)
                }
                $summary = ("Due: {0} | Alerts: {1} | Focus: {2}" -f $data.due_today_count, $data.unread_notifications, $focusLabel)
                $statusHeaderItem.Text = $summary
                $tip = "Ops Hub - " + $summary
                if ($tip.Length -gt 63) { $tip = $tip.Substring(0, 63) }
                $notifyIcon.Text = $tip
            }
        }
    } catch {
        $notifyIcon.Text = "Personal Operations Hub"
    }
}

Update-HubTrayStatus

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 15000
$timer.Add_Tick({ Update-HubTrayStatus })
$timer.Start()

[System.Windows.Forms.Application]::Run()

