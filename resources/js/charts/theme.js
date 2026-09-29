const font = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

const theme = ({ text, muted, surface, border, legend }) => ({
    backgroundColor: 'transparent',
    textStyle: { fontFamily: font, color: text },
    title: { textStyle: { color: text }, subtextStyle: { color: muted } },
    tooltip: { backgroundColor: surface, borderColor: border, textStyle: { color: text, fontFamily: font } },
    legend: { textStyle: { color: legend } },
    pie: { itemStyle: { borderColor: surface } },
});

export const themes = {
    'expenses-light': theme({ text: '#0f172a', muted: '#64748b', surface: '#ffffff', border: '#e2e8f0', legend: '#475569' }),
    'expenses-dark': theme({ text: '#f1f5f9', muted: '#94a3b8', surface: '#0f172a', border: '#1e293b', legend: '#cbd5e1' }),
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
