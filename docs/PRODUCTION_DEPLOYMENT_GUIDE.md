# Personal Operations Hub — Production Deployment Guide

This guide details the deployment methods, security hardening, and operational requirements for hosting **Personal Operations Hub** in production.

---

## 1. Hosting Methods Comparison

| Feature | Docker Compose + Caddy (Recommended) | Native Linux Systemd VPS | Cloud PaaS (Railway / Render / Fly) |
| :--- | :--- | :--- | :--- |
| **Complexity** | Low (Single compose file) | Medium (Manual package setup) | Low |
| **Reverse Proxy** | Caddy (Auto Let's Encrypt SSL) | Nginx + Certbot | Platform Edge Proxy |
| **Workers & Cron** | Isolated containers | Systemd services & timers | Procfile / Worker service |
| **Persistence** | Docker named volumes | Host filesystem | Persistent Volume Claim |
| **Cost** | Minimal (Any $5/mo VPS) | Minimal ($5/mo VPS) | $5 - $15/mo |
| **Maintenance** | Zero-dependency image updates | OS package upgrades | Platform managed |

---

## 2. Method 1: Self-Hosted Docker Compose with Caddy (Recommended)

### Prerequisites
- A Linux VPS (Ubuntu 22.04 / 24.04, Debian 12, etc.)
- A registered domain name (e.g. `operations.your-domain.com`) pointing its DNS `A` record to your server's public IP.
- Docker & Docker Compose plugin installed:
  ```bash
  curl -fsSL https://get.docker.com | sh
  sudo usermod -aG docker $USER
  ```

### Step 1: Clone the Repository
```bash
git clone <repository_url> /opt/personal-operations-hub
cd /opt/personal-operations-hub
```

### Step 2: Configure Environment Variables
Copy the production environment template:
```bash
cp .env.production.example .env
```

Generate a secure application key:
```bash
# Generate key locally or via temporary PHP container:
docker run --rm -v $(pwd):/app -w /app php:8.4-cli-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```
Paste this value into `.env` as `APP_KEY`.

Configure your domain name and email in `.env`:
```ini
APP_URL=https://operations.your-domain.com
POH_DOMAIN=operations.your-domain.com
ALLOW_REGISTRATION=false
SESSION_SECURE_COOKIE=true
```

### Step 3: Launch Containers
```bash
docker compose up -d --build
```

### Step 4: Verify Deployment
1. Check container health:
   ```bash
   docker compose ps
   ```
   All 4 containers (`poh_app`, `poh_queue`, `poh_scheduler`, `poh_caddy`) should report `Up (healthy)`.
2. Inspect Caddy certificate acquisition:
   ```bash
   docker compose logs caddy
   ```
3. Visit `https://operations.your-domain.com` in your browser. Caddy will have automatically obtained a valid TLS certificate from Let's Encrypt.

---

## 3. Method 2: Native Linux VPS with Systemd & Nginx

### Prerequisites
- Ubuntu 24.04 LTS VPS
- PHP 8.4-FPM and required extensions:
  ```bash
  sudo add-apt-repository ppa:ondrej/php -y
  sudo apt update
  sudo apt install -y php8.4-fpm php8.4-sqlite3 php8.4-mbstring php8.4-bcmath php8.4-zip php8.4-gd php8.4-curl nginx certbot python3-certbot-nginx
  ```

### Step 1: Deploy Application
```bash
sudo mkdir -p /var/www/html
sudo git clone <repository_url> /var/www/html
cd /var/www/html

composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.production.example .env
php artisan key:generate

# Set permissions
sudo chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache
sudo chmod -R 775 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache
```

### Step 2: Configure Nginx Virtual Host
```bash
sudo cp systemd/nginx-poh.conf /etc/nginx/sites-available/poh
sudo ln -s /etc/nginx/sites-available/poh /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 3: Install SSL Certificate via Certbot
```bash
sudo certbot --nginx -d operations.your-domain.com
```

### Step 4: Enable Systemd Queue Worker & Scheduler
```bash
sudo cp systemd/poh-queue.service /etc/systemd/system/
sudo cp systemd/poh-scheduler.service /etc/systemd/system/
sudo cp systemd/poh-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now poh-queue.service
sudo systemctl enable --now poh-scheduler.timer
```

Verify service status:
```bash
sudo systemctl status poh-queue.service
sudo systemctl list-timers | grep poh
```

---

## 4. Production Security Hardening Checklist

- [x] **Debug Mode Disabled:** `APP_DEBUG=false` in production to prevent leaking environment secrets and stack traces.
- [x] **Single-User Access Control:** `ALLOW_REGISTRATION=false` enforced server-side; blocks `/register` with `HTTP 403 Forbidden`.
- [x] **HTTPS Enforcement:** Reverse-proxy SSL headers trusted via `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.
- [x] **Secure Session Cookies:** `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true` ensure cookies cannot be intercepted over plain HTTP.
- [x] **SQLite Concurrency Settings:** `busy_timeout = 5000` and `synchronous = NORMAL` configured in `config/database.php`.
- [x] **Log Rotation:** `LOG_CHANNEL=daily` with `LOG_DAILY_DAYS=14` prevents logs from exhausting disk space.
- [x] **ICS Feed Rate Limiting:** Public calendar feeds rate-limited to 60 requests/min/IP (`throttle:calendar-feed`).
- [x] **Persistent Storage:** SQLite database file and backup directory mounted to persistent storage volumes.
