import { currentTheme, watchTheme } from './charts/theme';
import { euros, wholeEuros } from './charts/format';
import { averageLine, mean } from './charts/average';

/** Emerald like the app's accent: income reads as the positive counterpart to the slate spending line. */
const INCOME_COLOR = '#059669';

// The chart instance and the data live in closure variables, so Alpine's reactive proxies never wrap them.
export default () => {
    let chart = null;
    let cleanup = [];

    return {
        /** Average income per imported month (or year), formatted. */
        average: '',

        async init() {
            const { echarts } = await import('./charts/echarts');
            const data = JSON.parse(this.$el.dataset.incomeChart);

            /** Unimported periods break the line. */
            const values = data.values.map((v, i) => (data.imported[i] ? v : '-'));

            /** Mean over the imported periods only. */
            const average = mean(values.filter((v) => v !== '-'));

            const series = () => ({
                id: 'income',
                name: 'Income',
                type: 'line',
                connectNulls: false,
                showSymbol: true,
                showAllSymbol: true,
                itemStyle: { color: INCOME_COLOR },
                lineStyle: { color: INCOME_COLOR },
                data: values,
                markLine: averageLine(average),
            });

            chart = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
            chart.setOption({
                grid: { top: 16, left: 8, right: 8, bottom: 8, outerBoundsMode: 'same', outerBoundsContain: 'axisLabel' },
                tooltip: {
                    trigger: 'axis',
                    formatter: (params) => {
                        const p = params[0];
                        const body = data.imported[p.dataIndex]
                            ? `<div>${p.marker}${euros(p.value)}</div>`
                            : '<div style="opacity:.7">Not imported</div>';
                        return `<div style="font-weight:600;margin-bottom:4px">${p.axisValueLabel}</div>${body}`;
                    },
                },
                xAxis: { type: 'category', data: data.labels },
                yAxis: { type: 'value', axisLabel: { formatter: (v) => wholeEuros(v) } },
                series: [series()],
            });
            this.average = average === null ? '' : `Ø ${euros(average)} / ${data.per}`;

            const observer = new ResizeObserver(() => chart?.resize());
            observer.observe(this.$refs.chart);
            cleanup = [
                () => observer.disconnect(),
                watchTheme((name) => {
                    chart?.setTheme(name);
                    // The average label's background follows the theme.
                    chart?.setOption({ series: [series()] });
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
