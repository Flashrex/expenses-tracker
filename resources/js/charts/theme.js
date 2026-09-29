const font = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

const theme = ({ text, muted, surface, border, legend, grid, inactive, neutral }) => ({
    backgroundColor: 'transparent',
    textStyle: { fontFamily: font, color: text },
    title: { textStyle: { color: text }, subtextStyle: { color: muted } },
    tooltip: { backgroundColor: surface, borderColor: border, textStyle: { color: text, fontFamily: font } },
    legend: { textStyle: { color: legend }, inactiveColor: inactive },
    pie: { itemStyle: { borderColor: surface } },
    categoryAxis: { axisLine: { lineStyle: { color: border } }, axisTick: { show: false }, axisLabel: { color: muted }, splitLine: { show: false } },
    valueAxis: { axisLine: { show: false }, axisLabel: { color: muted }, splitLine: { lineStyle: { color: grid } } },
    bar: { label: { color: muted } },
    line: { symbol: 'circle', symbolSize: 6, smooth: false, lineStyle: { width: 2, color: neutral }, itemStyle: { color: neutral } },
});

export const themes = {
    'expenses-light': theme({ text: '#0f172a', muted: '#64748b', surface: '#ffffff', border: '#e2e8f0', legend: '#475569', grid: '#f1f5f9', inactive: '#cbd5e1', neutral: '#475569' }),
    'expenses-dark': theme({ text: '#f1f5f9', muted: '#94a3b8', surface: '#0f172a', border: '#1e293b', legend: '#cbd5e1', grid: '#1e293b', inactive: '#475569', neutral: '#94a3b8' }),
};

const query = () => window.matchMedia('(prefers-color-scheme: dark)');

export const currentTheme = () => (query().matches ? 'expenses-dark' : 'expenses-light');

/** Calls callback(themeName) whenever the OS theme changes; returns an unsubscribe function. */
export function watchTheme(callback) {
    const media = query();
    const listener = () => callback(currentTheme());
    media.addEventListener('change', listener);
    return () => media.removeEventListener('change', listener);
}
