# Local Alarms, Reminders & Notification System

Personal Operations Hub provides a multi-layer, 100% local notification and alarm system powered by SQLite, Laravel Database Notifications, the Web Notifications API, and offline Web Audio synthesis.

---

## 1. How Alarms & Reminders Work Locally

1. **Entity Synchronization (`ReminderService`)**:
   - **Tasks**: Supports `None`, `At due time`, `15m before`, `30m before`, `1h before`, `1d before`, `Custom datetime`, plus unlimited **additional reminder offsets** (`custom_reminder_times`) and automatic **Overdue** detection.
   - **Projects**: Automatic deadline countdown reminders (`7`, `3`, `1`, and `0` days before `target_date` by default, customizable per project or globally in Settings) plus active overdue project alerts.
   - **Milestones**: Automatic reminders (`3`, `1`, and `0` days before `due_date`) plus immediate milestone completion notifications.
   - Updating a due date/time automatically reconciles pending reminders; completing or deleting a task, project, or milestone cancels pending reminders immediately.

2. **Minute-by-Minute Evaluation (`ops:process-reminders`)**:
   - Runs every minute via `php artisan schedule:work`.
   - Uses database row locking and deterministic `dedup_key` guards so notifications are never duplicated.
   - Evaluates **Notification Category Preferences** and **Quiet Hours** in your configured IANA timezone (`Settings -> Localization & Timezone`).

3. **Real-Time Delivery in the UI**:
   - **In-App Notification Center**: Accessible from the top header bell icon with unread count badge, category filters, and pagination.
   - **Native Desktop Banners**: Uses the browser `Notification` API (enable in `Settings -> Notifications & Alarms -> Enable Browser Banners` and click **Send Test Alert**).
   - **Offline Synthesized Audio Chimes**: Uses the browser's native Web Audio API (`useNotificationSound.ts`) to synthesize `Gentle Chime`, `Warm Bell`, `Soft Ping`, or `Digital Alarm` locally with adjustable volume—zero external audio files or internet access required.

---

## 2. Actionable Notification Controls

From the **Notification Center** drawer, you can act on reminders immediately:

- **Snooze**: `15m`, `30m`, `1h`, or `Tomorrow 09:00` (in your local timezone).
- **Dismiss**: Permanently dismisses the reminder so it will not re-trigger.
- **Done (Complete Task)**: Marks the linked task complete directly from the notification card (and spawns the next recurring occurrence if applicable).
- **Mark Read / Mark All Read / Clear History**: Keep your notification inbox clean.

---

## 3. Quiet Hours & Critical Overrides

Configure Quiet Hours under **Settings -> Quiet Hours**:

- Standard reminders triggered during your Quiet Hours window (e.g., `22:00` to `07:00` local time, including overnight spans across midnight) are automatically deferred until Quiet Hours end (`07:00`).
- When **Allow Critical Reminders in Quiet Hours** is enabled, high-priority task alarms and due-today/overdue project/milestone alerts bypass Quiet Hours.

---

## 4. Morning Briefing & Weekly Digest

Configure automated summaries under **Settings -> Briefings & Default Reminders** or view them anytime at `/briefings`:

- **Morning Briefing** (default `07:30` local time): Summarizes today's tasks, top priorities, overdue items, upcoming 7-day deadlines, active projects, habit check-ins, calendar commitments, and scheduled alarms.
- **Weekly Digest** (default Sunday `18:00` local time): Summarizes tasks completed this week, logged Focus/Pomodoro minutes, habit consistency percentages, milestone completions, and key achievements.

