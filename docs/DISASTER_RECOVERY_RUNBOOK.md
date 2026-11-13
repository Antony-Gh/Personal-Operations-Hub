# Personal Operations Hub — Disaster Recovery Runbook

This runbook documents the disaster recovery architecture, automated backup mechanisms, encryption standards, and step-by-step procedures for restoring the application after data loss, hardware failure, or release regressions.

---

## 1. Disaster Recovery Architecture Overview

Personal Operations Hub implements a multi-tier disaster recovery architecture:

```
+-------------------------------------------------------------------------------------------------+
|                                    DISASTER RECOVERY LIFECYCLE                                  |
+-------------------------------------------------------------------------------------------------+
                                                 |
         +---------------------------------------+---------------------------------------+
         |                                       |                                       |
         v                                       v                                       v
+------------------------+              +------------------------+              +------------------------+
|   AUTOMATED SNAPSHOTS  |              |    ENCRYPTED MIRROR    |              |   PRE-RESTORE SAFETY   |
| - Hourly check         |              | - Secondary folder     |              | - Automatic snapshot   |
| - 24-hour cycle        |              | - AES-256-CBC bundle   |              | - Mandatory pre-check  |
| - SHA-256 metadata     |              | - HMAC-SHA256 authenticated           | - Automatic rollback   |
| - PRAGMA integrity ok  |              | - Key derivation: PBKDF2              | - Never corrupts active|
+------------------------+              +------------------------+              +------------------------+
```

### Key Safety Guarantees
1. **Zero Corrupted Restores:** Restoration validates the SQLite 3 binary header (`SQLite format 3\0`) and runs `PRAGMA integrity_check` on the backup file before touching the active database.
2. **Mandatory Safety Pre-Restore Snapshot:** A complete verified snapshot (`ops-hub-backup-*-pre_restore-*.sqlite`) is created automatically before any restore begins.
3. **Automatic Safety Rollback:** If the restore process throws an exception or the destination fails post-restore integrity checking, the active database is automatically rolled back to the pre-restore snapshot.
4. **Explicit Migration Control:** Database migrations are never silently forced upon restore; the user explicitly decides whether to run migrations (`--migrate` vs `--no-migrate`).
5. **Authenticated Encryption:** Encrypted archives (`.enc`) use `OPSHUBENC1` header, AES-256-CBC, PBKDF2-SHA256 (100,000 iterations), and HMAC-SHA256 authentication to detect tampering or incorrect passphrases before attempting decryption.

---

## 2. Backup Archive Formats & Locations

### Storage Locations
- **Primary Backups:** `storage/app/backups/`
- **Secondary Mirror (Optional):** User-configured local folder, external drive, or cloud mount (e.g. `D:\Backups\POH`).
- **Docker Persistent Volume:** `poh_backups` (`/var/www/html/storage/app/backups`).

### File Naming Convention
- **Raw SQLite Snapshot:** `ops-hub-backup-YYYY-MM-DD-HHmmss-{trigger}-{suffix}.sqlite`
- **Metadata Sidecar:** `ops-hub-backup-YYYY-MM-DD-HHmmss-{trigger}-{suffix}.sqlite.json`
- **Encrypted Snapshot:** `ops-hub-backup-YYYY-MM-DD-HHmmss-{trigger}-{suffix}.sqlite.enc`

### Trigger Types
- `scheduled`: Automated hourly/daily cron snapshot (eligible for retention pruning).
- `manual`: User-initiated backup from UI, CLI, or controller (preserved during pruning).
- `pre_restore`: Automatic snapshot taken immediately before a restore (preserved during pruning).
- `pre_fresh_wipe`: Automatic snapshot taken before `migrate:fresh` (preserved during pruning).

---

## 3. Step-by-Step Restoration Procedures

### Scenario A: Interactive Restore via Unified Artisan Command (Recommended)
Applicable to Windows, Linux, and Docker:

```bash
php artisan ops:restore-database
```

