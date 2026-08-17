export function emptySlotRows(slots = []) {
    return Object.fromEntries(
        slots.map((slot) => [slot.key, { initials: '', notes: '' }]),
    );
}

export function mergeSlotRows(existing = {}, slots = []) {
    const base = emptySlotRows(slots);
    for (const slot of slots) {
        const row = existing[slot.key];
        if (row && typeof row === 'object') {
            base[slot.key] = {
                initials: row.initials ? String(row.initials) : '',
                notes: row.notes ? String(row.notes) : '',
            };
        }
    }
    return base;
}

export function activeShiftHasEntries(slots = [], slotRows = {}, lockedSlotKeys = []) {
    const locked = new Set(lockedSlotKeys);

    return slots.some((slot) => {
        if (locked.has(slot.key)) {
            return false;
        }

        const row = slotRows[slot.key] || {};
        return String(row.notes || '').trim() !== '';
    });
}

export function careNoteListPreview(entry) {
    if (!entry) {
        return '';
    }

    if (!entry.isStructured) {
        return String(entry.body || '').trim();
    }

    const metaLabels = new Set(['Date', 'Shift', 'Incident', 'Shift comments']);
    const firstSlot = (entry.structuredSummary || []).find(
        (row) => row?.label && !metaLabels.has(row.label) && String(row.value || '').trim() !== '',
    );

    if (firstSlot) {
        return String(firstSlot.value).trim();
    }

    const shiftComments = (entry.structuredSummary || []).find((row) => row.label === 'Shift comments');
    if (shiftComments?.value) {
        return String(shiftComments.value).trim();
    }

    const shiftLabel = entry.shiftTypeLabel ? `${entry.shiftTypeLabel} shift` : 'Care log';
    return entry.templateLabel ? `${entry.templateLabel} — ${shiftLabel}` : shiftLabel;
}

export function buildInitialFormState(dailySupportCareLog, defaultShiftType) {
    const daySlots = dailySupportCareLog?.daySlots || [];
    const nightSlots = dailySupportCareLog?.nightSlots || [];

    return {
        patient_id: '',
        shift_type: defaultShiftType,
        log_date: new Date().toISOString().slice(0, 10),
        structured_data: {
            slots: {
                ...emptySlotRows(daySlots),
                ...emptySlotRows(nightSlots),
            },
            incident: '',
            shift_comments: '',
        },
        filter: 'all',
    };
}
