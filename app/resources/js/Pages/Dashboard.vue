<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    filters: { type: Object, default: () => ({ years: [], selectedYear: null }) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash?.status ?? null);

const numberFmt = new Intl.NumberFormat('es-PE');
const moneyFmt = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 });

const hasMetrics = computed(() => (props.kpis?.ordenes ?? 0) > 0);

// Filtro de periodo: recarga solo las props de métricas preservando el scroll.
const selectedYear = computed(() => props.filters?.selectedYear ?? null);

const selectYear = (year) => {
    if (year === selectedYear.value) return;
    router.get(
        window.location.pathname,
        year ? { year } : {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['kpis', 'trend', 'topProducts', 'byRegion', 'comparison', 'filters'],
        },
    );
};

// Variación del comparativo (año vs año anterior) para el KPI de facturación.
const delta = computed(() => {
    const c = props.comparison;
    if (!c || c.variacion_pct === null || !c.tiene_previo) return null;
    return {
        pct: c.variacion_pct,
        up: c.variacion_pct >= 0,
        previo: c.year_previo,
    };
});

const periodLabel = computed(() => (selectedYear.value ? String(selectedYear.value) : 'Todo el historial'));

const alertsHref = computed(() => (props.readOnly ? '/demo/alerts' : '/alerts'));

const statusLabel = {
    mapping: 'Por mapear',
    processing: 'Procesando',
    ready: 'Listo',
    failed: 'Falló',
};

const upload = useForm({ file: null, name: '' });

const submitUpload = () => {
    upload.post('/datasets', {
        onSuccess: () => upload.reset(),
    });
};

const logout = () => router.post('/logout');
</script>

<template>
    <Head :title="readOnly ? 'Demo' : 'Dashboard'" />

    <div class="shell">
        <header class="topbar">
            <Link href="/" class="brand">
                <span class="brand__dot"></span> Rikuy
            </Link>

            <nav class="nav">
                <span class="nav__link nav__link--on">Dashboard</span>
                <Link :href="alertsHref" class="nav__link">Alertas</Link>
            </nav>

            <div class="topbar__right">
                <span v-if="user" class="who">{{ user.name }}</span>
                <button v-if="user" class="btn-ghost" @click="logout">Salir</button>
                <template v-else>
                    <Link href="/login" class="btn-ghost">Entrar</Link>
                    <Link href="/register" class="btn-primary">Crear workspace</Link>
                </template>
            </div>
        </header>

        <main class="content">
            <div class="head">
                <div>
                    <p class="eyebrow">Workspace</p>
                    <h1 class="org">{{ organization.name }}</h1>
                </div>
                <span v-if="readOnly" class="ro-badge">Sandbox · solo lectura</span>
            </div>

            <div v-if="flash" class="flash">{{ flash }}</div>

            <!-- Filtro de periodo (Fase 4): recorta todas las métricas al año. -->
            <section v-if="hasMetrics && filters.years.length" class="periodbar">
                <span class="periodbar__label">Periodo</span>
                <div class="chips">
                    <button
                        class="chip"
                        :class="{ 'chip--on': selectedYear === null }"
                        @click="selectYear(null)"
                    >
                        Todo
                    </button>
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
                <div class="kpi">
                    <span class="kpi__label">Total facturado</span>
                    <span class="kpi__value">{{ moneyFmt.format(kpis.monto) }}</span>
                    <span
                        v-if="delta"
                        class="kpi__delta"
                        :class="delta.up ? 'kpi__delta--up' : 'kpi__delta--down'"
                    >
                        {{ delta.up ? '▲' : '▼' }} {{ Math.abs(delta.pct) }}% vs {{ delta.previo }}
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

            <!-- Tendencia mensual (ECharts): barras de monto + línea de acumulado -->
            <section v-if="hasMetrics && trend.length" class="panel chartpanel">
                <div class="panel__head">
                    <h2 class="panel__title">Tendencia mensual</h2>
                    <span class="panel__meta">{{ periodLabel }}</span>
                </div>
                <TrendChart :trend="trend" />
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
                    <input
                        type="file"
                        accept=".csv,.txt,.xlsx"
                        class="file"
                        @input="upload.file = $event.target.files[0]"
                    />
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
                        <span class="ds__status" :class="`ds__status--${ds.status}`">
                            {{ statusLabel[ds.status] ?? ds.status }}
                        </span>
                    </div>

                    <p v-if="ds.status === 'ready'" class="ds__rows">
                        {{ numberFmt.format(ds.rows) }} <span>filas</span>
                    </p>
                    <p v-else-if="ds.status === 'failed'" class="ds__error">{{ ds.error }}</p>
                    <Link
                        v-else-if="ds.status === 'mapping' && !readOnly"
                        :href="`/datasets/${ds.id}/map`"
                        class="ds__action"
                    >
                        Mapear columnas →
                    </Link>
                    <p v-else class="ds__muted">En cola…</p>
                </article>
            </section>

            <section v-else class="empty">
                <h2>Aún no hay datasets</h2>
                <p v-if="!readOnly">Sube tu primer CSV arriba para procesarlo.</p>
                <p v-else>Este sandbox aún no tiene datos cargados.</p>
            </section>
        </main>
    </div>
