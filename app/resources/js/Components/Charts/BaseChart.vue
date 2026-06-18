<script setup>
/*
 * Envoltura mínima de ECharts: inicializa, aplica la opción, redimensiona con el
 * contenedor y libera la instancia al desmontar. Importa solo los módulos que se
 * usan (tree-shaking) para no cargar todo ECharts.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    MarkLineComponent,
    CanvasRenderer,
]);

const props = defineProps({
    option: { type: Object, required: true },
    height: { type: String, default: '320px' },
});

const el = ref(null);
let chart = null;
let observer = null;

const render = () => {
    if (!chart) return;
    // notMerge: true para que al cambiar de periodo no queden series viejas.
    chart.setOption(props.option, true);
};

onMounted(() => {
    chart = echarts.init(el.value, null, { renderer: 'canvas' });
    render();

    observer = new ResizeObserver(() => chart && chart.resize());
    observer.observe(el.value);
});

watch(() => props.option, render, { deep: true });

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
    if (chart) chart.dispose();
    chart = null;
});
</script>

<template>
    <div ref="el" class="chart" :style="{ height }"></div>
</template>

<style scoped>
.chart {
    width: 100%;
}
</style>
