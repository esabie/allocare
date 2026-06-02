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

function MiniTrendChart({ title, unit, color, points = [] }) {
    const width = 280;
    const height = 72;
    const values = points.map((p) => p.value).filter((v) => v !== null && v !== undefined);

    if (values.length === 0) {
        return (
            <article className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
                <p className="mt-3 text-sm text-slate-400">No measurements in this period.</p>
            </article>
        );
    }

    let minY = Math.min(...values);
    let maxY = Math.max(...values);
    const padding = (maxY - minY) * 0.1 || 0.5;
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
                <path d={path} fill="none" strokeWidth="2" className={color.replace('text-', 'stroke-')} />
            </svg>
        </article>
    );
}

export default function WoundMeasurementCharts({ chartData = null }) {
    if (!chartData?.series) {
        return null;
    }

    const { series, from, to } = chartData;

    return (
        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">Measurement trends</h2>
                    <p className="text-sm text-slate-500">Track wound size over the last 30 days.</p>
                </div>
                <p className="text-xs text-slate-400">
                    {from} to {to}
                </p>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <MiniTrendChart title="Length" unit="cm" color="text-rose-600" points={series.length_cm} />
                <MiniTrendChart title="Width" unit="cm" color="text-orange-600" points={series.width_cm} />
                <MiniTrendChart title="Area (L×W)" unit="cm²" color="text-indigo-600" points={series.area_cm2} />
            </div>
        </section>
    );
}
