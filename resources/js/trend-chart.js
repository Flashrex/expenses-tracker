import { currentTheme, watchTheme } from './charts/theme';
import { euros, wholeEuros } from './charts/format';

// The chart instance and the data live in closure variables, so Alpine's reactive proxies never wrap them.
export default () => {
    let chart = null;
    let cleanup = [];

    return {
        async init() {
            const { echarts } = await import('./charts/echarts');
            const data = JSON.parse(this.$el.dataset.chart);

            /** Per-period sums of the groups the legend currently shows. */
            const visibleTotals = (selected) =>
                data.labels.map((_, i) =>
                    data.series.reduce((sum, s) => sum + (selected[s.name] === false ? 0 : s.values[i]), 0));

            const totalData = (selected) => visibleTotals(selected).map((total) => ({ value: 0, total }));

            const refreshTotals = () => {
                const selected = chart.getOption().legend?.[0]?.selected ?? {};
                chart.setOption({ series: [{ id: 'total', data: totalData(selected) }] });
            };

            chart = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
            chart.setOption({
                grid: { top: 32, left: 8, right: 8, bottom: 48, outerBoundsMode: 'same', outerBoundsContain: 'axisLabel' },
                legend: { type: 'scroll', bottom: 0, icon: 'circle', itemWidth: 10, itemHeight: 10, data: data.series.map((s) => s.name) },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' },
                    formatter: (params) => {
                        const rows = params.filter((p) => p.seriesId !== 'total' && p.value > 0);
                        const total = rows.reduce((sum, p) => sum + p.value, 0);
                        const line = (left, right, bold = false) =>
                            `<div style="display:flex;justify-content:space-between;gap:16px${bold ? ';font-weight:600' : ''}"><span>${left}</span><span>${right}</span></div>`;
                        return [
                            `<div style="font-weight:600;margin-bottom:4px">${params[0].axisValueLabel}</div>`,
                            ...rows.map((p) => line(`${p.marker}${p.seriesName}`, euros(p.value))),
                            line('Total', euros(total), true),
                        ].join('');
                    },
                },
                xAxis: { type: 'category', data: data.labels },
                yAxis: { type: 'value', axisLabel: { formatter: (v) => wholeEuros(v) } },
                series: [
                    ...data.series.map((s) => ({
                        id: s.key,
                        name: s.name,
                        type: 'bar',
                        stack: 'spending',
                        barMaxWidth: 48,
                        itemStyle: { color: s.color },
                        emphasis: { focus: 'series' },
                        data: s.values,
                    })),
                    {
                        // Zero-height bar stacked last: its top label carries the total of the visible groups.
                        id: 'total',
                        name: 'Total',
                        type: 'bar',
                        stack: 'spending',
                        barMaxWidth: 48,
                        silent: true,
                        tooltip: { show: false },
                        label: { show: true, position: 'top', fontWeight: 600, formatter: (p) => wholeEuros(p.data.total) },
                        data: totalData({}),
                    },
                ],
            });

            chart.on('legendselectchanged', refreshTotals);

            const observer = new ResizeObserver(() => chart?.resize());
            observer.observe(this.$refs.chart);
            cleanup = [
                () => observer.disconnect(),
                watchTheme((name) => {
                    chart?.setTheme(name);
                    refreshTotals();
                }),
            ];
        },

        destroy() {
            cleanup.forEach((fn) => fn());
            chart?.dispose();
            chart = null;
        },
    };
};
