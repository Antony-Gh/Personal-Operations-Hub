export type NotificationCategory =
    | 'task_reminders'
    | 'overdue_tasks'
    | 'project_deadlines'
    | 'milestone_deadlines'
    | 'habit_reminders'
    | 'focus_timer'
    | 'daily_briefing'
    | 'weekly_digest'
    | 'system_notifications';

export type NotificationSoundPreset = 'chime' | 'bell' | 'soft_ping' | 'digital_alarm';

export type TaskReminderPreset = 'none' | 'at_due' | '15m' | '30m' | '1h' | '1d' | 'custom';

export interface UserSetting {
    id: number;
    user_id: number;
    display_name: string | null;
    theme: 'light' | 'dark' | 'system';
    language: string;
    timezone: string;
    week_start: number; // 0 = Sunday, 1 = Monday
    date_format: string;
    time_format: string;
    default_priority: 'high' | 'medium' | 'low';
    default_task_duration: number;
    phone_usage_target_minutes: number;
    weekly_review_prompts: string[];
    quiet_hours_enabled: boolean;
    quiet_hours_start: string;
    quiet_hours_end: string;
    allow_critical_in_quiet_hours: boolean;
    notifications_enabled: boolean;
    browser_notifications_enabled: boolean;
    windows_toast_enabled?: boolean;
    notification_sound_enabled: boolean;
    notification_sound: NotificationSoundPreset;
    notification_volume: number;
    notification_preferences: Record<NotificationCategory, boolean>;
    daily_briefing_enabled: boolean;
    daily_briefing_time: string;
    last_daily_briefing_at?: string | null;
    evening_checkin_nudge_enabled?: boolean;
    evening_checkin_nudge_time?: string;
    last_evening_nudge_at?: string | null;
    weekly_digest_enabled: boolean;
    weekly_digest_day: number;
    weekly_digest_time: string;
    last_weekly_digest_at?: string | null;
    default_task_reminder_preset: TaskReminderPreset;
    project_reminder_days: number[];
    backup_enabled: boolean;
    backup_retention_days: number;
    secondary_backup_enabled?: boolean;
    secondary_backup_path?: string | null;
    backup_encryption_enabled?: boolean;
    has_backup_encryption_password?: boolean;
    focus_work_minutes: number;
    focus_short_break_minutes: number;
    focus_long_break_minutes: number;
    focus_auto_start_breaks: boolean;
    calendar_feed_enabled: boolean;
    has_calendar_feed_token?: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    setting?: UserSetting;
}

export interface Category {
    id: number;
    user_id: number;
    name: string;
    color: string;
    icon: string;
    sort_order: number;
    is_archived: boolean;
    tasks_count?: number;
    projects_count?: number;
    active_tasks_count?: number;
    completed_tasks_count?: number;
    upcoming_deadlines_count?: number;
    progress_percent?: number;
    created_at?: string;
    updated_at?: string;
}

export interface Tag {
    id: number;
    user_id: number;
    name: string;
    color: string;
}

export interface TaskChecklist {
    id: number;
    task_id: number;
    title: string;
    is_completed: boolean;
    sort_order: number;
}

export interface Task {
    id: number;
    user_id: number;
    category_id: number | null;
    project_id: number | null;
    title: string;
    description: string | null;
    priority: 'high' | 'medium' | 'low';
    status: 'not_started' | 'in_progress' | 'waiting' | 'completed' | 'deferred';
    due_date: string | null;
    due_time?: string | null;
    formatted_due_time?: string | null;
    formatted_due_date?: string | null;
    reminder_preset?: TaskReminderPreset | null;
    remind_at?: string | null;
    custom_reminder_times?: string[] | null;
    start_date: string | null;
    estimated_duration: number | null;
    actual_duration: number | null;
    notes: string | null;
    next_action: string | null;
    sort_order: number;
    is_recurring: boolean;
    recurrence_rule: string | null;
    recurrence_interval?: number;
    recurrence_end_type?: 'never' | 'on_date' | 'after_occurrences';
    recurrence_end_date?: string | null;
    recurrence_max_occurrences?: number | null;
    recurrence_occurrences_count?: number;
    recurrence_parent_id?: number | null;
    completed_at: string | null;
    category?: Category | null;
    project?: Project | null;
    tags?: Tag[];
    checklists?: TaskChecklist[];
    blocked_by_tasks?: {
        id: number;
        title: string;
        status: Task['status'];
        priority: Task['priority'];
    }[];
    is_priority?: boolean;
    plan_task_id?: number;
    plan_sort_order?: number;
    scheduled_start_time?: string | null;
    scheduled_end_time?: string | null;
}

export interface ProjectMilestone {
    id: number;
    project_id: number;
    title: string;
    due_date: string | null;
    due_time?: string | null;
    formatted_due_time?: string | null;
    formatted_due_date?: string | null;
    reminders_enabled?: boolean;
    reminder_offsets?: number[] | null;
    is_completed: boolean;
    completed_at: string | null;
    sort_order: number;
}

export interface Project {
    id: number;
    user_id: number;
    category_id: number | null;
    name: string;
    description: string | null;
    status: 'planned' | 'in_progress' | 'on_hold' | 'completed' | 'archived';
    start_date: string | null;
    target_date: string | null;
    reminders_enabled?: boolean;
    reminder_days?: number[] | null;
    overdue_reminders_enabled?: boolean;
    notes: string | null;
    category?: Category | null;
    milestones?: ProjectMilestone[];
    tasks?: Task[];
    total_tasks_count?: number;
    completed_tasks_count?: number;
    progress_percent?: number;
}

