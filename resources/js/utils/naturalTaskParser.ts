export interface ParsedTaskToken {
    kind: 'priority' | 'duration' | 'category' | 'project' | 'date' | 'time';
    label: string;
    raw: string;
}

/**
 * Client-side preview of natural-language shorthand tokens so Quick Add and
 * Command Palette can show live detected badges before submitting to the server.
 */
export function previewNaturalTaskTokens(input: string): {
    cleanTitle: string;
    tokens: ParsedTaskToken[];
} {
    let working = input.trim();
    const tokens: ParsedTaskToken[] = [];

    if (!working) {
        return { cleanTitle: '', tokens };
    }

    // Priority: !high, !urgent, !p1, !medium, !med, !p2, !low, !p3
    working = working.replace(/(?:^|\s)!(high|urgent|p1|medium|med|p2|low|p3)\b/gi, (_m, raw: string) => {
        const lower = raw.toLowerCase();
        const level = ['high', 'urgent', 'p1'].includes(lower)
            ? 'High'
            : ['medium', 'med', 'p2'].includes(lower)
              ? 'Medium'
              : 'Low';
        tokens.push({ kind: 'priority', label: `Priority: ${level}`, raw: `!${raw}` });
        return ' ';
    });

    // Duration: ~90m, ~45min, ~1.5h, ~2hr
    working = working.replace(
        /(?:^|\s)~(\d+(?:\.\d+)?)\s*(m|min|mins|minute|minutes|h|hr|hrs|hour|hours)\b/gi,
        (_m, num: string, unit: string) => {
            const val = parseFloat(num);
            const isHour = unit.toLowerCase().startsWith('h');
            const mins = Math.max(5, Math.round(isHour ? val * 60 : val));
            tokens.push({ kind: 'duration', label: `~${mins}m`, raw: `~${num}${unit}` });
            return ' ';
        }
    );

    // Category: #"Area Name" or #University
    working = working.replace(/(?:^|\s)#(?:"([^"]+)"|([A-Za-z0-9_-]+))/g, (_m, quoted?: string, single?: string) => {
        const name = (quoted || single || '').trim();
        if (name) {
            tokens.push({ kind: 'category', label: `#${name}`, raw: `#${name}` });
        }
        return ' ';
    });

    // Project: +"Project Name" or +Kernel
    working = working.replace(/(?:^|\s)\+(?:"([^"]+)"|([A-Za-z0-9_-]+))/g, (_m, quoted?: string, single?: string) => {
        const name = (quoted || single || '').trim();
        if (name) {
            tokens.push({ kind: 'project', label: `+${name}`, raw: `+${name}` });
        }
        return ' ';
    });

    // Date: today, tomorrow, tmrw, in N days, next weekday, weekday, YYYY-MM-DD
    working = working.replace(
        /(?:^|\s)(today|tomorrow|tmrw|in\s+\d+\s+days?|(?:next\s+)?(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|\d{4}-\d{2}-\d{2})\b/gi,
        (_m, rawDate: string) => {
            tokens.push({ kind: 'date', label: `Due: ${rawDate.trim()}`, raw: rawDate.trim() });
            return ' ';
        }
    );

    // Time: at 17:00, 17:00, 5pm, 5:30pm
    working = working.replace(
        /(?:^|\s)(?:at\s+)?((?:[01]?\d|2[0-3]):[0-5]\d(?:\s*[ap]m)?|(?:1[0-2]|0?[1-9])\s*[ap]m)\b/gi,
        (_m, rawTime: string) => {
            tokens.push({ kind: 'time', label: `At ${rawTime.trim()}`, raw: rawTime.trim() });
            return ' ';
        }
    );

    const cleanTitle = working.replace(/\s{2,}/g, ' ').trim();

    return { cleanTitle, tokens };
}

