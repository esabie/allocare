const FRONT_REGIONS = [
    { id: 'head_front', label: 'Head', style: 'left-[42%] top-[2%] h-[10%] w-[16%]' },
    { id: 'chest_front', label: 'Chest', style: 'left-[32%] top-[12%] h-[14%] w-[36%]' },
    { id: 'abdomen_front', label: 'Abdomen', style: 'left-[34%] top-[26%] h-[12%] w-[32%]' },
    { id: 'left_arm_front', label: 'L arm', style: 'left-[12%] top-[14%] h-[28%] w-[14%]' },
    { id: 'right_arm_front', label: 'R arm', style: 'left-[74%] top-[14%] h-[28%] w-[14%]' },
    { id: 'left_leg_front', label: 'L leg', style: 'left-[30%] top-[40%] h-[32%] w-[16%]' },
    { id: 'right_leg_front', label: 'R leg', style: 'left-[54%] top-[40%] h-[32%] w-[16%]' },
];

const BACK_REGIONS = [
    { id: 'head_back', label: 'Head', style: 'left-[42%] top-[2%] h-[10%] w-[16%]' },
    { id: 'upper_back', label: 'Upper back', style: 'left-[32%] top-[12%] h-[16%] w-[36%]' },
    { id: 'lower_back', label: 'Lower back', style: 'left-[34%] top-[28%] h-[12%] w-[32%]' },
    { id: 'sacrum', label: 'Sacrum', style: 'left-[38%] top-[40%] h-[8%] w-[24%]' },
    { id: 'left_arm_back', label: 'L arm', style: 'left-[12%] top-[14%] h-[28%] w-[14%]' },
    { id: 'right_arm_back', label: 'R arm', style: 'left-[74%] top-[14%] h-[28%] w-[14%]' },
    { id: 'left_leg_back', label: 'L leg', style: 'left-[30%] top-[50%] h-[28%] w-[16%]' },
    { id: 'right_leg_back', label: 'R leg', style: 'left-[54%] top-[50%] h-[28%] w-[16%]' },
    { id: 'left_heel', label: 'L heel', style: 'left-[30%] top-[80%] h-[8%] w-[16%]' },
    { id: 'right_heel', label: 'R heel', style: 'left-[54%] top-[80%] h-[8%] w-[16%]' },
];

function BodyDiagram({ title, regions, selected, onSelect }) {
    return (
        <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
            <div className="relative mx-auto h-64 w-40 rounded-2xl border border-slate-200 bg-slate-50">
                {regions.map((region) => (
                    <button
                        key={region.id}
                        type="button"
                        title={region.label}
                        onClick={() => onSelect(region.id)}
                        className={`absolute rounded-md border text-[9px] font-semibold transition ${region.style} ${
                            selected === region.id
                                ? 'border-emerald-600 bg-emerald-100 text-emerald-800'
                                : 'border-slate-300 bg-white/80 text-slate-600 hover:border-emerald-400'
                        }`}
                    >
                        {region.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function WoundBodyMap({ value = '', onChange }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BodyDiagram
                title="Front"
                regions={FRONT_REGIONS}
                selected={value}
                onSelect={onChange}
            />
            <BodyDiagram
                title="Back"
                regions={BACK_REGIONS}
                selected={value}
                onSelect={onChange}
            />
        </div>
    );
}
