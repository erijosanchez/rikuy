<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '../Components/AppShell.vue';
import TrendChart from '../Components/Charts/TrendChart.vue';
import TopProductsChart from '../Components/Charts/TopProductsChart.vue';
import RegionChart from '../Components/Charts/RegionChart.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    datasets: { type: Array, default: () => [] },
    readOnly: { type: Boolean, default: false },
    kpis: { type: Object, default: () => ({}) },
    trend: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
    byRegion: { type: Array, default: () => [] },
    comparison: { type: Object, default: null },
    forecast: { type: Object, default: null },
    filters: { type: Object, default: () => ({ years: [], selectedYear: null }) },
});

const numberFmt = new Intl.NumberFormat('es-PE');
const moneyFmt = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 });

const hasMetrics = computed(() => (props.kpis?.ordenes ?? 0) > 0);

// Filtro de periodo: recarga solo las props de métricas preservando el scroll.
const selectedYear = computed(() => props.filters?.selectedYear ?? null);

const selectYear = (year) => {
    if (year === selectedYear.value) return;
    router.get(window.location.pathname, year ? { year } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['kpis', 'trend', 'topProducts', 'byRegion', 'comparison', 'forecast', 'filters'],
    });
};

// Variación del comparativo (año vs año anterior) para el KPI de facturación.
const delta = computed(() => {
    const c = props.comparison;
    if (!c || c.variacion_pct === null || !c.tiene_previo) return null;
    return { pct: c.variacion_pct, up: c.variacion_pct >= 0, previo: c.year_previo };
});

const periodLabel = computed(() => (selectedYear.value ? String(selectedYear.value) : 'Todo el historial'));
const reportHref = computed(() => (props.readOnly ? '/demo/report/executive.pdf' : '/report/executive.pdf'));

const forecastMeta = computed(() => {
    const f = props.forecast;
    if (!f || !f.points?.length) return null;
    const conf = Math.round((f.confidence ?? 0.8) * 100);
    const label = f.model?.startsWith('ets-seasonal')
        ? 'ETS estacional'
        : f.model?.startsWith('ets')
            ? 'ETS tendencia'
            : 'Proyección base';
    return `Proyección ${f.points.length} meses · ${label} · banda ${conf}%`;
});

const statusLabel = { mapping: 'Por mapear', processing: 'Procesando', ready: 'Listo', failed: 'Falló' };

const upload = useForm({ file: null, name: '' });
const submitUpload = () => upload.post('/datasets', { onSuccess: () => upload.reset() });
</script>

