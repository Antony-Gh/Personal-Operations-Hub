# Personal Operations Hub — Phase 5 Operational Dogfooding Guide & Usability Ledger

> **Milestone:** Phase 5 — Track A: Real-World Usability & Personal Dogfooding  
> **Environment:** Windows 10/11 (Local Workstation) • PHP 8.4.15 • Laravel Framework 13.39.0 • SQLite 3 WAL  
> **Target Cycle:** 7–14 Days Continuous Everyday Personal Operations  
> **Status:** Operational Readiness Verified  

---

## 1. Quick Reference: Application Lifecycle Commands

All commands are validated for Windows PowerShell 5.1 and PowerShell 7+. Run them from the project root (`E:\Projects\Personal-Operations-Hub`).

### Starting the Application
```powershell
# Option A (Interactive all-in-one launcher - starts background queues, Vite compiler, server):
.\scripts\start.ps1

# Option B (Standard Composer dev runner - starts PHP serve & Vite HMR):
composer run dev

# Option C (Manual parallel consoles):
# Console 1 (Backend):
php artisan serve
# Console 2 (Frontend compiler):
npm run dev
```

### Checking Operational Status
```powershell
# Check background daemons and Vite compiler status:
.\scripts\start.ps1 -Status

# Comprehensive operational status report (reminders, backups, recurring tasks):
php artisan ops:status
```

### Stopping the Application
```powershell
# Stop all background processes, workers, and servers cleanly:
.\scripts\stop.ps1
```

---

## 2. Daily Personal Operations Workflows

### 2.1 Morning Planning: My Day Cockpit (`/my-day`)
1. **Open My Day:** Navigate to `http://localhost:8000/my-day` (or click **My Day** in the left navigation).
2. **Smart Import:** Click **Import Due & Overdue Tasks** to pull tasks scheduled for today into the daily plan without creating duplicates.
3. **The Three-Priority Limit:**
   - Select up to **3 Top Priorities** for the day by starring or assigning Priority 1, 2, or 3.
   - The hub enforces a strict maximum of 3 top priorities on both backend and frontend to prevent cognitive overload.
4. **Time-Blocking:**
   - Enter estimated duration (e.g., `30m`, `1h`) and assign time slots (`HH:MM`).
   - The visual timeline renders chronological blocks with automatic end-time calculation.

### 2.2 Task Capture & Focus Execution
1. **Quick Capture:**
   - Press `Ctrl+K` to open the Command Palette, or click the **Quick Add** button in the top navigation.
   - Use natural language shorthand:
     * `Finish report @work !high due:today dur:45m`
     * `Buy groceries @errands due:tomorrow dur:20m`
2. **Focus / Pomodoro Timer:**
   - Start the **Mini Focus Timer** directly from the top navigation bar.
   - Attach the timer session to an active task.
   - Work in 25-minute focus intervals (or custom duration). When completed, the duration is logged to personal analytics.

### 2.3 Daily Phone Usage Tracking
- In `/my-day`, locate the **Phone Usage** widget in the right sidebar.
- Log your daily screen time in minutes (or view target minutes from settings).
- Compare your daily usage against your daily target threshold.

### 2.4 Evening Check-In
1. **Task Completion:** Check off completed tasks directly from My Day or the Kanban board.
2. **Rescheduling Incomplete Tasks:**
   - For tasks you did not finish, click the calendar/reschedule button and set a new date (e.g., tomorrow).
   - *Verified behavior (AUD-02):* Rescheduled tasks automatically detach from today's active plan without lingering.
3. **Habit Tracking (`/habits`):**
   - Record daily check-ins for positive habits (`completed` or `partial`).
   - Check in on break-habits (`/bad-habits`) to record clean days or log triggers.

### 2.5 Weekly Review (`/weekly-review`)
- Conduct your review at the end of the week (Sunday or Monday, based on your configured setting).
- Review tasks completed during the week, habit adherence rates, and weekly focus metrics.
- Fill out responses to your configured reflective review prompts.
- Review upcoming tasks for the next 7 calendar days (*verified behavior AUD-04: strictly next week, excluding the closing day of the current review week*).

---

## 3. Database Backup & Safe Verification Protocols

> [!CAUTION]
> **CRITICAL BACKUP SAFETY NOTICE:**  
> The command `php artisan ops:restore-database` is an active database **RESTORE** operation that replaces the live `database/database.sqlite` file.  
> **NEVER** run `ops:restore-database` as a routine check or verification test against your active personal hub database.