</template>

<style scoped>
.shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.topbar {
    display: flex;
    align-items: center;
    gap: var(--rk-space-6);
    padding: var(--rk-space-4) var(--rk-space-6);
    border-bottom: 1px solid var(--rk-border);
    background: var(--rk-surface);
}

.nav {
    display: flex;
    gap: var(--rk-space-4);
}

.nav__link {
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
    text-decoration: none;
}

.nav__link--on {
    color: var(--rk-primary);
    font-weight: 600;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-weight: 700;
    color: var(--rk-text);
    text-decoration: none;
}

.brand__dot {
    width: 10px;
    height: 10px;
    border-radius: var(--rk-radius-full);
    background: var(--rk-primary);
    box-shadow: var(--rk-glow-primary);
}

.topbar__right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: var(--rk-space-3);
}

.who {
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.btn-ghost,
.btn-primary {
    font-size: var(--rk-text-sm);
    font-weight: 600;
    text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-4);
    border-radius: var(--rk-radius);
    cursor: pointer;
    border: none;
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--rk-border-strong);
    color: var(--rk-text);
}

.btn-primary {
    background: var(--rk-primary);
    color: var(--rk-primary-contrast);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.content {
    flex: 1;
    width: 100%;
    max-width: var(--rk-maxw);
    margin: 0 auto;
    padding: var(--rk-space-8) var(--rk-space-6);
}

.head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--rk-space-4);
    margin-bottom: var(--rk-space-6);
}

.eyebrow {
    margin: 0 0 var(--rk-space-2);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: var(--rk-text-faint);
}

.org {
    margin: 0;
    font-size: var(--rk-text-2xl);
    font-weight: 700;
    letter-spacing: -0.02em;
}

.ro-badge {
    flex-shrink: 0;
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
    color: var(--rk-warning);
    border: 1px solid var(--rk-warning);
    border-radius: var(--rk-radius-full);
    padding: var(--rk-space-1) var(--rk-space-3);
}

.flash {
    margin-bottom: var(--rk-space-6);
    padding: var(--rk-space-3) var(--rk-space-4);
    border-radius: var(--rk-radius);
    background: rgba(46, 196, 182, 0.12);
    border: 1px solid var(--rk-primary);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--rk-space-4);
    margin-bottom: var(--rk-space-4);
}

.kpi {
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
    box-shadow: var(--rk-shadow);
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-2);
}

.kpi__label {
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--rk-text-faint);
}

.kpi__value {
    font-size: var(--rk-text-2xl);
    font-weight: 700;
    font-family: var(--rk-font-mono);
    color: var(--rk-primary);
    letter-spacing: -0.02em;
}

.kpi__delta {
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
    font-weight: 600;
}

.kpi__delta--up { color: var(--rk-success); }
.kpi__delta--down { color: var(--rk-danger); }

/* --- Filtro de periodo --- */
.periodbar {
    display: flex;
    align-items: center;
    gap: var(--rk-space-3);
    margin-bottom: var(--rk-space-4);
    flex-wrap: wrap;
}

.periodbar__label {
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--rk-text-faint);
}

.chips {
    display: flex;
    gap: var(--rk-space-2);
    flex-wrap: wrap;
}

