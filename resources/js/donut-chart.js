import { currentTheme, watchTheme } from './charts/theme';

const percent = (value) =>
    `${value.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

export default () => ({
    chart: null,
    cleanup: [],

    async init() {
        const { echarts } = await import('./charts/echarts');
        const data = JSON.parse(this.$el.dataset.chart);

        this.chart = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
        this.chart.setOption({
            title: {
                text: data.total,
                subtext: data.label,
                left: 'center',
                top: 'center',
                itemGap: 4,
                textStyle: { fontSize: 20, fontWeight: 600 },
                subtextStyle: { fontSize: 12 },
            },
            tooltip: {
                trigger: 'item',
                formatter: (p) => `${p.marker}${p.name}<br>${p.data.amount} · ${percent(p.percent)}`,
            },
            series: [{
                type: 'pie',
                radius: ['62%', '85%'],
                label: { show: false },
                labelLine: { show: false },
                itemStyle: { borderWidth: 2, borderRadius: 4 },
                emphasis: { scale: true, scaleSize: 4, label: { show: false } },
                data: data.slices.map((s) => ({ key: s.key, name: s.name, value: s.value, amount: s.amount, itemStyle: { color: s.color } })),
            }],
        });
        this.chart.on('click', (params) => {
            window.dispatchEvent(new CustomEvent('entries:group', { detail: { group: params.data.key } }));
        });

        const observer = new ResizeObserver(() => this.chart?.resize());
        observer.observe(this.$refs.chart);
        this.cleanup = [() => observer.disconnect(), watchTheme((name) => this.chart?.setTheme(name))];
    },

    destroy() {
        this.cleanup.forEach((fn) => fn());
        this.chart?.dispose();
        this.chart = null;
    },
});