<template>
    <Head :title="readOnly ? 'Demo' : 'Dashboard'" />

    <AppShell current="dashboard" :read-only="readOnly" eyebrow="Workspace" :title="organization.name">
        <template #actions>
            <a v-if="hasMetrics" :href="reportHref" class="btn-report" target="_blank" rel="noopener">
                <span aria-hidden="true">↓</span> Reporte PDF
            </a>
        </template>

        <!-- Filtro de periodo (Fase 4): recorta todas las métricas al año. -->
        <section v-if="hasMetrics && filters.years.length" class="periodbar">
            <span class="periodbar__label">Periodo</span>
            <div class="chips">
                <button class="chip" :class="{ 'chip--on': selectedYear === null }" @click="selectYear(null)">Todo</button>
                <button
                    v-for="y in filters.years"
                    :key="y"
                    class="chip"
                    :class="{ 'chip--on': selectedYear === y }"
                    @click="selectYear(y)"
                >
                    {{ y }}
                </button>
            </div>
        </section>

        <!-- KPIs (Fase 3): números reales desde el esquema estrella -->
        <section v-if="hasMetrics" class="kpis">
            <div class="kpi kpi--accent">
                <span class="kpi__label">Total facturado</span>
                <span class="kpi__value">{{ moneyFmt.format(kpis.monto) }}</span>
                <span v-if="delta" class="kpi__delta" :class="delta.up ? 'is-up' : 'is-down'">
                    {{ delta.up ? '▲' : '▼' }} {{ Math.abs(delta.pct) }}% <span class="kpi__delta-note">vs {{ delta.previo }}</span>
                </span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Órdenes</span>
                <span class="kpi__value">{{ numberFmt.format(kpis.ordenes) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Ticket promedio</span>
                <span class="kpi__value">{{ moneyFmt.format(kpis.ticket_promedio) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Unidades</span>
                <span class="kpi__value">{{ numberFmt.format(kpis.unidades) }}</span>
            </div>
        </section>

        <!-- Tendencia mensual (ECharts) + banda de proyección (Fase 6). -->
        <section v-if="hasMetrics && trend.length" class="panel chartpanel">
            <div class="panel__head">
                <h2 class="panel__title">Tendencia mensual</h2>
                <span class="panel__meta">
                    <template v-if="forecastMeta">{{ forecastMeta }}</template>
                    <template v-else>{{ periodLabel }}</template>
                </span>
            </div>
            <TrendChart :trend="trend" :forecast="forecast" />
        </section>

        <section v-if="hasMetrics" class="breakdowns">
            <div class="panel chartpanel">
                <h2 class="panel__title">Top productos</h2>
                <TopProductsChart v-if="topProducts.length" :products="topProducts" />
                <p v-else class="panel__empty">Sin productos en este periodo.</p>
            </div>
            <div class="panel chartpanel">
                <h2 class="panel__title">Por región</h2>
                <RegionChart v-if="byRegion.length" :regions="byRegion" />
                <p v-else class="panel__empty">Sin regiones en este periodo.</p>
            </div>
        </section>

        <!-- Subida de datasets (oculta en el sandbox demo) -->
        <section v-if="!readOnly" class="uploader">
            <div>
                <h2 class="uploader__title">Subir dataset</h2>
                <p class="uploader__hint">CSV o Excel (.xlsx), hasta 10 MB. Luego mapeas las columnas.</p>
            </div>
            <form class="uploader__form" @submit.prevent="submitUpload">
                <input type="file" accept=".csv,.txt,.xlsx" class="file" @input="upload.file = $event.target.files[0]" />
                <input v-model="upload.name" type="text" class="text-input" placeholder="Nombre (opcional)" />
                <button type="submit" class="btn-primary" :disabled="upload.processing || !upload.file">
                    {{ upload.processing ? 'Subiendo…' : 'Subir' }}
                </button>
            </form>
        </section>
        <p v-if="upload.errors.file" class="error">{{ upload.errors.file }}</p>

        <section v-if="datasets.length" class="grid">
            <article v-for="ds in datasets" :key="ds.id" class="ds">
                <div class="ds__top">
                    <h2 class="ds__name">{{ ds.name }}</h2>
                    <span class="ds__status" :class="`ds__status--${ds.status}`">{{ statusLabel[ds.status] ?? ds.status }}</span>
                </div>
                <p v-if="ds.status === 'ready'" class="ds__rows">{{ numberFmt.format(ds.rows) }} <span>filas</span></p>
                <p v-else-if="ds.status === 'failed'" class="ds__error">{{ ds.error }}</p>
                <Link v-else-if="ds.status === 'mapping' && !readOnly" :href="`/datasets/${ds.id}/map`" class="ds__action">Mapear columnas →</Link>
                <p v-else class="ds__muted">En cola…</p>
            </article>
        </section>

        <section v-else class="empty">
            <h2>Aún no hay datasets</h2>
            <p v-if="!readOnly">Sube tu primer CSV arriba para procesarlo.</p>
            <p v-else>Este sandbox aún no tiene datos cargados.</p>
        </section>
    </AppShell>
</template>

<style scoped>
.btn-report {
    display: inline-flex; align-items: center; gap: var(--rk-space-2);
    font-size: var(--rk-text-sm); font-weight: 600; text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-4); border-radius: var(--rk-radius);
    border: 1px solid var(--rk-border-strong); color: var(--rk-text); background: var(--rk-surface);
    transition: border-color var(--rk-transition), color var(--rk-transition);
}
.btn-report:hover { border-color: var(--rk-primary); color: var(--rk-primary); }

/* --- Filtro de periodo --- */
.periodbar { display: flex; align-items: center; gap: var(--rk-space-3); margin-bottom: var(--rk-space-5); flex-wrap: wrap; }
.periodbar__label { font-size: var(--rk-text-xs); text-transform: uppercase; letter-spacing: var(--rk-tracking-wide); color: var(--rk-text-faint); }
.chips { display: flex; gap: var(--rk-space-2); flex-wrap: wrap; }
.chip {
    font-size: var(--rk-text-sm); font-family: var(--rk-font-mono); color: var(--rk-text-muted);
    background: var(--rk-surface); border: 1px solid var(--rk-border-strong); border-radius: var(--rk-radius-full);
    padding: var(--rk-space-1) var(--rk-space-4); cursor: pointer; transition: all var(--rk-transition);
}
.chip:hover { color: var(--rk-text); border-color: var(--rk-primary); }
.chip--on { color: var(--rk-primary-contrast); background: var(--rk-gradient-primary); border-color: transparent; }

/* --- KPIs --- */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--rk-space-4); margin-bottom: var(--rk-space-5); }
.kpi {
    position: relative; overflow: hidden;
    background: var(--rk-surface); border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg); padding: var(--rk-space-6);
    box-shadow: var(--rk-shadow); display: flex; flex-direction: column; gap: var(--rk-space-2);
    transition: border-color var(--rk-transition), transform var(--rk-transition);
}
.kpi:hover { border-color: var(--rk-border-strong); transform: translateY(-2px); }
.kpi::before {
    content: ''; position: absolute; inset: 0 0 auto 0; height: 2px;
    background: var(--rk-gradient-surface);
}
.kpi--accent::before { background: var(--rk-gradient-primary); opacity: 0.9; }
.kpi__label { font-size: var(--rk-text-xs); text-transform: uppercase; letter-spacing: var(--rk-tracking-wide); color: var(--rk-text-faint); }
.kpi__value { font-size: var(--rk-text-2xl); font-weight: 800; font-family: var(--rk-font-mono); color: var(--rk-text); letter-spacing: var(--rk-tracking-tight); }
.kpi--accent .kpi__value { color: var(--rk-primary); }
.kpi__delta { font-size: var(--rk-text-xs); font-family: var(--rk-font-mono); font-weight: 700; }
.kpi__delta-note { color: var(--rk-text-faint); font-weight: 400; }
.kpi__delta.is-up { color: var(--rk-success); }
.kpi__delta.is-down { color: var(--rk-danger); }

