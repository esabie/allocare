/** UK wall-clock helpers — schedules are locked to Europe/London for all users. */

export const UK_TIME_ZONE = 'Europe/London';

const weekDayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function pad2(value) {
    return String(value).padStart(2, '0');
}

/** Calendar YYYY-MM-DD in Europe/London for an instant. */
export function ukDateIso(value = new Date()) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: UK_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);

    const year = parts.find((part) => part.type === 'year')?.value;
    const month = parts.find((part) => part.type === 'month')?.value;
    const day = parts.find((part) => part.type === 'day')?.value;

    return `${year}-${month}-${day}`;
}

export function ukTodayIso() {
    return ukDateIso(new Date());
}

/** Stable Date standing in for a UK calendar day (noon UTC). */
export function ukDateFromIso(isoDate) {
    const [year, month, day] = String(isoDate || '').split('-').map(Number);
    if (!year || !month || !day) {
        return new Date(NaN);
    }

    return new Date(Date.UTC(year, month - 1, day, 12, 0, 0));
}

export function addUkDaysIso(isoDate, days) {
    const date = ukDateFromIso(isoDate);
    date.setUTCDate(date.getUTCDate() + Number(days || 0));
    return ukDateIso(date);
}

export function ukWeekdayIndex(isoDate) {
    const label = new Intl.DateTimeFormat('en-US', {
        timeZone: UK_TIME_ZONE,
        weekday: 'short',
    }).format(ukDateFromIso(isoDate));

    return weekDayLabels.indexOf(label);
}

export function startOfUkWeekIso(isoDate = ukTodayIso()) {
    const weekday = ukWeekdayIndex(isoDate);
    return addUkDaysIso(isoDate, weekday >= 0 ? -weekday : 0);
}

export function formatUkHeaderDate(isoDate) {
    const date = ukDateFromIso(isoDate);
    const day = Number(new Intl.DateTimeFormat('en-GB', {
        timeZone: UK_TIME_ZONE,
        day: 'numeric',
    }).format(date));
    const weekday = new Intl.DateTimeFormat('en-US', {
        timeZone: UK_TIME_ZONE,
        weekday: 'short',
    }).format(date);
    const mod100 = day % 100;
    const ordinal = (mod100 >= 11 && mod100 <= 13)
        ? 'th'
        : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');

    return `${weekday} ${day}${ordinal}`;
}

export function formatUkTime(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '--';
    }

    return date.toLocaleTimeString('en-GB', {
        timeZone: UK_TIME_ZONE,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

/** UK abbreviation for an instant — typically BST or GMT. */
export function ukTimeZoneAbbr(value = new Date()) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'UK';
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: UK_TIME_ZONE,
        timeZoneName: 'short',
    }).formatToParts(date);

    const name = parts.find((part) => part.type === 'timeZoneName')?.value?.trim();
    if (!name) {
        return 'UK';
    }

    // Some engines return "GMT+1" instead of "BST".
    if (name === 'GMT+1' || name === 'UTC+1') {
        return 'BST';
    }
    if (name === 'GMT+0' || name === 'UTC+0' || name === 'UTC') {
        return 'GMT';
    }

    return name;
}

export function formatUkTimeWithZone(value) {
    const time = formatUkTime(value);
    if (time === '--') {
        return time;
    }

    return `${time} ${ukTimeZoneAbbr(value)}`;
}

export function formatUkTimeRange(startIso, endIso, spansOvernight = false) {
    const start = startIso ? new Date(startIso) : null;
    const end = endIso ? new Date(endIso) : null;
    if (!start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return '--';
    }

    const overnight = spansOvernight || ukDateIso(start) !== ukDateIso(end);
    const zone = ukTimeZoneAbbr(start);
    const label = `${formatUkTime(start)} - ${formatUkTime(end)} ${zone}`;

    return overnight ? `${label} (overnight)` : label;
}

export function formatUkWeekRangeLabel(startIso, endIso) {
    const start = ukDateFromIso(startIso);
    const end = ukDateFromIso(endIso);
    const startLabel = start.toLocaleDateString('en-GB', {
        timeZone: UK_TIME_ZONE,
        weekday: 'short',
        day: 'numeric',
    });
    const endLabel = end.toLocaleDateString('en-GB', {
        timeZone: UK_TIME_ZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return `${startLabel} - ${endLabel}`;
}

export function formatUkDateLabel(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '--';
    }

    return date.toLocaleDateString('en-GB', {
        timeZone: UK_TIME_ZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export function ukHoursMinutes(value) {
    const formatted = formatUkTime(value);
    if (formatted === '--' || !formatted.includes(':')) {
        return { hours: 0, minutes: 0 };
    }

    const [hours, minutes] = formatted.split(':').map(Number);
    return { hours, minutes };
}

export { pad2, weekDayLabels };