export interface CalendarEvent {
    id: string | number;
    real_id?: number;
    type?: 'event' | 'task' | 'milestone' | 'project';
    title: string;
    description?: string | null;
    start_time?: string;
    end_time?: string;
    local_start_hm?: string | null;
    local_end_hm?: string | null;
    date?: string | null;
    due_time?: string | null;
    all_day?: boolean;
    reminder_minutes?: number | null;
    location?: string | null;
    color?: string | null;
    priority?: string;
    status?: string;
    is_completed?: boolean;
}

export interface Habit {
    id: number;
    user_id?: number;
    name: string;
    description: string | null;
    category: string;
    frequency: 'daily' | 'weekly';
    target_days_per_week: number;
    today_status?: 'completed' | 'partial' | 'not_completed' | 'none' | 'pending';
    today_notes?: string | null;
    completed_in_7_days?: number;
    current_streak?: number;
    best_streak?: number;
    total_completed_days?: number;
    weekly_consistency?: number;
    monthly_consistency?: number;
    completion_percentage?: number;
    history?: {
        date: string;
        status: 'completed' | 'partial' | 'not_completed' | 'none';
        notes: string | null;
    }[];
}

export interface BadHabitReflection {
    date: string;
    status: 'clean' | 'minor_slip' | 'slipped';
    observed_value: number | null;
    trigger_note: string | null;
    notes: string | null;
}

export interface BadHabit {
    id: number;
    user_id?: number;
    name: string;
    description: string | null;
    category: string;
    trigger_cue: string | null;
    replacement_action: string | null;
    linked_habit_id?: number | null;
    linked_habit?: {
        id: number;
        name: string;
        category: string;
        today_status: string;
    } | null;
    minutes_saved_per_day?: number;
    money_saved_per_day?: number;
    minutes_reclaimed?: number;
    money_reclaimed?: number;
    has_daily_limit: boolean;
    daily_limit_value: number | null;
    daily_limit_unit: 'times' | 'minutes';
    target_clean_days_per_week: number;
    today_status?: 'clean' | 'minor_slip' | 'slipped' | 'none' | 'pending';
    today_observed_value?: number | null;
    today_trigger_note?: string | null;
    today_notes?: string | null;
    current_clean_streak?: number;
    best_clean_streak?: number;
    total_clean_days?: number;
    minor_slips_count?: number;
    slips_count?: number;
    clean_in_7_days?: number;
    weekly_clean_rate?: number;
    monthly_clean_rate?: number;
    overall_clean_percentage?: number;
    recent_reflections?: BadHabitReflection[];
    history?: {
        date: string;
        status: 'clean' | 'minor_slip' | 'slipped' | 'none';
        observed_value: number | null;
        trigger_note: string | null;
        notes: string | null;
    }[];
}

export interface HabitHeatmapDay {
    date: string;
    day_number: number;
    is_current_month: boolean;
    is_today: boolean;
    is_future: boolean;
    completed_count: number;
    partial_count: number;
    total_habits: number;
    ratio: number;
    level: number;
}

export interface HabitHeatmap {
    month: string;
    month_label: string;
    prev_month: string;
    next_month: string;
    week_start: number;
    days: HabitHeatmapDay[];
    monthly_summary: {
        month: string;
        label: string;
        active_days: number;
        days_in_span: number;
        completed_checkins: number;
        consistency_percent: number;
    }[];
}

export interface DailyPlan {
    id: number;
    user_id: number;
    date: string;
    fallback_plan: string | null;
    personal_recovery_notes: string | null;
    notes: string | null;
}

export interface WeeklyReview {
    id: number;
    user_id: number;
    week_start_date: string;
    week_end_date: string;
    responses: Record<string, string> | null;
    notes: string | null;
    is_completed: boolean;
}

export interface PhoneUsageLog {
    id: number;
    user_id: number;
    date: string;
    minutes: number;
    notes: string | null;
}

export interface AppNotificationPayload {
    category: NotificationCategory;
    title: string;
    message: string;
    action_url?: string;
    entity_type?: string | null;
    entity_id?: number | null;
    reminder_id?: number | null;
    is_critical?: boolean;
    dedup_key?: string | null;
    meta?: Record<string, unknown>;
}

export interface AppNotification {
    id: string;
    data: AppNotificationPayload;
    read_at: string | null;
    created_at: string | null;
}

export interface ActiveFocusSession {
    id: number;
    task_id: number | null;
    task: {
        id: number;
        title: string;
        estimated_duration: number | null;
        actual_duration: number | null;
    } | null;
    mode: 'focus' | 'short_break' | 'long_break';
    planned_minutes: number;
    elapsed_seconds: number;
    remaining_seconds: number;
    logged_minutes: number;
    status: 'running' | 'paused' | 'completed' | 'stopped';
    started_at: string | null;
    last_resumed_at: string | null;
    server_now_ms: number;
}

export interface BackupEntry {
    filename: string;
    created_at: string;
    size_bytes: number;
    size_human: string;
    sha256: string;
    integrity_status: string;
    trigger: string;
    tables_count: number | null;
    mirrored_to?: string | null;
    encrypted_mirror?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    categories: Category[];
    tags: Tag[];
    notificationsSummary?: {
        unread_count: number;
        items: AppNotification[];
    };
    activeFocusSession?: ActiveFocusSession | null;
    flash: {
        success: string | null;
        error: string | null;
    };
};