/* --- Paneles / charts --- */
.panel { background: var(--rk-surface); border: 1px solid var(--rk-border); border-radius: var(--rk-radius-lg); padding: var(--rk-space-6); box-shadow: var(--rk-shadow); }
.chartpanel { margin-bottom: var(--rk-space-5); }
.panel__head { display: flex; align-items: baseline; justify-content: space-between; gap: var(--rk-space-3); margin-bottom: var(--rk-space-5); }
.panel__title { margin: 0; font-size: var(--rk-text-base); font-weight: 600; }
.panel__meta { font-size: var(--rk-text-xs); font-family: var(--rk-font-mono); color: var(--rk-text-faint); }
.panel__empty { margin: 0; padding: var(--rk-space-8) 0; text-align: center; color: var(--rk-text-faint); font-size: var(--rk-text-sm); }

.breakdowns { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--rk-space-5); margin-bottom: var(--rk-space-5); }

/* --- Uploader --- */
.uploader {
    display: flex; align-items: center; justify-content: space-between; gap: var(--rk-space-4); flex-wrap: wrap;
    background: var(--rk-surface); border: 1px solid var(--rk-border); border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6); margin-bottom: var(--rk-space-4);
}
.uploader__title { margin: 0 0 var(--rk-space-1); font-size: var(--rk-text-base); font-weight: 600; }
.uploader__hint { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-text-muted); }
.uploader__form { display: flex; align-items: center; gap: var(--rk-space-2); flex-wrap: wrap; }
.file { font-size: var(--rk-text-sm); color: var(--rk-text-muted); }
.file::file-selector-button {
    background: var(--rk-surface-2); border: 1px solid var(--rk-border-strong); color: var(--rk-text);
    border-radius: var(--rk-radius-sm); padding: var(--rk-space-2) var(--rk-space-3); margin-right: var(--rk-space-3);
    font-size: var(--rk-text-sm); cursor: pointer;
}
.text-input {
    background: var(--rk-bg-soft); border: 1px solid var(--rk-border-strong); border-radius: var(--rk-radius);
    padding: var(--rk-space-2) var(--rk-space-3); color: var(--rk-text); font-size: var(--rk-text-sm); font-family: inherit;
    transition: border-color var(--rk-transition), box-shadow var(--rk-transition);
}
.text-input:focus { outline: none; border-color: var(--rk-primary); box-shadow: var(--rk-ring); }

