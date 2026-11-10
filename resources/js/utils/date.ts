/**
 * Centralized timezone-safe local date & time helpers.
 * Never use `new Date().toISOString().split('T')[0]` for local calendar dates.
 */

export function getLocalDateString(timezone?: string | null, date: Date = new Date()): string {
    if (timezone) {
        try {
            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });
            const parts = formatter.formatToParts(date);
            const year = parts.find((p) => p.type === 'year')?.value;
            const month = parts.find((p) => p.type === 'month')?.value;
            const day = parts.find((p) => p.type === 'day')?.value;
            if (year && month && day) {
                return `${year}-${month}-${day}`;
            }
        } catch {
            // Fall through to browser local date components
        }
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function getLocalDateTimeInputValue(timezone?: string | null, date: Date = new Date()): string {
    const datePart = getLocalDateString(timezone, date);
    if (timezone) {
        try {
            const formatter = new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });
            const hm = formatter.format(date);
            if (/^\d{2}:\d{2}$/.test(hm)) {
                return `${datePart}T${hm}`;
            }
        } catch {
            // Fall through
        }
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const mins = String(date.getMinutes()).padStart(2, '0');
    return `${datePart}T${hours}:${mins}`;
}

export function addDaysToLocalDate(dateStr: string, days: number): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    const utcAnchor = new Date(Date.UTC(year, (month || 1) - 1, (day || 1) + days));
    const y = utcAnchor.getUTCFullYear();
    const m = String(utcAnchor.getUTCMonth() + 1).padStart(2, '0');
    const d = String(utcAnchor.getUTCDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function isOverdueLocal(
    dueDateStr: string | null | undefined,
    status?: string | null,
    timezone?: string | null
): boolean {
    if (!dueDateStr || status === 'completed') {
        return false;
    }
    return dueDateStr < getLocalDateString(timezone);
}

export function formatRelativeTime(isoString: string | null | undefined): string {
    if (!isoString) {
        return '';
    }
    const target = new Date(isoString).getTime();
    if (Number.isNaN(target)) {
        return '';
    }
    const diffSec = Math.round((Date.now() - target) / 1000);
    if (diffSec < 45) {
        return 'Just now';
    }
    if (diffSec < 3600) {
        return `${Math.floor(diffSec / 60)}m ago`;
    }
    if (diffSec < 86400) {
        return `${Math.floor(diffSec / 3600)}h ago`;
    }
    if (diffSec < 604800) {
        return `${Math.floor(diffSec / 86400)}d ago`;
    }
    return new Date(isoString).toLocaleDateString();
}

/**
 * Format an HH:mm(:ss) or existing time string to 12-hour format with AM/PM (e.g., "2:30 PM", "9:05 AM").
 */
export function formatTime12Hour(timeStr?: string | null): string {
    if (!timeStr) {
        return '';
    }
    const clean = String(timeStr).trim();
    if (!clean) {
        return '';
    }

    // Already 12-hour AM/PM format (e.g., "2:30 PM" or "02:30 pm")
    const ampmMatch = clean.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(am|pm)$/i);
    if (ampmMatch) {
        const hour = parseInt(ampmMatch[1], 10);
        const minute = ampmMatch[2];
        const period = ampmMatch[3].toUpperCase();
        return `${hour}:${minute} ${period}`;
    }

    // Standard 24-hour time "HH:mm" or "HH:mm:ss"
    const match = clean.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (!match) {
        return clean;
    }

    const hour = parseInt(match[1], 10);
    const minute = match[2];
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;

    return `${hour12}:${minute} ${period}`;
}

export const DAY_NAMES_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;
export const DAY_NAMES_FULL = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as const;

export interface WeekDayOption {
    dayIndex: number;
    dayName: string;
    fullName: string;
    dayNumber: string;
    dateStr: string;
    formatted: string;
    isToday: boolean;
}

/**
 * Returns the 3-letter abbreviated day of week for a YYYY-MM-DD date string (e.g. "Tue").
 */
export function getDayOfWeekName(dateStr?: string | null): string {
    if (!dateStr) return '';
    const clean = String(dateStr).trim().slice(0, 10);
    const parts = clean.split('-').map(Number);
    if (parts.length < 3 || parts.some(isNaN)) return '';
    const [year, month, day] = parts;
    const anchor = new Date(Date.UTC(year, (month || 1) - 1, day || 1));
    return DAY_NAMES_SHORT[anchor.getUTCDay()] || '';
}

/**
 * Format a YYYY-MM-DD date with 3-letter day of week (e.g., "Tue, 2026-11-10").
 */
export function formatDateWithDay(dateStr?: string | null): string {
    if (!dateStr) return '';
    const clean = String(dateStr).trim();
    if (!clean) return '';
    if (/^[A-Za-z]{3},\s/.test(clean)) return clean;
    const day = getDayOfWeekName(clean);
    return day ? `${day}, ${clean}` : clean;
}

/**
 * Calculate the date (YYYY-MM-DD) for a chosen day of week in the current week.
 */
export function getDateForDayInCurrentWeek(
    targetDay: number | string,
    todayDateStr?: string | null,
    weekStart: number = 1
): string {
    const baseDate = todayDateStr || getLocalDateString();
    const [year, month, day] = baseDate.split('-').map(Number);
    const anchor = new Date(Date.UTC(year, (month || 1) - 1, day || 1));
    const currentDayOfWeek = anchor.getUTCDay();

    let targetIndex: number;
    if (typeof targetDay === 'string') {
        const lower = targetDay.toLowerCase().slice(0, 3);
        const map: Record<string, number> = {
            sun: 0,
            mon: 1,
            tue: 2,
            wed: 3,
            thu: 4,
            fri: 5,
            sat: 6,
        };
        targetIndex = map[lower] ?? 1;
    } else {
        targetIndex = ((targetDay % 7) + 7) % 7;
    }

    const start = weekStart === 0 ? 0 : 1;
    const currentOffsetFromStart = (currentDayOfWeek - start + 7) % 7;
    const targetOffsetFromStart = (targetIndex - start + 7) % 7;
    const diff = targetOffsetFromStart - currentOffsetFromStart;

    return addDaysToLocalDate(baseDate, diff);
}

/**
 * Returns all 7 days of the current week with dates, day numbers, names, and today status.
 */
export function getCurrentWeekDays(
    todayDateStr?: string | null,
    weekStart: number = 1
): WeekDayOption[] {
    const baseDate = todayDateStr || getLocalDateString();
    const [year, month, day] = baseDate.split('-').map(Number);
    const anchor = new Date(Date.UTC(year, (month || 1) - 1, day || 1));
    const currentDayOfWeek = anchor.getUTCDay();

    const start = weekStart === 0 ? 0 : 1;
    const currentOffsetFromStart = (currentDayOfWeek - start + 7) % 7;
    const weekStartDate = addDaysToLocalDate(baseDate, -currentOffsetFromStart);

    const result: WeekDayOption[] = [];
    for (let i = 0; i < 7; i++) {
        const dateStr = addDaysToLocalDate(weekStartDate, i);
        const [y, m, d] = dateStr.split('-').map(Number);
        const dAnchor = new Date(Date.UTC(y, (m || 1) - 1, d || 1));
        const dayIdx = dAnchor.getUTCDay();

        result.push({
            dayIndex: dayIdx,
            dayName: DAY_NAMES_SHORT[dayIdx],
            fullName: DAY_NAMES_FULL[dayIdx],
            dayNumber: String(d),
            dateStr,
            formatted: `${DAY_NAMES_SHORT[dayIdx]}, ${dateStr}`,
            isToday: dateStr === baseDate,
        });
    }

    return result;
}



