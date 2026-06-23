function canonicalActionLabel(action) {
    const labels = {
        create: 'Create',
        read: 'Read',
        update: 'Update',
        delete_attempt: 'Delete attempt',
    };

    return labels[action] || (action ? action.replace(/_/g, ' ') : '—');
}

function deviceLabel(deviceType) {
    if (!deviceType || deviceType === 'unknown' || deviceType === 'Unknown') {
        return '—';
    }

    const legacy = {
        mobile: 'Mobile',
        tablet: 'Tablet',
        desktop: 'Desktop',
    };

    return legacy[deviceType] || deviceType;
}

function cellValue(value) {
    return value && String(value).trim() !== '' ? value : '—';
}

const COLUMN_COUNT = 12;

export default function AuditEventTable({ events = [], emptyMessage = 'No activity recorded yet.' }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[1400px] border-collapse text-left text-sm">
                <thead className="bg-slate-50">
                    <tr>
                        <th className="border border-slate-200 px-3 py-2">When (UTC)</th>
                        <th className="border border-slate-200 px-3 py-2">User ID</th>
                        <th className="border border-slate-200 px-3 py-2">User name</th>
                        <th className="border border-slate-200 px-3 py-2">Action</th>
                        <th className="border border-slate-200 px-3 py-2">Record type</th>
                        <th className="border border-slate-200 px-3 py-2">Record ID</th>
                        <th className="border border-slate-200 px-3 py-2">Description</th>
                        <th className="border border-slate-200 px-3 py-2">Previous value</th>
                        <th className="border border-slate-200 px-3 py-2">New value</th>
                        <th className="border border-slate-200 px-3 py-2">Device</th>
                        <th className="border border-slate-200 px-3 py-2">IP address</th>
                        <th className="border border-slate-200 px-3 py-2">Session ID</th>
                    </tr>
                </thead>
                <tbody>
                    {events.length === 0 ? (
                        <tr>
                            <td colSpan={COLUMN_COUNT} className="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        events.map((event) => (
                            <tr key={event.id} className="odd:bg-white even:bg-slate-50/30">
                                <td className="whitespace-nowrap border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {cellValue(event.occurred_at_label || event.created_at)}
                                </td>
                                <td className="whitespace-nowrap border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {event.user_id ?? '—'}
                                </td>
                                <td className="border border-slate-200 px-3 py-2">
                                    {cellValue(event.user_name)}
                                </td>
                                <td className="border border-slate-200 px-3 py-2 text-xs font-medium capitalize text-slate-800">
                                    {canonicalActionLabel(event.action)}
                                </td>
                                <td className="border border-slate-200 px-3 py-2 text-slate-700">
                                    {cellValue(event.area_label)}
                                </td>
                                <td className="border border-slate-200 px-3 py-2 font-mono text-xs text-slate-700">
                                    {cellValue(event.subject_key)}
                                </td>
                                <td className="border border-slate-200 px-3 py-2 text-slate-700">
                                    {cellValue(event.description)}
                                </td>
                                <td className="max-w-[200px] border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {cellValue(event.previous_values_label)}
                                </td>
                                <td className="max-w-[200px] border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {cellValue(event.new_values_label || event.change_detail)}
                                </td>
                                <td className="border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {deviceLabel(event.device_type)}
                                </td>
                                <td className="whitespace-nowrap border border-slate-200 px-3 py-2 font-mono text-xs text-slate-600">
                                    {cellValue(event.ip_address)}
                                </td>
                                <td
                                    className="max-w-[120px] truncate border border-slate-200 px-3 py-2 font-mono text-[11px] text-slate-500"
                                    title={event.session_id || undefined}
                                >
                                    {cellValue(event.session_id)}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
