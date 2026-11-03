export interface UserSetting {
    id: number;
    user_id: number;
    display_name: string | null;
    theme: 'light' | 'dark' | 'system';
    language: string;
    week_start: number; // 0 = Sunday, 1 = Monday
    date_format: string;
    time_format: string;
    default_priority: 'high' | 'medium' | 'low';
    default_task_duration: number;
    phone_usage_target_minutes: number;
    weekly_review_prompts: string[];
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
    start_date: string | null;
    estimated_duration: number | null;
    actual_duration: number | null;
    notes: string | null;
    next_action: string | null;
    sort_order: number;
    is_recurring: boolean;
    recurrence_rule: string | null;
    completed_at: string | null;
    category?: Category | null;
    project?: Project | null;
    tags?: Tag[];
    checklists?: TaskChecklist[];
    is_priority?: boolean;
    plan_task_id?: number;
    plan_sort_order?: number;
}

export interface ProjectMilestone {
    id: number;
    project_id: number;
    title: string;
    due_date: string | null;
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
    type?: 'event' | 'task' | 'milestone';
    title: string;
    description?: string | null;
    start_time?: string;
    end_time?: string;
    date?: string | null;
    all_day?: boolean;
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
    history?: {
        date: string;
        status: 'completed' | 'partial' | 'not_completed' | 'none';
        notes: string | null;
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

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    categories: Category[];
    tags: Tag[];
    flash: {
        success: string | null;
        error: string | null;
    };
};
