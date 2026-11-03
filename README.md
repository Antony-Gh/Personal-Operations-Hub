# Personal Operations Hub

A centralized, production-quality personal productivity web application built for a university student, software engineer, and team leader.

Rather than a generic to-do list or spreadsheet clone, the **Personal Operations Hub** is designed around low cognitive load, intentional daily planning, domain customizability, and sustainable, recovery-friendly routines.

---

## Tech Stack

- **Backend**: Laravel 11/12 (PHP 8.4)
- **Frontend**: Vue 3 (Composition API) + TypeScript
- **Integration**: Inertia.js (Seamless server-driven SPA without separate REST endpoints)
- **Styling**: Tailwind CSS
- **Icons**: Lucide Icons (`@lucide/vue`)
- **Database**: SQLite (out-of-the-box zero-configuration, migrations compatible with PostgreSQL/MySQL)
- **Testing**: Pest PHP & PHPUnit (48 automated tests with 122 assertions, 100% pass rate)
- **Asset Bundler**: Vite 8

---

## Core Features

1. **Personalized Dashboard**
   - **Today's Overview**: Instant glance at tasks due today, overdue items, completed tasks, and upcoming 7-day deadlines.
   - **Today's Priorities**: Up to three user-designated main focuses for high-impact execution.
   - **Areas & Projects Overview**: Real-time progress bars, active workloads, and stage metrics.
   - **Habits & Spiritual Life Widget**: Quick one-click check-ins for prayer, quiet time, and scripture reading.
   - **Phone Usage Widget**: Mindful leisure tracking against daily targets.

2. **My Day (Daily Planning)**
   - Date navigator (jump to any date, previous/next day, or return to today).
   - Separate planned tasks from general due tasks.
   - Select up to 3 main priorities with custom ordering.
   - Fixed commitments timeline (synced with calendar events).
   - Dedicated Fallback Plan (for overwhelmed days) & Personal Recovery notes.
   - Reschedule unfinished tasks cleanly without losing history.

3. **Comprehensive Task Management**
   - Title, description, category, project, priority (`high`, `medium`, `low`), status (`not_started`, `in_progress`, `waiting`, `completed`, `deferred`).
   - Due date, start date, estimated duration, actual duration, next action, and tags.
   - Interactive subtasks / checklist items.
   - Recurrence rules (`daily`, `weekly`, `monthly`).
   - Full-text search and multi-criteria filtering (status, area, project, priority, date windows).
   - Multi-task selection with bulk actions (bulk complete, bulk reopen, bulk delete).
   - Instant task duplication.

4. **Flexible Areas & Categories**
   - Dynamic, database-backed categories immediately accessible in all application dropdowns.
   - Custom colors and Lucide icons.
   - Drag/reorder priorities and archive/restore functionality.
   - **Safe Deletion Protocol**: Never deletes associated tasks silently—prompts to reassign tasks to another area, keep them unassigned, or delete them deliberately.

5. **Projects & Milestones**
   - Multiple views: Responsive Card Grid and Kanban Board (`Planned`, `In Progress`, `On Hold`, `Completed`).
   - Project detail page with progress metrics, notes, and task assignments.
   - Milestone stage gates with completion checkboxes and timeline view.

6. **Interactive Calendar**
   - Monthly grid view with month switching.
   - Aggregated items: Fixed calendar commitments, tasks with due dates, and project milestones.
   - Color-coded badges and direct event scheduling modal.
   - Filterable by Area and Project.

7. **Habits & Spiritual Life**
   - Pre-configured practices: **Prayer**, **Quiet Time**, **Bible Reading**, and customizable habits.
   - Three check-in states: `Completed`, `Partial`, and `Not Completed` with optional reflection notes.
   - Past 14-day visual consistency matrix.
   - **Guilt-free design**: Non-judgmental language, no streak-loss shaming, treating spiritual disciplines with grace rather than productivity scores.

8. **Weekly Review**
   - Guided reflection with customizable reflection prompts:
     1. *What are the three most meaningful things I accomplished?*
     2. *What was delayed, and why?*
     3. *What distracted me the most?*
     4. *Was my workload realistic?*
     5. *What can I delegate or postpone?*
     6. *What are the most important upcoming deadlines?*
     7. *What should I protect next week?*
     8. *What helped me recover energy?*
   - Automatic weekly metrics summary of completed tasks, overdue items, and upcoming deadlines.
   - Archive of past reviews for retrospective analysis.

9. **Mindful Phone Usage Tracker**
   - Manual daily logging of leisure phone screen time in minutes.
   - Daily and weekly target tracking against user-defined goals.

10. **Data Ownership & Backups**
    - **Full System Archive**: Complete JSON backup of user settings, categories, projects, tasks, checklists, habits, reviews, and calendar events.
    - **Tasks Spreadsheet**: Export tasks as CSV for Excel / Google Sheets.
    - **Raw Database Download**: Direct SQLite database file download for offline snapshots.
    - **Safe Import Workflow**: Upload backup JSON files, view an interactive content preview/audit, and select between **Merge Mode** (safe append) or **Replace Mode** (fresh restore).

---

## Local Development Setup

### 1. Prerequisites

- **PHP** >= 8.2 (PHP 8.4 recommended) with `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `intl`, `openssl`
- **Composer** >= 2.0
- **Node.js** >= 18.0 & **npm**

### 2. Installation Steps

Clone the repository and install dependencies:

```bash
# Clone the repository
git clone <repo-url> Personal-Operations-Hub
cd Personal-Operations-Hub

# Install PHP dependencies
composer install

# Install Frontend dependencies
npm install
```

### 3. Environment & Database Configuration

Copy the environment file and generate the application key:

```bash
# Prepare environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Configure SQLite and run migrations with seed data:

