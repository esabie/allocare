import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';
import ReportPagination, { paginatorData } from '@/Components/ReportPagination';

function StatCard({ label, value, tone = 'slate' }) {
    const toneClasses = {
        slate: 'text-slate-900',
        green: 'text-emerald-700',
        amber: 'text-amber-700',
        red: 'text-rose-700',
        blue: 'text-blue-700',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${toneClasses[tone] || toneClasses.slate}`}>{value}</p>
        </div>
    );
}

function formatDate(value) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function riskBadge(risk) {
    if (risk === 'red') return 'bg-rose-100 text-rose-700';
    if (risk === 'amber') return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
}

function riskLabel(risk) {
    if (risk === 'red') return 'Overdue';
    if (risk === 'amber') return 'Due Soon';
    return 'Compliant';
}

export default function ReportsComplianceTraining({
    stats = {},
    staffRows = [],
    riskSummary = {},
    expiryWindows = {},
    actions = [],
    filters = {},
    roleOptions = [],
}) {
    const [selectedRole, setSelectedRole] = useState(filters.role || 'all');
    const [selectedRisk, setSelectedRisk] = useState(filters.risk || 'all');

    const applyFilters = () => {
        router.get(route('reports.compliance-training'), {
            role: selectedRole,
            risk: selectedRisk,
        }, { preserveState: true, preserveScroll: true });
    };

    const exportQuery = {
        role: selectedRole,
        risk: selectedRisk,
    };

    const staffRowsData = paginatorData(staffRows);
    const actionRows = paginatorData(actions);

    return (
        <>
            <Head title="Compliance & Training Report" />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                            <span>/</span>
                            <span className="text-slate-900">Compliance & Training</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Compliance & Training Report</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Track workforce readiness, expiry risk, and immediate compliance actions.
                            </p>
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Filters</p>
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="text-xs text-slate-500">Role</label>
                                    <select
                                        value={selectedRole}
                                        onChange={(e) => setSelectedRole(e.target.value)}
                                        className="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                    >
                                        <option value="all">All roles</option>
                                        {roleOptions.map((role) => (
                                            <option key={role.value} value={role.value}>{role.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs text-slate-500">Risk</label>
                                    <select
                                        value={selectedRisk}
                                        onChange={(e) => setSelectedRisk(e.target.value)}
                                        className="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                    >
                                        <option value="all">All risks</option>
                                        <option value="green">Compliant</option>
                                        <option value="amber">Due Soon</option>
                                        <option value="red">Overdue</option>
                                    </select>
                                </div>
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                                >
                                    Apply
                                </button>
                                <a
                                    href={route('reports.compliance-training.export.csv', exportQuery)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export CSV
                                </a>
                                <a
                                    href={route('reports.compliance-training.export.pdf', exportQuery)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export PDF
                                </a>
                            </div>
                        </section>

                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            <StatCard label="Total Staff" value={stats.totalStaff || 0} />
                            <StatCard label="Fully Compliant" value={stats.fullyCompliant || 0} tone="green" />
                            <StatCard label="Due Soon" value={stats.dueSoon || 0} tone="amber" />
                            <StatCard label="Overdue Critical" value={stats.overdueCritical || 0} tone="red" />
                            <StatCard label="Compliance Rate" value={`${stats.complianceRate || 0}%`} tone="blue" />
                            <StatCard label="Missing Evidence" value={stats.missingEvidence || 0} tone="amber" />
                        </section>

                        <section className="mb-6 grid gap-4 lg:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Risk Summary</h2>
                                <div className="grid grid-cols-3 gap-3">
                                    <StatCard label="Compliant" value={riskSummary.green || 0} tone="green" />
                                    <StatCard label="Due Soon" value={riskSummary.amber || 0} tone="amber" />
                                    <StatCard label="Overdue" value={riskSummary.red || 0} tone="red" />
                                </div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Expiry Windows</h2>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <StatCard label="7 Days" value={expiryWindows.days7 || 0} tone="red" />
                                    <StatCard label="30 Days" value={expiryWindows.days30 || 0} tone="amber" />
                                    <StatCard label="60 Days" value={expiryWindows.days60 || 0} tone="blue" />
                                    <StatCard label="90 Days" value={expiryWindows.days90 || 0} tone="slate" />
                                </div>
                            </div>
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Immediate Actions</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Staff</th>
                                            <th className="border border-slate-200 px-3 py-2">Role</th>
                                            <th className="border border-slate-200 px-3 py-2">Risk</th>
                                            <th className="border border-slate-200 px-3 py-2">Action Required</th>
                                            <th className="border border-slate-200 px-3 py-2">Due Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {actionRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No immediate actions in the selected filter scope.
                                                </td>
                                            </tr>
                                        ) : (
                                            actionRows.map((action, index) => (
                                                <tr key={`${action.user_id}-${index}`} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2 font-medium text-slate-800">{action.staff_name}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{action.role_label}</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase ${riskBadge(action.risk)}`}>
                                                            {riskLabel(action.risk)}
                                                        </span>
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{action.message}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{formatDate(action.due_date)}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination pagination={actions} />
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Staff Compliance Matrix</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Staff</th>
                                            <th className="border border-slate-200 px-3 py-2">Role</th>
                                            <th className="border border-slate-200 px-3 py-2">Training</th>
                                            <th className="border border-slate-200 px-3 py-2">Competencies</th>
                                            <th className="border border-slate-200 px-3 py-2">Supervisions</th>
                                            <th className="border border-slate-200 px-3 py-2">DBS</th>
                                            <th className="border border-slate-200 px-3 py-2">Documents</th>
                                            <th className="border border-slate-200 px-3 py-2">Risk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {staffRowsData.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No staff data available for this filter combination.
                                                </td>
                                            </tr>
                                        ) : (
                                            staffRowsData.map((row) => (
                                                <tr key={row.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2 font-medium text-slate-800">{row.staff_name}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.role_label}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.training_summary}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.competency_summary}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.supervision_summary}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.dbs_summary}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.documents_summary}</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase ${riskBadge(row.risk)}`}>
                                                            {riskLabel(row.risk)}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination pagination={staffRows} />
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
