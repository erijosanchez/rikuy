/*
 * Tema de ECharts para Rikuy — consume los design tokens (tokens.css) en vez
 * de hardcodear colores, para que el dashboard respete el design system
 * (regla 4 de CLAUDE.md). Lee las CSS custom properties en runtime.
 */

function cssVar(name, fallback = '') {
    if (typeof window === 'undefined') return fallback;
    const value = getComputedStyle(document.documentElement).getPropertyValue(name);
    return value.trim() || fallback;
}

export function chartTokens() {
    return {
        text: cssVar('--rk-text', '#e6edf3'),
        textMuted: cssVar('--rk-text-muted', '#9aa7b8'),
        textFaint: cssVar('--rk-text-faint', '#61708a'),
        border: cssVar('--rk-border', '#222b3a'),
        surface: cssVar('--rk-surface', '#111722'),
        surface2: cssVar('--rk-surface-2', '#161d2b'),
        fontSans: cssVar('--rk-font-sans', 'Inter, sans-serif'),
        fontMono: cssVar('--rk-font-mono', 'monospace'),
        series: [
            cssVar('--rk-series-1', '#2ec4b6'),
            cssVar('--rk-series-2', '#7c5cff'),
            cssVar('--rk-series-3', '#4c8dff'),
            cssVar('--rk-series-4', '#d9a441'),
            cssVar('--rk-series-5', '#f2545b'),
            cssVar('--rk-series-6', '#3fb950'),
        ],
    };
}

/**
 * Base común a todos los charts: grid compacto, tooltip y ejes en tono Grafana.
 */
export function baseOption(t = chartTokens()) {
    return {
        color: t.series,
        textStyle: { fontFamily: t.fontSans, color: t.textMuted },
        grid: { top: 24, left: 8, right: 16, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'axis',
            backgroundColor: t.surface2,
            borderColor: t.border,
            borderWidth: 1,
            textStyle: { color: t.text, fontFamily: t.fontSans },
            axisPointer: { type: 'line', lineStyle: { color: t.border } },
        },
    };
}

/**
 * Formateadores compartidos (es-PE) para etiquetas y tooltips.
 */
export const moneyFmt = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    maximumFractionDigits: 0,
});

export const numberFmt = new Intl.NumberFormat('es-PE');

/**
 * Abrevia montos grandes para los ejes (S/ 1.2M, S/ 850k).
 */
export function compactMoney(value) {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `S/ ${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `S/ ${Math.round(value / 1_000)}k`;
    return `S/ ${Math.round(value)}`;
}