### 3.1 Creating a Routine Backup
Backups should be created before major updates, system maintenance, or periodically during dogfooding:

```powershell
# Method 1: Using the Artisan command directly:
php artisan ops:backup-database --trigger=manual

# Method 2: Using the PowerShell operational script:
.\scripts\backup-database.ps1

# Method 3: Using the launcher shortcut:
.\scripts\start.ps1 -Backup
```
- Snapshots are written to `storage/app/backups/`.
- The command automatically executes SQLite `PRAGMA integrity_check` on the snapshot immediately upon creation and generates a `.json` metadata sidecar with SHA-256 checksums and table counts.

### 3.2 Non-Destructive Integrity Verification
To verify the health and integrity of existing backups **without touching or modifying the active database**:

#### Option A: Inspect Backup Metadata via Artisan Status
```powershell
php artisan ops:status
```
Outputs the last backup timestamp, file size, integrity status, and age.

#### Option B: Non-Destructive SQLite CLI Pragma Check
```powershell
# Run PRAGMA integrity_check directly against the backup archive file:
sqlite3 "storage/app/backups/<backup-filename>.sqlite" "PRAGMA integrity_check;"
```
Expected output:
```text
ok
```

#### Option C: Non-Destructive Check via PHP Tinker
```powershell
php artisan tinker --execute '(new \App\Services\DatabaseBackupService)->verifySqliteIntegrity("storage/app/backups/<backup-filename>.sqlite");'
```
Expected output:
```text
"ok"
```

### 3.3 Safe Disaster Recovery Drill (Disposable Database Only)
If you wish to test database restore mechanics, **never test against your live database**. Perform drills against an isolated disposable target:

1. **Create a fresh test backup:**
   ```powershell
   php artisan ops:backup-database --trigger=manual
   ```
2. **Copy the backup to an isolated disposable directory:**
   ```powershell
   New-Item -ItemType Directory -Force -Path "storage/app/restore-drills"
   Copy-Item "storage/app/backups/<backup-filename>.sqlite" -Destination "storage/app/restore-drills/disposable-target.sqlite"
   ```
3. **Verify integrity and query records on the disposable copy:**
   ```powershell
   sqlite3 "storage/app/restore-drills/disposable-target.sqlite" "PRAGMA integrity_check; SELECT count(*) FROM tasks; SELECT count(*) FROM users;"
   ```
4. **Clean up the disposable drill folder:**
   ```powershell
   Remove-Item -Recurse -Force "storage/app/restore-drills"
   ```

---

## 4. Reusable Usability & Friction Ledger

Use the ledger below during your dogfooding period (7–14 days) to capture real-world friction, ergonomics issues, edge cases, and suggested improvements.

| Date | Workflow / Module | Expected Behavior | Actual Behavior | Friction or Problem | Severity (Low / Med / High) | Frequency (Once / Intermittent / Always) | Suggested Improvement | Status (Open / In Review / Resolved) |
|---|---|---|---|---|:---:|:---:|---|:---:|
| *YYYY-MM-DD* | *My Day* | *Example: Drag task to reorder timeline* | *Touch target small on tablet* | *Difficult to drag without opening edit modal* | *Low* | *Intermittent* | *Increase drag handle padding* | *Open* |
| | | | | | | | | |
| | | | | | | | | |
| | | | | | | | | |
| | | | | | | | | |
| | | | | | | | | |
| | | | | | | | | |

---

## 5. Dogfooding Acceptance Checklist

Complete this checklist at the end of your 7-day operational evaluation:

- [ ] Started and stopped the application reliably via `scripts/start.ps1` and `scripts/stop.ps1`.
- [ ] Created and managed daily tasks through My Day and Kanban views.
- [ ] Enforced the 3-priority constraint and verified focus alignment.
- [ ] Completed at least 5 Pomodoro focus sessions with task time tracking.
- [ ] Logged daily phone usage in My Day and verified target progress.
- [ ] Completed at least 7 consecutive days of habit check-ins.
- [ ] Completed at least one end-of-week Weekly Review.
- [ ] Verified category deletion modal shows explicit project unassignment notice (AUD-06).
- [ ] Verified mobile header displays cleanly on phone viewports without redundant QR icon (AUD-07).
- [ ] Created verified backups using `php artisan ops:backup-database` and verified non-destructive integrity.
- [ ] Recorded all ergonomic friction in the Usability Ledger above.
