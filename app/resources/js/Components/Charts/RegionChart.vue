<script setup>
/*
 * Participación de monto por región, en donut. Agrupa la cola larga en "Otras"
 * para mantener legible la lectura ejecutiva.
 */
import { computed } from 'vue';
import BaseChart from './BaseChart.vue';
import { baseOption, chartTokens, moneyFmt } from '../../charts/theme.js';

const props = defineProps({
    regions: { type: Array, default: () => [] },
    max: { type: Number, default: 6 },
});

const option = computed(() => {
    const t = chartTokens();

    const top = props.regions.slice(0, props.max);
    const rest = props.regions.slice(props.max);
    const data = top.map((r) => ({ name: r.region, value: r.monto }));

    if (rest.length) {
        data.push({
            name: 'Otras',
            value: Math.round(rest.reduce((sum, r) => sum + r.monto, 0) * 100) / 100,
        });
    }

    return {
        ...baseOption(t),
        tooltip: {
            ...baseOption(t).tooltip,
            trigger: 'item',
            formatter: (p) => `${p.name}<br/>${moneyFmt.format(p.value)} · ${p.percent}%`,
        },
        legend: {
            type: 'scroll',
            orient: 'vertical',
            right: 0,
            top: 'center',
            textStyle: { color: t.textMuted },
        },
        series: [
            {
                type: 'pie',
                radius: ['46%', '72%'],
                center: ['38%', '50%'],
                avoidLabelOverlap: true,
                itemStyle: { borderColor: t.surface, borderWidth: 2 },
                label: { show: false },
                data,
            },
        ],
    };
});
</script>

<template>
    <BaseChart :option="option" height="320px" />
</template>
