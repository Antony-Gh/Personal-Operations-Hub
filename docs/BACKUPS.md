# Automated SQLite Backups & Safe Restoration Guide

Personal Operations Hub protects your local SQLite database (`database\database.sqlite`) with transaction-safe snapshots, `PRAGMA integrity_check` verification, SHA-256 metadata sidecars, retention pruning, and pre-restore safety backups.

---

## 1. Where Backups Are Stored

Managed SQLite snapshots are stored locally outside the public web root in:

```text
storage\app\backups\
```

Each backup creates two files:
- `ops-hub-backup-YYYY-MM-DD-HHMMSS-<trigger>-<id>.sqlite` — Verified SQLite 3 database snapshot.
- `ops-hub-backup-YYYY-MM-DD-HHMMSS-<trigger>-<id>.sqlite.json` — Metadata sidecar containing `created_at`, `size_bytes`, `sha256`, `integrity_status` (`ok`), `trigger` (`scheduled`, `manual`, or `pre_restore`), and `tables_count`.

Both `storage\app\backups\.gitignore` and `.htaccess` are automatically maintained so snapshots are never committed to Git or served publicly.

---

## 2. Automated Daily Backups & Retention

When background services are running (`scripts\start-background.ps1` or `start.ps1`):

1. Laravel Scheduler runs `php artisan ops:backup-database --if-needed` hourly.
2. If **Automated Daily Backups** is enabled in **Settings -> Automated Backups & Private Calendar Feed** and no verified backup exists for the current local calendar day, a transaction-safe snapshot is created using SQLite `VACUUM INTO` (with `SQLite3::backup` online backup API fallback).
3. Every snapshot is immediately verified via `PRAGMA integrity_check;`. Any corrupt file is discarded and logged.
4. Snapshots older than your configured **Retention Window** (default **14 days**) are automatically pruned.

---

## 3. Manual Backups (Web UI & CLI)

### From the Web Interface
1. Open **Settings -> Data Management & Backups** (`/settings/data`).
2. Click **Create Verified Backup Now** in the **Automated Local SQLite Snapshots** panel.
3. You can also download:
   - **Full JSON Archive** (`/settings/export/json`) — Portable cross-database archive (v2.0) including settings, categories, projects, milestones, tasks, checklists, tags, daily plans, habits, weekly reviews, phone usage, and focus sessions.
   - **Tasks CSV** (`/settings/export/tasks-csv`) — Spreadsheet export.
   - **Raw SQLite File** (`/settings/backup/database` or individual snapshot **Download** button).

### From PowerShell
```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Backup
# or directly via Artisan:
php artisan ops:backup-database --trigger=manual
```

---

## 4. Restoring from a Backup Safely

### Option A — In-App Safe Restore (Recommended)
1. Open **Settings -> Data Management & Backups** (`/settings/data`).
2. Locate the desired verified snapshot in the table and click **Restore**.
3. Type `RESTORE` (all caps) in the confirmation modal and confirm.
4. **What happens under the hood**:
   - Validates the backup file's SQLite 3 header and runs `PRAGMA integrity_check;`.
   - Automatically captures a fresh **`pre_restore`** snapshot of your current database before touching anything.
   - Performs an atomic SQLite online backup copy into `database\database.sqlite` and runs `php artisan migrate --force` so schema compatibility is guaranteed.

### Option B — Manual Offline File Restore (If the App Cannot Start)
1. Stop all local workers:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\stop.ps1
   ```
2. Copy your chosen `.sqlite` snapshot from `storage\app\backups\` over `database\database.sqlite`:
   ```powershell
   Copy-Item "storage\app\backups\ops-hub-backup-YYYY-MM-DD-HHMMSS-manual-xxxx.sqlite" "database\database.sqlite" -Force
   ```
3. Run safe migrations to ensure all latest tables/columns exist:
   ```powershell
   php artisan migrate --force
   ```
4. Start the hub again (`.\start.ps1` or `.\scripts\start-background.ps1`).

