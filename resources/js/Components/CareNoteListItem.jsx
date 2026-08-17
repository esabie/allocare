import { careNoteListPreview } from '@/data/dailySupportCareLog';

export default function CareNoteListItem({ entry, onSelect, context = 'global' }) {
    const isPatientContext = context === 'patient';
    const title = isPatientContext
        ? (entry.templateLabel || entry.recordedAtLabel || 'Care note')
        : (entry.patient?.name || 'Unknown patient');
    const subtitle = isPatientContext && entry.templateLabel
        ? `Recorded by ${entry.author?.name || 'Unknown staff'} · ${entry.recordedAtLabel || ''}`
        : `Recorded by ${entry.author?.name || 'Unknown staff'}`;

    return (
        <li>
            <button
                type="button"
                onClick={() => onSelect(entry)}
                className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-slate-300 hover:shadow-md"
            >
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="font-semibold text-slate-800">{title}</p>
                        <p className="text-xs text-slate-500">{subtitle}</p>
                    </div>
                    {!isPatientContext && (
                        <time
                            dateTime={entry.recordedAt}
                            className="text-xs font-medium text-slate-500"
                        >
                            {entry.recordedAtLabel}
                        </time>
                    )}
                    {isPatientContext && entry.shiftTypeLabel && (
                        <span
                            className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                                entry.shiftType === 'night'
                                    ? 'bg-indigo-100 text-indigo-800'
                                    : 'bg-amber-100 text-amber-800'
                            }`}
                        >
                            {entry.shiftTypeLabel}
                        </span>
                    )}
                </div>

                <p className="line-clamp-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                    {careNoteListPreview(entry)}
                </p>
            </button>
        </li>
    );
}