.chip {
    font-size: var(--rk-text-sm);
    font-family: var(--rk-font-mono);
    color: var(--rk-text-muted);
    background: var(--rk-surface);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius-full);
    padding: var(--rk-space-1) var(--rk-space-4);
    cursor: pointer;
    transition: all 0.12s ease;
}

.chip:hover {
    color: var(--rk-text);
    border-color: var(--rk-primary);
}

.chip--on {
    color: var(--rk-primary-contrast);
    background: var(--rk-primary);
    border-color: var(--rk-primary);
}

.breakdowns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--rk-space-4);
    margin-bottom: var(--rk-space-6);
}

.panel {
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
}

.panel__title {
    margin: 0 0 var(--rk-space-4);
    font-size: var(--rk-text-base);
    font-weight: 600;
}

.chartpanel {
    margin-bottom: var(--rk-space-4);
}

.panel__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--rk-space-3);
    margin-bottom: var(--rk-space-4);
}

.panel__head .panel__title {
    margin: 0;
}

.panel__meta {
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
    color: var(--rk-text-faint);
}

.panel__empty {
    margin: 0;
    padding: var(--rk-space-8) 0;
    text-align: center;
    color: var(--rk-text-faint);
    font-size: var(--rk-text-sm);
}

.rank {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-3);
}

.rank li {
    display: flex;
    align-items: center;
    gap: var(--rk-space-3);
    font-size: var(--rk-text-sm);
}

.rank__pos {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    border-radius: var(--rk-radius-full);
    background: var(--rk-surface-2);
    color: var(--rk-text-muted);
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
}

.rank__name {
    flex: 1;
    color: var(--rk-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rank__val {
    flex-shrink: 0;
    color: var(--rk-text-muted);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
}

.uploader {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--rk-space-4);
    flex-wrap: wrap;
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
    margin-bottom: var(--rk-space-4);
}

.uploader__title {
    margin: 0 0 var(--rk-space-1);
    font-size: var(--rk-text-base);
    font-weight: 600;
}

.uploader__hint {
    margin: 0;
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.uploader__form {
    display: flex;
    align-items: center;
    gap: var(--rk-space-2);
    flex-wrap: wrap;
}

.file {
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.text-input {
    background: var(--rk-bg);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius);
    padding: var(--rk-space-2) var(--rk-space-3);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
}

.error {
    color: var(--rk-danger);
    font-size: var(--rk-text-sm);
    margin: 0 0 var(--rk-space-4);
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--rk-space-4);
}

.ds {
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
    box-shadow: var(--rk-shadow);
}

.ds__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--rk-space-2);
    margin-bottom: var(--rk-space-4);
}

.ds__name {
    margin: 0;
    font-size: var(--rk-text-base);
    font-weight: 600;
}

.ds__status {
    flex-shrink: 0;
    font-size: var(--rk-text-xs);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius-full);
    padding: 2px var(--rk-space-2);
    color: var(--rk-text-muted);
}

.ds__status--ready { color: var(--rk-success); border-color: var(--rk-success); }
.ds__status--processing { color: var(--rk-info); border-color: var(--rk-info); }
.ds__status--mapping { color: var(--rk-warning); border-color: var(--rk-warning); }
.ds__status--failed { color: var(--rk-danger); border-color: var(--rk-danger); }

.ds__rows {
    margin: 0;
    font-size: var(--rk-text-xl);
    font-weight: 700;
    font-family: var(--rk-font-mono);
    color: var(--rk-primary);
}

.ds__rows span {
    font-size: var(--rk-text-sm);
    font-weight: 400;
    color: var(--rk-text-faint);
}

.ds__action {
    color: var(--rk-primary);
    text-decoration: none;
    font-size: var(--rk-text-sm);
    font-weight: 600;
}

.ds__error {
    margin: 0;
    font-size: var(--rk-text-sm);
    color: var(--rk-danger);
}

.ds__muted {
    margin: 0;
    font-size: var(--rk-text-sm);
    color: var(--rk-text-faint);
}

.empty {
    border: 1px dashed var(--rk-border-strong);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-12);
    text-align: center;
    color: var(--rk-text-muted);
}

.empty h2 {
    margin: 0 0 var(--rk-space-2);
    color: var(--rk-text);
}

.empty p {
    margin: 0;
    font-size: var(--rk-text-sm);
}
</style>
