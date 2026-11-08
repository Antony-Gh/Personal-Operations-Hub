param(
    [Parameter(Mandatory = $true)][string]$Title,
    [Parameter(Mandatory = $true)][string]$Message,
    [string]$Category = "Personal Operations Hub"
)

# Browserless Windows 10/11 Action Center Toast Notification (PowerShell 5.1 Compatible)
$ErrorActionPreference = "SilentlyContinue"

function Escape-XmlText {
    param([string]$InputText)
    if ([string]::IsNullOrEmpty($InputText)) { return "" }
    return [System.Security.SecurityElement]::Escape($InputText)
}

$safeTitle = Escape-XmlText $Title
$safeMessage = Escape-XmlText $Message
$safeAttribution = Escape-XmlText ("Personal Operations Hub - " + ($Category -replace '_', ' '))

$dispatched = $false

try {
    $null = [Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime]
    $null = [Windows.Data.Xml.Dom.XmlDocument, Windows.Data.Xml.Dom.XmlDocument, ContentType = WindowsRuntime]

    $xmlString = @"
<toast scenario="reminder">
    <visual>
        <binding template="ToastGeneric">
            <text>$safeTitle</text>
            <text>$safeMessage</text>
            <text placement="attribution">$safeAttribution</text>
        </binding>
    </visual>
    <audio src="ms-winsoundevent:Notification.Default" />
</toast>
"@

    $toastXml = New-Object Windows.Data.Xml.Dom.XmlDocument
    $toastXml.LoadXml($xmlString)

    # Use built-in Windows PowerShell AUMID so notifications display cleanly without registry changes
    $appId = "{1AC14E77-02E7-4E5D-B744-2EB1AE5198B7}\WindowsPowerShell\v1.0\powershell.exe"
    $toast = [Windows.UI.Notifications.ToastNotification]::new($toastXml)
    [Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier($appId).Show($toast)
    $dispatched = $true
} catch {
    $dispatched = $false
}

if (-not $dispatched) {
    try {
        Add-Type -AssemblyName System.Windows.Forms
        Add-Type -AssemblyName System.Drawing
        $notify = New-Object System.Windows.Forms.NotifyIcon
        $notify.Icon = [System.Drawing.SystemIcons]::Information
        $notify.BalloonTipTitle = $Title
        $notify.BalloonTipText = $Message
        $notify.Visible = $true
        $notify.ShowBalloonTip(5000)
        Start-Sleep -Milliseconds 1200
        $notify.Dispose()
    } catch {
        # Silently ignore if non-interactive session has no desktop surface
    }
}

exit 0

