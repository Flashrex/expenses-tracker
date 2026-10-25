import { currentTheme, themes } from './theme';
import { wholeEuros } from './format';

/** Saturated red: stands out against the muted group colours and the slate total line. */
const AVERAGE_COLOR = '#ef4444';

/** Mean of the values, or null when there are none. */
export const mean = (values) => (values.length === 0 ? null : Math.round(values.reduce((sum, v) => sum + v, 0) / values.length));

/** Dashed, semi-transparent horizontal line at the average, behind the bars and the line (z 2); nothing when there is no average. */
export const averageLine = (value) => ({
    z: 1,
    silent: true,
    symbol: 'none',
    lineStyle: { color: AVERAGE_COLOR, type: 'dashed', width: 1.5, opacity: 0.6 },
    // On the y axis like its tick labels (same 8px margin); the background hides a tick label it overlaps.
    label: {
        position: 'start',
        distance: 8,
        color: AVERAGE_COLOR,
        fontSize: 12,
        fontWeight: 600,
        backgroundColor: themes[currentTheme()].tooltip.backgroundColor,
        padding: [2, 0, 2, 4],
        formatter: () => `Ø ${wholeEuros(value)}`,
    },
    data: value === null ? [] : [{ yAxis: value }],
});
