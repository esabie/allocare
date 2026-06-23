import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

const statusClass = {
    Active: 'bg-emerald-100 text-emerald-700',
    Draft: 'bg-slate-200 text-slate-600',
    'Under Review': 'bg-sky-100 text-sky-700',
    'Not started': 'bg-slate-100 text-slate-500',
};

const categoryLabels = {
    clinical: 'Clinical & Nursing',
    person_centred: 'Person-Centred Records',
    bespoke: 'Bespoke Sections',
};

const inputClass = 'w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100';

export default function PatientCarePlans({
    patientSlug = 'sarah-jenkins',
    patient = {},
    carePlans = [],
    moduleCatalogue = [],
    assignedModuleSlugs = [],
    canConfigureModules = false,
    canExportCarePlans = false,
}) {
    const { flash, errors = {} } = usePage().props;
    const cards = Array.isArray(carePlans) ? carePlans : [];
    const catalogue = Array.isArray(moduleCatalogue) ? moduleCatalogue : [];
    const assignedSet = useMemo(() => new Set(assignedModuleSlugs), [assignedModuleSlugs]);

    const [showConfigure, setShowConfigure] = useState(false);
    const [selectedSlugs, setSelectedSlugs] = useState([]);
    const [showBespokeForm, setShowBespokeForm] = useState(false);
    const [bespokeTitle, setBespokeTitle] = useState('');
    const [bespokePurpose, setBespokePurpose] = useState('');
    const [moduleToRemove, setModuleToRemove] = useState(null);
    const [isRemovingModule, setIsRemovingModule] = useState(false);

    const availableModules = catalogue.filter((module) => !assignedSet.has(module.slug));
    const groupedAvailable = useMemo(() => {
        const groups = {};
        availableModules.forEach((module) => {
            const key = module.category || 'clinical';
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(module);
        });
        return groups;
    }, [availableModules]);

    const groupedActive = useMemo(() => {
        const groups = {};
        cards.forEach((card) => {
            const key = card.category || 'clinical';
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(card);
        });
        return groups;
    }, [cards]);

    const toggleModuleSelection = (slug) => {
        setSelectedSlugs((current) => (
            current.includes(slug) ? current.filter((entry) => entry !== slug) : [...current, slug]
        ));
    };

    const addSelectedModules = () => {
        if (selectedSlugs.length === 0) {
            return;
        }
        router.post(route('patients.careplans.modules.store', patientSlug), {
            module_slugs: selectedSlugs,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedSlugs([]);
                setShowConfigure(false);
            },
        });
    };

    const requestRemoveModule = (card) => {
        setModuleToRemove({ slug: card.slug, title: card.title });
    };

    const confirmRemoveModule = () => {
        if (!moduleToRemove) {
            return;
        }

        setIsRemovingModule(true);
        router.delete(route('patients.careplans.modules.destroy', {
            patient: patientSlug,
            slug: moduleToRemove.slug,
        }), {
            preserveScroll: true,
            onFinish: () => {
                setIsRemovingModule(false);
                setModuleToRemove(null);
            },
        });
    };

    const createBespokeSection = (e) => {
        e.preventDefault();
        router.post(route('patients.careplans.modules.bespoke', patientSlug), {
            title: bespokeTitle,
            purpose: bespokePurpose,
        });
    };

    const renderCard = (card) => {
        const CardWrapper = card.href ? Link : 'div';
        const wrapperProps = card.href ? { href: card.href } : {};

        return (
            <CardWrapper
                key={card.slug}
                {...wrapperProps}
                className="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md"
            >
                <div className="mb-2 flex items-start justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">{card.title}</h2>
                        {card.purpose && (
                            <p className="mt-1 text-xs text-slate-500">{card.purpose}</p>
                        )}
                    </div>
                    <span className={`shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${statusClass[card.status] || statusClass['Not started']}`}>
                        {card.status}
                    </span>
                </div>

                {card.isBespoke && (
                    <span className="mb-3 inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-violet-700">
                        Bespoke
                    </span>
                )}

                {card.builderType === 'document' && (
                    <span className="mb-3 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-sky-700">
                        Document form
                    </span>
                )}

                <div className="mb-4 flex flex-wrap gap-2">
                    {(card.risks || []).map((risk) => (
                        <span key={risk} className="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                            {risk}
                        </span>
                    ))}
                </div>

                                        {card.reviewDueAt && (
                                            <p className={`mt-3 text-xs ${
                                                card.reviewOverdue
                                                    ? 'font-semibold text-rose-600'
                                                    : card.reviewDueSoon
                                                        ? 'font-semibold text-amber-700'
                                                        : 'text-slate-500'
                                            }`}>
                                                Review due: {card.reviewDueAt}
                                                {card.reviewOverdue && ' (overdue)'}
                                                {!card.reviewOverdue && card.reviewDueSoon && ' (due soon)'}
                                            </p>
                                        )}

                                        <div className="grid grid-cols-2 gap-3 text-xs text-slate-500">
                    <div>
                        <p className="uppercase tracking-wide">Last Action</p>
                        <p className="font-medium text-slate-700">{card.date}</p>
                    </div>
                    <div>
                        <p className="uppercase tracking-wide">Author</p>
                        <p className="font-medium text-slate-700">{card.author}</p>
                    </div>
                </div>

                {canConfigureModules && (
                    <div className="mt-4 border-t border-slate-100 pt-3" onClick={(e) => e.preventDefault()}>
                        <button
                            type="button"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                requestRemoveModule(card);
                            }}
                            className="text-xs font-semibold text-rose-600 hover:underline"
                        >
                            Remove module
                        </button>
                    </div>
                )}
            </CardWrapper>
        );
    };

    return (
        <>
            <Head title="Patient Care Plans" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="care_plans" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <span className="text-slate-900">Care Plans</span>
                        </div>

                        {flash?.success && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {flash.success}
                            </div>
                        )}

                        {Object.keys(errors).length > 0 && (
                            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                <ul className="list-disc space-y-1 pl-5">
                                    {Object.values(errors).map((msg) => (
                                        <li key={msg}>{msg}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <section className="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-2xl font-bold text-slate-900">Dynamic Care Plan Builder</h1>
                                    <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                        Configure care planning modules for {patient.name || 'this service user'} based on assessed needs.
                                        Select clinical domains and person-centred records, or add bespoke sections for organisation-specific requirements.
                                    </p>
                                </div>
                                {canExportCarePlans && cards.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        <a
                                            href={route('patients.careplans.export.pdf', patientSlug)}
                                            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
                                        >
                                            Export PDF Package
                                        </a>
                                        <a
                                            href={route('patients.careplans.export.zip', patientSlug)}
                                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Export ZIP (with documents)
                                        </a>
                                    </div>
                                )}
                                {canConfigureModules && (
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowConfigure((open) => !open);
                                                setShowBespokeForm(false);
                                            }}
                                            className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                        >
                                            {showConfigure ? 'Close' : 'Add Modules'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowBespokeForm((open) => !open);
                                                setShowConfigure(false);
                                            }}
                                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700"
                                        >
                                            {showBespokeForm ? 'Cancel' : 'Bespoke Section'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {showConfigure && (
                                <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p className="mb-3 text-sm font-medium text-slate-700">Select modules to add to this service user&apos;s care plan.</p>
                                    {availableModules.length === 0 ? (
                                        <p className="text-sm text-slate-500">All catalogue modules are already assigned.</p>
                                    ) : (
                                        <>
                                            {Object.entries(groupedAvailable).map(([category, modules]) => (
                                                <div key={category} className="mb-4">
                                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        {categoryLabels[category] || category}
                                                    </p>
                                                    <div className="space-y-2">
                                                        {modules.map((module) => (
                                                            <label key={module.slug} className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={selectedSlugs.includes(module.slug)}
                                                                    onChange={() => toggleModuleSelection(module.slug)}
                                                                    className="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                                />
                                                                <span>
                                                                    <span className="block text-sm font-semibold text-slate-900">{module.title}</span>
                                                                    {module.purpose && (
                                                                        <span className="mt-0.5 block text-xs text-slate-500">{module.purpose}</span>
                                                                    )}
                                                                </span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                            <div className="flex justify-end">
                                                <button
                                                    type="button"
                                                    onClick={addSelectedModules}
                                                    disabled={selectedSlugs.length === 0}
                                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    Add Selected ({selectedSlugs.length})
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}

                            {showBespokeForm && (
                                <form onSubmit={createBespokeSection} className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p className="mb-3 text-sm font-medium text-slate-700">Create a bespoke care plan section for organisation-specific needs.</p>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Section Title *</label>
                                            <input
                                                type="text"
                                                value={bespokeTitle}
                                                onChange={(e) => setBespokeTitle(e.target.value)}
                                                required
                                                className={inputClass}
                                                placeholder="e.g. Sensory Integration Support"
                                            />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Purpose / Scope *</label>
                                            <textarea
                                                value={bespokePurpose}
                                                onChange={(e) => setBespokePurpose(e.target.value)}
                                                required
                                                rows={3}
                                                className={inputClass}
                                                placeholder="Describe what this section covers and how staff should use it."
                                            />
                                        </div>
                                    </div>
                                    <div className="mt-3 flex justify-end">
                                        <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                                            Create Section
                                        </button>
                                    </div>
                                </form>
                            )}
                        </section>

                        {cards.length === 0 ? (
                            <div className="rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                                <p className="text-lg font-semibold text-slate-900">No care plan modules configured yet</p>
                                <p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                                    Use &quot;Add Modules&quot; to select relevant clinical and person-centred care planning sections for this service user,
                                    or create a bespoke section for organisation-specific requirements.
                                </p>
                            </div>
                        ) : (
                            Object.entries(groupedActive).map(([category, sectionCards]) => (
                                <section key={category} className="mb-6">
                                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                                        {categoryLabels[category] || category}
                                    </h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                        {sectionCards.map((card) => renderCard(card))}
                                    </div>
                                </section>
                            ))
                        )}
                    </main>
                </div>
            </div>

            <ConfirmDialog
                show={Boolean(moduleToRemove)}
                title="Remove care plan module?"
                message={
                    moduleToRemove
                        ? `Remove "${moduleToRemove.title}" from this service user? Saved content will remain in the system but the module will no longer appear here.`
                        : ''
                }
                cancelLabel="Cancel"
                confirmLabel="OK"
                confirmVariant="danger"
                processing={isRemovingModule}
                onClose={() => {
                    if (!isRemovingModule) {
                        setModuleToRemove(null);
                    }
                }}
                onConfirm={confirmRemoveModule}
            />
        </>
    );
}
