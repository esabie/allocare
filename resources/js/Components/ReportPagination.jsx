import { router } from '@inertiajs/react';

export function paginatorData(pagination) {
    if (!pagination) {
        return [];
    }

    if (Array.isArray(pagination)) {
        return pagination;
    }

    return pagination.data ?? [];
}

export function paginatorMeta(pagination) {
    if (!pagination || Array.isArray(pagination)) {
        return null;
    }

    return pagination.meta ?? pagination;
}

export function paginatorLinks(pagination) {
    if (!pagination || Array.isArray(pagination)) {
        return null;
    }

    if (pagination.links && (pagination.links.prev !== undefined || pagination.links.next !== undefined)) {
        return pagination.links;
    }

    return {
        prev: pagination.prev_page_url ?? null,
        next: pagination.next_page_url ?? null,
    };
}

export default function ReportPagination({ pagination, className = '' }) {
    const meta = paginatorMeta(pagination);
    const links = paginatorLinks(pagination);

    if (!meta || meta.total === 0) {
        return null;
    }

    const goToPage = (url) => {
        if (!url) {
            return;
        }

        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <div className={`flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 ${className}`.trim()}>
            <p className="text-xs text-slate-500">
                Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
            </p>
            {meta.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        disabled={!links?.prev}
                        onClick={() => goToPage(links?.prev)}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Previous
                    </button>
                    <span className="text-xs font-medium text-slate-600">
                        Page {meta.current_page} of {meta.last_page}
                    </span>
                    <button
                        type="button"
                        disabled={!links?.next}
                        onClick={() => goToPage(links?.next)}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
}
