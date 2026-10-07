import { currentTheme, watchTheme } from './charts/theme';
import { euros, wholeEuros } from './charts/format';

// Both chart instances and the data live in closure variables, so Alpine's reactive proxies never wrap them.
export default () => {
    let bars = null;
    let line = null;
    let data = null;
    let apply = () => {};
    let cleanup = [];

    return {
        /** null or { key, name, color } of the locked group. */
        locked: null,

        /** Group key => false for the groups hidden with the chips below the bar chart. */
        hidden: {},

        /** Average per imported month (or year) of the line chart, formatted. */
        average: '',

        async init() {
            const { echarts } = await import('./charts/echarts');
            data = JSON.parse(this.$el.dataset.chart);

            /** Legend selection by series name, as the chart and the visible totals expect it. */
            const selected = () => Object.fromEntries(data.series.map((s) => [s.name, this.hidden[s.key] !== true]));

            /** Per-period sums of the groups the legend currently shows. */
            const visibleTotals = (sel) =>
                data.labels.map((_, i) =>
                    data.series.reduce((sum, s) => sum + (sel[s.name] === false ? 0 : s.values[i]), 0));

            const totalData = (sel) => visibleTotals(sel).map((total) => ({ value: 0, total }));

            /** Lock styling of the group series: others dimmed like hover, hover paused while locked. */
            const groupStates = () => data.series.map((s) => ({
                id: s.key,
                itemStyle: { color: s.color, opacity: this.locked && this.locked.key !== s.key ? 0.1 : 1 },
                emphasis: { disabled: this.locked !== null, focus: 'series' },
            }));

            /** The locked group's line, or the total of the legend-visible groups; unimported periods break the line. */
            const lineSeries = (sel) => {
                const group = this.locked ? data.series.find((s) => s.key === this.locked.key) : null;
                const values = group ? group.values : visibleTotals(sel);
                return {
                    id: group ? `line-${group.key}` : 'line-total',
                    name: group ? group.name : 'Total',
                    type: 'line',
                    connectNulls: false,
                    showSymbol: true,
                    showAllSymbol: true,
                    data: values.map((v, i) => (data.imported[i] ? v : '-')),
                    ...(group ? { itemStyle: { color: group.color }, lineStyle: { color: group.color } } : {}),
                };
            };

            /** Mean of the line over the imported periods only. */
            const averageOf = (sel) => {
                const values = lineSeries(sel).data.filter((v) => v !== '-');
                return values.length === 0 ? '' : `Ø ${euros(Math.round(values.reduce((sum, v) => sum + v, 0) / values.length))} / ${data.per}`;
            };

            apply = () => {
                const sel = selected();
                bars.setOption({ legend: { selected: sel }, series: [...groupStates(), { id: 'total', data: totalData(sel) }] });
                line.setOption({ series: [lineSeries(sel)] }, { replaceMerge: ['series'] });
                this.average = averageOf(sel);
            };

            bars = echarts.init(this.$refs.chart, currentTheme(), { renderer: 'svg' });
            bars.setOption({
                grid: { top: 32, left: 8, right: 8, bottom: 8, outerBoundsMode: 'same', outerBoundsContain: 'axisLabel' },
                // Hidden: the chips below the chart switch groups on and off through its selection.
                legend: { show: false, data: data.series.map((s) => s.name) },
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

            line = echarts.init(this.$refs.line, currentTheme(), { renderer: 'svg' });
            line.setOption({
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
                series: [lineSeries({})],
            });
            this.average = averageOf(selected());

            bars.on('click', (p) => {
                if (p.componentType === 'series' && p.seriesId !== 'total') {
                    this.toggle(p.seriesId);
                }
            });

            const observer = new ResizeObserver(() => {
                bars?.resize();
                line?.resize();
            });
            observer.observe(this.$refs.chart);
            observer.observe(this.$refs.line);
            cleanup = [
                () => observer.disconnect(),
                watchTheme((name) => {
                    bars?.setTheme(name);
                    line?.setTheme(name);
                    apply();
                }),
            ];
        },

        /** Click on a segment: lock it, switch the lock, or unlock when it is already locked. */
        toggle(key) {
            const group = data.series.find((s) => s.key === key);
            this.clearHover();
            this.locked = this.locked?.key === key ? null : { key: group.key, name: group.name, color: group.color };
            apply();
        },

        isVisible(key) {
            return this.hidden[key] !== true;
        },

        /** Single click on a chip: show or hide the group; hiding the locked group unlocks it. */
        toggleVisible(key) {
            this.hidden = { ...this.hidden, [key]: this.isVisible(key) };
            if (this.locked?.key === key && !this.isVisible(key)) {
                this.clearHover();
                this.locked = null;
            }
            apply();
        },

        /** Double click on a chip: show and lock only this group, or show all again when it already is the only one. */
        solo(key) {
            const alone = data.series.every((s) => this.isVisible(s.key) === (s.key === key));
            this.clearHover();

            if (alone) {
                this.hidden = {};
            } else {
                const group = data.series.find((s) => s.key === key);
                this.hidden = Object.fromEntries(data.series.filter((s) => s.key !== key).map((s) => [s.key, true]));
                this.locked = { key: group.key, name: group.name, color: group.color };
            }
            apply();
        },

        unlock() {
            this.clearHover();
            this.locked = null;
            apply();
        },

        /** Drops any hover blur before the lock styling changes. */
        clearHover() {
            bars?.dispatchAction({ type: 'downplay', seriesId: data.series.map((s) => s.key) });
        },

        destroy() {
            cleanup.forEach((fn) => fn());
            bars?.dispose();
            line?.dispose();
            bars = null;
            line = null;
        },
    };
};