.btn-primary {
    background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); border: none;
    border-radius: var(--rk-radius); padding: var(--rk-space-2) var(--rk-space-4); font-weight: 600;
    font-size: var(--rk-text-sm); cursor: pointer; box-shadow: var(--rk-glow-primary);
    transition: transform var(--rk-transition), filter var(--rk-transition);
}
.btn-primary:hover { filter: brightness(1.05); }
.btn-primary:active { transform: translateY(1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

.error { color: var(--rk-danger); font-size: var(--rk-text-sm); margin: 0 0 var(--rk-space-4); }

/* --- Datasets --- */
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--rk-space-4); }
.ds { background: var(--rk-surface); border: 1px solid var(--rk-border); border-radius: var(--rk-radius-lg); padding: var(--rk-space-6); box-shadow: var(--rk-shadow); transition: border-color var(--rk-transition); }
.ds:hover { border-color: var(--rk-border-strong); }
.ds__top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--rk-space-2); margin-bottom: var(--rk-space-4); }
.ds__name { margin: 0; font-size: var(--rk-text-base); font-weight: 600; }
.ds__status { flex-shrink: 0; font-size: var(--rk-text-2xs); border: 1px solid var(--rk-border-strong); border-radius: var(--rk-radius-full); padding: 2px var(--rk-space-2); color: var(--rk-text-muted); }
.ds__status--ready { color: var(--rk-success); border-color: color-mix(in srgb, var(--rk-success) 55%, transparent); background: var(--rk-success-soft); }
.ds__status--processing { color: var(--rk-info); border-color: color-mix(in srgb, var(--rk-info) 55%, transparent); background: var(--rk-info-soft); }
.ds__status--mapping { color: var(--rk-warning); border-color: color-mix(in srgb, var(--rk-warning) 55%, transparent); background: var(--rk-warning-soft); }
.ds__status--failed { color: var(--rk-danger); border-color: color-mix(in srgb, var(--rk-danger) 55%, transparent); background: var(--rk-danger-soft); }
.ds__rows { margin: 0; font-size: var(--rk-text-xl); font-weight: 700; font-family: var(--rk-font-mono); color: var(--rk-primary); }
.ds__rows span { font-size: var(--rk-text-sm); font-weight: 400; color: var(--rk-text-faint); }
.ds__action { color: var(--rk-primary); text-decoration: none; font-size: var(--rk-text-sm); font-weight: 600; }
.ds__action:hover { text-decoration: underline; }
.ds__error { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-danger); }
.ds__muted { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-text-faint); }

.empty { border: 1px dashed var(--rk-border-strong); border-radius: var(--rk-radius-lg); padding: var(--rk-space-12); text-align: center; color: var(--rk-text-muted); }
.empty h2 { margin: 0 0 var(--rk-space-2); color: var(--rk-text); }
.empty p { margin: 0; font-size: var(--rk-text-sm); }
</style>
