# Local Calendar Export & Subscription Guide (`.ics`)

Personal Operations Hub generates standards-compliant **RFC 5545 iCalendar (`.ics`)** files containing your scheduled **Calendar Events**, **Tasks with due dates/times**, **Project target deadlines**, and **Project Milestones**—complete with `VALARM` display triggers.

---

## 1. One-Click `.ics` File Export

For an immediate offline snapshot import into any calendar app:

1. Navigate to **Calendar** (`/calendar`).
2. Click **Export `.ics`** in the top toolbar (downloads `personal-operations-hub-YYYY-MM-DD.ics`).
3. Import the downloaded `.ics` file into Outlook, Windows Calendar, Thunderbird, or Apple Calendar.

---

## 2. Live Local `.ics` Subscription Feed (Private Token URL)

To let a local desktop calendar client poll Personal Operations Hub automatically without browser session cookies:

1. Navigate to **Calendar** (`/calendar`) and click **Subscribe (`.ics`)** *(or open **Settings -> Automated Backups & Private Calendar Feed**)*.
2. Click **Generate Private Link**.
3. Copy your private URL:
   ```text
   http://127.0.0.1:8000/calendar/feed/<48-char-hex-token>.ics
   ```
4. Ensure background services are running (`scripts\start-background.ps1` or `start.ps1`) so `http://127.0.0.1:8000` stays reachable.

### Token Security & Revocation
- Only the **SHA-256 hash** (for constant-time verification) and an **encrypted copy** (using your local `APP_KEY`) are stored in SQLite.
- Clicking **Regenerate** rotates the token and immediately invalidates the old URL (`HTTP 403`).
- Clicking **Revoke** disables external `.ics` feed access immediately.
- Soft-deleted tasks and archived projects emit `STATUS:CANCELLED` with stable `UID`s (`task-{id}@personal-operations-hub.local`, etc.) so subscribed clients remove cancelled items cleanly.

---

## 3. Subscribing in Desktop Calendar Apps

### Mozilla Thunderbird (Recommended for Localhost Feeds on Windows)
1. Open Thunderbird -> **Calendar** tab -> **New Calendar...**
2. Select **On the Network** -> **Next**.
3. Check **This location doesn't require credentials** and paste your `http://127.0.0.1:8000/calendar/feed/<token>.ics` URL.
4. Choose a refresh interval (e.g., **Every 30 minutes**) and click **Subscribe**.

### Classic Outlook Desktop (Windows)
1. Open Outlook -> **Calendar** -> **Add Calendar** -> **From Internet...**
2. Paste `http://127.0.0.1:8000/calendar/feed/<token>.ics` and confirm.

### Important Note on Cloud-Hosted Calendar Services (Google Calendar / Outlook Web)
- Cloud calendar servers (such as Google Calendar's web servers or Microsoft 365 cloud servers) fetch subscription URLs from remote data centers and **cannot reach `127.0.0.1` on your private PC**.
- For Google Calendar or Outlook on the Web while running purely locally, use **Export `.ics`** and import the `.ics` file manually (`Settings -> Import & export`).

---

## 4. Optional Phone / Tablet Subscription on Home Wi-Fi (LAN)

To subscribe from an iPhone, iPad, or Android calendar client while connected to the **same local Wi-Fi network**:

1. Find your Windows PC's local LAN IPv4 address (e.g., `192.168.1.50`):
   ```powershell
   ipconfig
   ```
2. Start the background runner bound to your LAN interface (`0.0.0.0`):
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\scripts\start-background.ps1 -BindHost 0.0.0.0 -Port 8000
   ```
3. Allow inbound TCP port `8000` on your **Private** Windows Firewall profile (run once in an Administrator PowerShell):
   ```powershell
   New-NetFirewallRule -DisplayName "Personal Operations Hub (LAN)" -Direction Inbound -LocalPort 8000 -Protocol TCP -Profile Private -Action Allow
   ```
4. Replace `127.0.0.1` in your private feed URL with your PC's LAN IP:
   ```text
   http://192.168.1.50:8000/calendar/feed/<token>.ics
   ```
   *(Your phone will refresh the feed automatically whenever both devices are awake on the same Wi-Fi network.)*

