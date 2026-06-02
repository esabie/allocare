import { Link } from '@inertiajs/react';

/**
 * Resolve navigation target for a care alert (server href preferred, label fallbacks).
 */
export function resolveCareAlertHref(alert) {
    if (!alert) {
        return null;
    }

    if (alert.href) {
        return alert.href;
    }

    const slug = alert.patientUrlKey;
    const label = String(alert.label || '').toUpperCase();

    if (label.includes('MEDICATION')) {
        return slug ? route('patients.mar.show', { patient: slug, mar: 'today-mar' }) : null;
    }

    if (label.includes('OBSERVATION')) {
        return slug ? route('patients.observations', slug) : null;
    }

    if (label.includes('WOUND')) {
        return slug ? route('patients.wound-care', slug) : null;
    }

    if (label.includes('RISK REVIEW') && slug && alert.riskSlug) {
        return route('patients.risks.show', { patient: slug, risk: alert.riskSlug });
    }

    if (label.includes('RISK')) {
        return slug ? route('patients.risks', slug) : null;
    }

    if (label.includes('INCIDENT')) {
        return alert.incidentId
            ? route('reports.incidents.show', alert.incidentId)
            : route('reports.incidents');
    }

    if (label.includes('DATA BREACH')) {
        return alert.privacyRequestId
            ? `${route('reports.gdpr')}#privacy-request-${alert.privacyRequestId}`
            : route('reports.gdpr');
    }

    if (label.includes('RETENTION')) {
        return `${route('reports.gdpr')}#retention-schedules`;
    }

    if (label.includes('MISSED VISIT')) {
        return slug ? route('patients.handovers', slug) : route('schedules');
    }

    if (label.includes('HIGH RISK') || label.includes('ELEVATED RISK')) {
        return slug ? route('patients.show', slug) : null;
    }

    return null;
}

const buttonClass = 'inline-block rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800';

export default function CareAlertAction({ alert }) {
    const href = resolveCareAlertHref(alert);
    const label = alert?.action || 'View';

    if (href) {
        return (
            <Link href={href} className={buttonClass}>
                {label}
            </Link>
        );
    }

    return (
        <Link href={route('care-alerts')} className={buttonClass}>
            {label}
        </Link>
    );
}