**Workflow:**
1. Displays an interactive table of all available backups sorted newest first.
2. Prompts you to enter the number of the desired backup.
3. If the backup is encrypted (`.enc`), prompts for your decryption passphrase securely.
4. Asks whether you want to run pending database migrations (`[y/N]`).
5. Requests final confirmation (`Type 'YES'`).
6. Creates the pre-restore safety snapshot, executes the online restore, and displays the verification report.

---

### Scenario B: Automated / Headless Restore (Scripts & CI/CD)
For automated recovery drills, scripts, or container init:

```bash
# Restore the most recent backup without interactive prompts:
php artisan ops:restore-database --latest --force --no-migrate

# Restore a specific backup archive:
php artisan ops:restore-database ops-hub-backup-2026-11-13-150005-scheduled-334a.sqlite --force --no-migrate

# Restore an encrypted backup:
php artisan ops:restore-database backup.enc --passphrase="MySecretPassword" --force --no-migrate
```

---

### Scenario C: Windows Controller Menu
1. Launch `controller.bat` (or `controller.ps1`).
2. Select **[16] Restore Database from Backup Archive**.
3. Choose the backup number from the displayed list.
4. The controller automatically delegates to `php artisan ops:restore-database`.

---

### Scenario D: Bare-Metal Recovery (Disaster from Scratch)
When restoring onto a completely clean machine:

1. **Clone the Repository & Install Dependencies:**
   ```bash
   git clone <repository_url> personal-operations-hub
   cd personal-operations-hub
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   cp .env.example .env
   php artisan key:generate
   ```

2. **Copy Backup Files:**
   Place your backup file (e.g. `ops-hub-backup-latest.sqlite`) into `storage/app/backups/`.

3. **Execute Restore:**
   ```bash
   php artisan ops:restore-database --latest --force --migrate
   ```

4. **Verify Database Health:**
   ```bash
   php -r "echo (new SQLite3('database/database.sqlite'))->querySingle('PRAGMA integrity_check;') . PHP_EOL;"
   # Expected output: ok
   php artisan ops:status
   ```

5. **Start Application:**
   ```bash
   # Windows:
   controller.bat
   # Linux:
   ./poh start
   # Docker:
   docker compose up -d
   ```

---

## 4. Manual Decryption Without Laravel

If you need to recover data from an encrypted `.enc` file in an emergency without Laravel:

### Method 1: Using the Decrypt Command
```bash
php artisan ops:decrypt-backup storage/app/backups/backup.sqlite.enc recovered.sqlite
```

### Method 2: Standalone PHP Script
```php
<?php
// emergency-decrypt.php
$encPath = $argv[1];
$outPath = $argv[2];
$password = $argv[3];

$bundle = file_get_contents($encPath);
$magic = substr($bundle, 0, 10);
$salt = substr($bundle, 10, 16);
$iv = substr($bundle, 26, 16);
$storedHmac = substr($bundle, 42, 32);
$ciphertext = substr($bundle, 74);

$keyMaterial = hash_pbkdf2('sha256', $password, $salt, 100000, 64, true);
$encKey = substr($keyMaterial, 0, 32);
$macKey = substr($keyMaterial, 32, 32);

$computedHmac = hash_hmac('sha256', $magic.$salt.$iv.$ciphertext, $macKey, true);
if (!hash_equals($storedHmac, $computedHmac)) {
    die("Authentication failed: Wrong password or corrupt file.\n");
}

$plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
file_put_contents($outPath, $plaintext);
echo "Successfully decrypted to $outPath!\n";
```

---

## 5. Verification Checklist After Restoration

After performing a database restore, verify the following:

- [ ] SQLite binary integrity check reports `ok`:
  ```bash
  php -r "echo (new SQLite3('database/database.sqlite'))->querySingle('PRAGMA integrity_check;') . PHP_EOL;"
  ```
- [ ] Operational summary reports expected counts:
  ```bash
  php artisan ops:status
  ```
- [ ] User authentication functions properly at `/login`.
- [ ] Pre-restore safety snapshot exists in `storage/app/backups/`.
- [ ] Background workers and scheduler resume without errors:
  ```bash
  tail -n 30 storage/logs/laravel.log
  ```
