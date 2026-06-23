function buildPath(points, width, height, minY, maxY) {
    if (points.length === 0) {
        return '';
    }

    const range = maxY - minY || 1;
    const xStep = points.length > 1 ? width / (points.length - 1) : 0;

    return points
        .map((point, index) => {
            const x = points.length > 1 ? index * xStep : width / 2;
            const y = height - ((point.value - minY) / range) * height;
            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
}

const strokeByAccent = {
    'text-slate-800': 'stroke-slate-800',
    'text-rose-600': 'stroke-rose-600',
    'text-emerald-600': 'stroke-emerald-600',
    'text-orange-600': 'stroke-orange-600',
    'text-indigo-600': 'stroke-indigo-600',
    'text-amber-700': 'stroke-amber-700',
};

function MiniTrendChart({ title, unit, color, points = [], thresholds = null }) {
    const width = 320;
    const height = 72;
    const values = points.map((p) => p.value).filter((v) => v !== null && v !== undefined);

    if (values.length === 0) {
        return (
            <article className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
                <p className="mt-3 text-sm text-slate-400">No readings in this period.</p>
            </article>
        );
    }

    let minY = Math.min(...values);
    let maxY = Math.max(...values);
    if (thresholds?.min !== undefined) {
        minY = Math.min(minY, thresholds.min);
    }
    if (thresholds?.max !== undefined) {
        maxY = Math.max(maxY, thresholds.max);
    }
    const padding = (maxY - minY) * 0.1 || 1;
    minY -= padding;
    maxY += padding;

    const path = buildPath(points, width, height, minY, maxY);
    const latest = points[points.length - 1];

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="mb-2 flex items-end justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
                    <p className={`text-2xl font-bold ${color}`}>
                        {latest.value}
                        <span className="ml-1 text-sm font-medium text-slate-500">{unit}</span>
                    </p>
                </div>
                <p className="text-[10px] text-slate-400">{latest.label}</p>
            </div>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-20 w-full" preserveAspectRatio="none">
                {thresholds?.low !== undefined && (
                    <line
                        x1="0"
                        y1={height - ((thresholds.low - minY) / (maxY - minY || 1)) * height}
                        x2={width}
                        y2={height - ((thresholds.low - minY) / (maxY - minY || 1)) * height}
                        stroke="#f59e0b"
                        strokeDasharray="4 4"
                        strokeWidth="1"
                    />
                )}
                {thresholds?.high !== undefined && (
                    <line
                        x1="0"
                        y1={height - ((thresholds.high - minY) / (maxY - minY || 1)) * height}
                        x2={width}
                        y2={height - ((thresholds.high - minY) / (maxY - minY || 1)) * height}
                        stroke="#f59e0b"
                        strokeDasharray="4 4"
                        strokeWidth="1"
                    />
                )}
                {thresholds?.critical !== undefined && (
                    <line
                        x1="0"
                        y1={height - ((thresholds.critical - minY) / (maxY - minY || 1)) * height}
                        x2={width}
                        y2={height - ((thresholds.critical - minY) / (maxY - minY || 1)) * height}
                        stroke="#e11d48"
                        strokeDasharray="4 4"
                        strokeWidth="1"
                    />
                )}
                <path
                    d={path}
                    fill="none"
                    strokeWidth="2"
                    className={strokeByAccent[color] || 'stroke-slate-800'}
                />
            </svg>
        </article>
    );
}

export default function ObservationTrendCharts({ chartData = null }) {
    if (!chartData?.series) {
        return null;
    }

    const { series, thresholds, from, to } = chartData;

    return (
        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">NEWS2 &amp; observation trends</h2>
                    <p className="text-sm text-slate-500">Last 30 days — includes aggregate NEWS2 score trending.</p>
                </div>
                <p className="text-xs text-slate-400">
                    {from} to {to}
                </p>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <MiniTrendChart title="NEWS2 score" unit="" color="text-rose-600" points={series.news2_score} thresholds={thresholds?.news2_score} />
                <MiniTrendChart title="Respiration rate" unit="/min" color="text-slate-800" points={series.respiration_rate} />
                <MiniTrendChart title="Pulse" unit="bpm" color="text-slate-800" points={series.heart_rate} thresholds={thresholds?.heart_rate} />
                <MiniTrendChart title="BP systolic" unit="mmHg" color="text-rose-600" points={series.bp_systolic} thresholds={thresholds?.bp_systolic} />
                <MiniTrendChart title="SpO₂" unit="%" color="text-emerald-600" points={series.spo2} thresholds={thresholds?.spo2} />
                <MiniTrendChart title="Temperature" unit="°C" color="text-orange-600" points={series.temperature_celsius} thresholds={thresholds?.temperature} />
                <MiniTrendChart title="Blood glucose" unit="mmol/L" color="text-indigo-600" points={series.blood_glucose_mmol} thresholds={thresholds?.glucose} />
                <MiniTrendChart title="Pain score" unit="/10" color="text-amber-700" points={series.pain_score} thresholds={thresholds?.pain} />
            </div>
        </section>
    );
}
