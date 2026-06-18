<script setup>
/*
 * Tendencia mensual: barras de monto facturado + línea de acumulado en eje
 * secundario. El acumulado proviene de la window function de la capa de métricas.
 */
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';
import { baseOption, chartTokens, compactMoney, moneyFmt } from '../../charts/theme.js';

const props = defineProps({
    trend: { type: Array, default: () => [] },
});

const option = computed(() => {
    const t = chartTokens();
    const labels = props.trend.map((r) => r.period);
    const monto = props.trend.map((r) => r.monto);
    const acumulado = props.trend.map((r) => r.acumulado);

    return {
        ...baseOption(t),
        legend: {
            data: ['Facturado', 'Acumulado'],
            textStyle: { color: t.textMuted },
            top: 0,
            right: 0,
        },
        grid: { top: 36, left: 8, right: 16, bottom: 8, containLabel: true },
        tooltip: {
            ...baseOption(t).tooltip,
            valueFormatter: (v) => (v == null ? '—' : moneyFmt.format(v)),
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
        series: [
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
        ],
    };
});
</script>

<template>
    <BaseChart :option="option" height="320px" />
</template>
