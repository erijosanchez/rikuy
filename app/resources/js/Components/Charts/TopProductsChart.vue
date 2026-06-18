<script setup>
/*
 * Top productos por monto facturado, en barras horizontales (el formato más
 * legible para rankings con nombres largos).
 */
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';
import { baseOption, chartTokens, compactMoney, moneyFmt } from '../../charts/theme.js';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const option = computed(() => {
    const t = chartTokens();
    // ECharts dibuja el eje Y de abajo hacia arriba: invertir para que el #1 quede arriba.
    const rows = [...props.products].reverse();

    return {
        ...baseOption(t),
        grid: { top: 12, left: 8, right: 24, bottom: 8, containLabel: true },
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
            data: rows.map((p) => p.producto),
            axisLine: { lineStyle: { color: t.border } },
            axisLabel: {
                color: t.textMuted,
                width: 160,
                overflow: 'truncate',
            },
        },
        series: [
            {
                type: 'bar',
                data: rows.map((p) => p.monto),
                itemStyle: { color: t.series[0], borderRadius: [0, 4, 4, 0] },
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
