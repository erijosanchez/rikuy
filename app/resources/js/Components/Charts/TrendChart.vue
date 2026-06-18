<script setup>
/*
 * Tendencia mensual: barras de monto + línea de acumulado (window function).
 * Si llega una proyección (Fase 6) se dibuja, a continuación de la historia, la
 * línea punteada de pronóstico y su banda de confianza (técnica de áreas
 * apiladas: base transparente + banda translúcida).
 */
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';
import { baseOption, chartTokens, compactMoney, moneyFmt } from '../../charts/theme.js';

const props = defineProps({
    trend: { type: Array, default: () => [] },
    // { model, confidence, points: [{ds, yhat, yhat_lower, yhat_upper}] } | null
    forecast: { type: Object, default: null },
});

const hasForecast = computed(
    () => !!props.forecast && Array.isArray(props.forecast.points) && props.forecast.points.length > 0,
);

const option = computed(() => {
    const t = chartTokens();

    const histLabels = props.trend.map((r) => r.period);
    const monto = props.trend.map((r) => r.monto);
    const acumulado = props.trend.map((r) => r.acumulado);
    const h = histLabels.length;

    const fc = hasForecast.value ? props.forecast.points : [];
    const labels = [...histLabels, ...fc.map((p) => p.ds)];
    const lastMonto = h > 0 ? monto[h - 1] : 0;

    // Línea punteada de proyección, enganchada al último mes real.
    const projLine = new Array(labels.length).fill(null);
    const bandLower = new Array(labels.length).fill(null);
    const bandSpan = new Array(labels.length).fill(null); // upper - lower (para apilar)
    const lowerVals = new Array(labels.length).fill(null);
    const upperVals = new Array(labels.length).fill(null);

    if (hasForecast.value && h > 0) {
        projLine[h - 1] = lastMonto;
        bandLower[h - 1] = lastMonto;
        bandSpan[h - 1] = 0;
        fc.forEach((p, k) => {
            const i = h + k;
            projLine[i] = p.yhat;
            bandLower[i] = p.yhat_lower;
            bandSpan[i] = Math.max(0, p.yhat_upper - p.yhat_lower);
            lowerVals[i] = p.yhat_lower;
            upperVals[i] = p.yhat_upper;
        });
    }

    const confPct = hasForecast.value ? Math.round((props.forecast.confidence ?? 0.8) * 100) : 0;
    const series = [
        {
            name: 'Facturado',
            type: 'bar',
            data: monto,
            itemStyle: { color: t.series[0], borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 38,
        },
        {
            name: 'Acumulado',
            type: 'line',
            yAxisIndex: 1,
            data: acumulado,
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            lineStyle: { color: t.series[1], width: 2 },
            itemStyle: { color: t.series[1] },
            areaStyle: { color: t.series[1], opacity: 0.08 },
        },
    ];

    const legendData = ['Facturado', 'Acumulado'];

    if (hasForecast.value) {
        legendData.push('Proyección');
        series.push(
            // Base transparente de la banda.
            {
                name: 'banda-base',
                type: 'line',
                data: bandLower,
                stack: 'conf',
                symbol: 'none',
                lineStyle: { opacity: 0 },
                areaStyle: { opacity: 0 },
                silent: true,
                tooltip: { show: false },
            },
            // Banda de confianza translúcida (se apila sobre la base).
            {
                name: `Banda ${confPct}%`,
                type: 'line',
                data: bandSpan,
                stack: 'conf',
                symbol: 'none',
                lineStyle: { opacity: 0 },
                areaStyle: { color: t.series[3], opacity: 0.16 },
                silent: true,
                tooltip: { show: false },
            },
            // Línea punteada de proyección.
            {
                name: 'Proyección',
                type: 'line',
                data: projLine,
                smooth: true,
                symbol: 'circle',
                symbolSize: 5,
                lineStyle: { color: t.series[3], width: 2, type: 'dashed' },
                itemStyle: { color: t.series[3] },
            },
        );
    }

    return {
        ...baseOption(t),
        legend: { data: legendData, textStyle: { color: t.textMuted }, top: 0, right: 0 },
        grid: { top: 36, left: 8, right: 16, bottom: 8, containLabel: true },
        tooltip: {
            ...baseOption(t).tooltip,
            formatter: (params) => {
                const idx = params[0].dataIndex;
                const find = (name) => params.find((p) => p.seriesName === name);
                const fmt = (v) => (v == null ? '—' : moneyFmt.format(v));
                const lines = [`<b>${labels[idx]}</b>`];

                const fact = find('Facturado');
                if (fact && fact.value != null) lines.push(`Facturado: ${fmt(fact.value)}`);
                const acc = find('Acumulado');
                if (acc && acc.value != null) lines.push(`Acumulado: ${fmt(acc.value)}`);

                const proj = find('Proyección');
                if (proj && proj.value != null && idx >= h) {
                    lines.push(`Proyección: ${fmt(proj.value)}`);
                    if (lowerVals[idx] != null) {
                        lines.push(`Rango ${confPct}%: ${fmt(lowerVals[idx])} – ${fmt(upperVals[idx])}`);
                    }
                }
                return lines.join('<br/>');
            },
        },
        xAxis: {
            type: 'category',
            data: labels,
            axisLine: { lineStyle: { color: t.border } },
            axisLabel: { color: t.textFaint, fontFamily: t.fontMono },
        },
        yAxis: [
            {
                type: 'value',
                splitLine: { lineStyle: { color: t.border } },
                axisLabel: { color: t.textFaint, formatter: compactMoney },
            },
            {
                type: 'value',
                splitLine: { show: false },
                axisLabel: { color: t.textFaint, formatter: compactMoney },
            },
        ],
        series,
    };
});
</script>

<template>
    <BaseChart :option="option" height="320px" />
</template>