```bash
# Run migrations and seed editable sample data
php artisan migrate:fresh --seed
```

### 4. Starting the Application

#### Option A: One-Click Windows Launcher (Recommended)

Simply double-click or run the launcher script from the project root:

**Using Command Prompt / Batch:**
```cmd
start.bat
```

**Using PowerShell:**
```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1
```

**What the launcher automates:**
1. Validates CLI prerequisites (`php`, `composer`, `node`, `npm`).
2. Confirms `.env` and `database/database.sqlite` exist (generates key if needed).
3. Creates a database safety backup (`database/database.sqlite.bak`) prior to running migrations.
4. Executes safe, non-destructive migrations (`php artisan migrate --force`) to preserve existing user data.
5. Captures and records process IDs (PIDs) in `.run/` for targeted process management.
6. Polls both the Laravel backend (`http://127.0.0.1:8000`) and the Vite frontend compiler (`http://127.0.0.1:5173/@vite/client`) to ensure full styling and script readiness.
7. Automatically launches your default web browser directly to:
   👉 **`http://127.0.0.1:8000`**

**To stop all background services:**
`stop.bat` and `stop.ps1` read the tracked PIDs from `.run/` and cleanly terminate only the processes started by Personal Operations Hub, without terminating unrelated services.
```cmd
stop.bat
```
*Or in PowerShell:*
```powershell
powershell -ExecutionPolicy Bypass -File .\stop.ps1
```

---

#### Option B: Manual Two-Terminal Setup (Cross-Platform)

```bash
# Terminal 1: Frontend compiler
npm run dev

# Terminal 2: Backend server
php artisan serve
```

Access the application at: **`http://127.0.0.1:8000`**

---

## Default Seeded Credentials

The database comes pre-configured for an engineering student and team lead:

- **Email**: `user@operations-hub.local`
- **Password**: `password`

*(Registration is disabled by default for private installations. You can enable it by setting `ALLOW_REGISTRATION=true` in `.env` if desired.)*

---

## Automated Testing

Run the automated test suite (Pest PHP / PHPUnit):

```bash
php artisan test
```

**Result: 48 passed (122 assertions, 100% pass rate)**

For production deployment instructions and platform trade-offs (including why Vercel is not recommended for this monolithic architecture), refer to the comprehensive [Production Deployment Guide](DEPLOYMENT.md).

The test suite validates:
- Authentication, guest views, password resets, and private user scoping
- Cross-user data isolation policies preventing unauthorized record modification
- Category lifecycle & safe deletion reassignment
- Task management, subtask checklists, recurrence rules, duplication, and bulk operations
- Daily plan constraints (max 3 priorities) & personal recovery notes
- Project milestones & stage-gate transitions
- Habits check-ins and weekly reflection note persistence
- JSON / CSV export and JSON backup schema validation

---

## System Audit & Enhancement Record

The following architectural, design, and security improvements were implemented:

1. **Branded Authentication Suite**
   - **Modern Aesthetic**: Replaced generic starter views with a calm slate and deep navy workspace theme featuring accessible typography and consistent dark/light mode support.
   - **Custom Application Emblem**: Designed an SVG geometric hub mark (`ApplicationLogo.vue`) shared across guest cards, login views, and responsive sidebars.
   - **Enhanced Credentials Form**: Built accessible input labels, show/hide password toggle controls, keyboard tab flows, visible focus indicators, and loading/disabled states.
   - **Clean UX**: Removed placeholder social-auth links and non-functional buttons; conditionally hides registration links when registration is disabled (`canRegister`).
   - **Branded Welcome Experience**: Replaced the 376-line default Laravel welcome view with a modern overview of the Personal Operations Hub workflow.

2. **Security & Data Integrity**
   - **Strict Multi-Tenant Scoping**: Configured `AuthorizesRequests` and Eloquent policies (`CategoryPolicy`, `TaskPolicy`, `HabitPolicy`, `ProjectPolicy`, `MilestonePolicy`, `ReviewPolicy`) ensuring all queries strictly scope to the authenticated user.
   - **Non-Destructive Operations**: Category deletion enforces task reassignment or unassignment rather than cascading silent data loss.
   - **Preserved Database Records**: All updates avoid destructive commands (`migrate:fresh`) to safeguard persisted user data.

3. **Type Safety & Reliability**
   - **TypeScript Strictness**: Resolved TS typing warnings in `Dashboard.vue` and `MyDay/Index.vue`.
   - **Pivot Integrity**: Corrected explicit `task_tag` pivot declarations in `Task` and `Tag` models.
   - **In-Memory Testing Support**: Guarded registration gating inside controller actions rather than route definition callbacks, allowing tests to run cleanly in isolated `:memory:` databases.
   - **Zero Build Warnings**: `npm run build` (`vue-tsc && vite build`) compiles all 2,543 modules with 0 errors.

## Production Deployment & Backups

### 1. Production Build

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Database Backup & Restore

- **Backup**:
  - Download JSON or SQLite backup directly from the web interface at `/settings/data`.
  - Alternatively, copy `database/database.sqlite` to secure cold storage.
- **Restore**:
  - Upload the JSON backup file via `/settings/data` and select "Merge" or "Replace".
  - Or place the backup `.sqlite` file into `database/database.sqlite` and run `php artisan migrate`.

### 3. PostgreSQL / MySQL Migration

To switch to PostgreSQL or MySQL for cloud deployment:

1. Update `DB_CONNECTION=pgsql` or `DB_CONNECTION=mysql` in `.env`.
2. Provide host, port, database, username, and password.
3. Run `php artisan migrate --seed`.

---

## License

Open-source personal software under the [MIT License](LICENSE).
