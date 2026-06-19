<script setup>
/*
 * Ranking genérico en barras horizontales (productos, proveedores, entidades…).
 * Recibe filas con { [labelKey], monto, participacion_pct } ya ordenadas desc.
 */
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';
import { baseOption, chartTokens, compactMoney, moneyFmt } from '../../charts/theme.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    labelKey: { type: String, required: true },
    colorIndex: { type: Number, default: 0 },
});

const option = computed(() => {
    const t = chartTokens();
    // ECharts dibuja el eje Y de abajo hacia arriba: invertir para que el #1 quede arriba.
    const rows = [...props.items].reverse();
    const color = t.series[props.colorIndex % t.series.length];

    return {
        ...baseOption(t),
        grid: { top: 12, left: 8, right: 28, bottom: 8, containLabel: true },
        tooltip: {
            ...baseOption(t).tooltip,
            trigger: 'item',
            valueFormatter: (v) => moneyFmt.format(v),
        },
        xAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: t.border } },
            axisLabel: { color: t.textFaint, formatter: compactMoney },
        },
        yAxis: {
            type: 'category',
            data: rows.map((r) => r[props.labelKey]),
            axisLine: { lineStyle: { color: t.border } },
            axisLabel: { color: t.textMuted, width: 150, overflow: 'truncate' },
        },
        series: [
            {
                type: 'bar',
                data: rows.map((r) => r.monto),
                itemStyle: { color, borderRadius: [0, 4, 4, 0] },
                barMaxWidth: 22,
                label: {
                    show: true,
                    position: 'right',
                    color: t.textFaint,
                    formatter: (d) => `${rows[d.dataIndex].participacion_pct}%`,
                },
            },
        ],
    };
});
</script>

<template>
    <BaseChart :option="option" height="320px" />
</template>
