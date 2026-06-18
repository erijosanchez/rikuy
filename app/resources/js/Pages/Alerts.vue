<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppShell from '../Components/AppShell.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    readOnly: { type: Boolean, default: false },
    rules: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
});

const moneyFmt = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 });
const numberFmt = new Intl.NumberFormat('es-PE');

const measureLabel = (m) => (m === 'ordenes' ? 'Órdenes' : 'Ventas');
const dirLabel = (d) => (d === 'rise' ? 'suben' : 'caen');
const fmtValue = (measure, value) => (measure === 'ordenes' ? `${numberFmt.format(value)} órdenes` : moneyFmt.format(value));
const fmtPct = (pct) => `${pct > 0 ? '+' : ''}${pct}%`;
const fmtDate = (iso) => new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

const form = useForm({ name: '', measure: 'monto', direction: 'drop', threshold_pct: 20 });

const submit = () => form.post('/alerts', { preserveScroll: true, onSuccess: () => form.reset('name') });
const toggle = (rule) => router.patch(`/alerts/${rule.id}`, { enabled: !rule.enabled }, { preserveScroll: true });
const remove = (rule) => {
    if (confirm(`¿Eliminar la regla "${rule.name}"?`)) {
        router.delete(`/alerts/${rule.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="readOnly ? 'Alertas · Demo' : 'Alertas'" />

    <AppShell current="alerts" :read-only="readOnly" eyebrow="Alertas y anomalías" :title="organization.name">
        <div class="cols">
            <!-- Reglas configuradas -->
            <section class="panel">
                <h2 class="panel__title">Reglas</h2>

                <form v-if="!readOnly" class="rule-form" @submit.prevent="submit">
                    <p class="rule-form__line">
                        Avísame cuando
                        <select v-model="form.measure" class="inline-select">
                            <option value="monto">las ventas</option>
                            <option value="ordenes">las órdenes</option>
                        </select>
                        <select v-model="form.direction" class="inline-select">
                            <option value="drop">caigan</option>
                            <option value="rise">suban</option>
                        </select>
                        al menos
                        <input v-model.number="form.threshold_pct" type="number" step="0.1" min="0.1" class="inline-num" />
                        % vs. el mes anterior.
                    </p>
                    <div class="rule-form__row">
                        <input v-model="form.name" type="text" class="text-input" placeholder="Nombre (opcional)" />
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Guardando…' : 'Crear regla' }}
                        </button>
                    </div>
                    <p v-if="form.errors.threshold_pct" class="error">{{ form.errors.threshold_pct }}</p>
                </form>

                <ul v-if="rules.length" class="rules">
                    <li v-for="r in rules" :key="r.id" class="rule" :class="{ 'rule--off': !r.enabled }">
                        <div class="rule__main">
                            <span class="rule__name">{{ r.name }}</span>
                            <span class="rule__cond">{{ measureLabel(r.measure) }} {{ dirLabel(r.direction) }} ≥ {{ r.threshold_pct }}% mensual</span>
                        </div>
                        <div v-if="!readOnly" class="rule__actions">
                            <button class="pill" :class="r.enabled ? 'pill--on' : 'pill--off'" @click="toggle(r)">
                                {{ r.enabled ? 'Activa' : 'Pausada' }}
                            </button>
                            <button class="link-danger" @click="remove(r)" aria-label="Eliminar regla">Eliminar</button>
                        </div>
                        <span v-else class="pill" :class="r.enabled ? 'pill--on' : 'pill--off'">{{ r.enabled ? 'Activa' : 'Pausada' }}</span>
                    </li>
                </ul>
                <p v-else class="muted">Aún no hay reglas configuradas.</p>
            </section>

            <!-- Disparos registrados -->
            <section class="panel">
                <h2 class="panel__title">Disparos recientes</h2>
                <ul v-if="events.length" class="events">
                    <li v-for="e in events" :key="e.id" class="event">
                        <div class="event__head">
                            <span class="event__badge" :class="e.change_pct < 0 ? 'event__badge--down' : 'event__badge--up'">{{ fmtPct(e.change_pct) }}</span>
                            <span class="event__period">{{ e.period }}</span>
                            <span class="event__time">{{ fmtDate(e.created_at) }}</span>
                        </div>
                        <p class="event__msg">{{ e.message }}</p>
                        <p class="event__detail">
                            {{ fmtValue(e.measure, e.observed) }}
                            <span class="event__vs">vs</span>
                            {{ fmtValue(e.measure, e.previous) }} el mes previo
                        </p>
                    </li>
                </ul>
                <div v-else class="empty">
                    <p class="muted">Sin disparos todavía. Las reglas activas se evalúan a diario.</p>
                </div>
            </section>
        </div>
    </AppShell>
</template>

<style scoped>
.cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(330px, 1fr)); gap: var(--rk-space-5); align-items: start; }

.panel {
    background: var(--rk-surface); border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg); padding: var(--rk-space-6); box-shadow: var(--rk-shadow);
}
.panel__title { margin: 0 0 var(--rk-space-5); font-size: var(--rk-text-base); font-weight: 600; }

.rule-form {
    border: 1px solid var(--rk-border); border-radius: var(--rk-radius);
    padding: var(--rk-space-4); margin-bottom: var(--rk-space-5);
    background: var(--rk-bg-soft); display: flex; flex-direction: column; gap: var(--rk-space-3);
}
.rule-form__line { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-text-muted); line-height: 2.1; }
.rule-form__row { display: flex; gap: var(--rk-space-2); }

.inline-select, .inline-num, .text-input {
    background: var(--rk-surface-2); border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius-sm); padding: var(--rk-space-1) var(--rk-space-2);
    color: var(--rk-text); font-size: var(--rk-text-sm); font-family: inherit;
    transition: border-color var(--rk-transition), box-shadow var(--rk-transition);
}
.inline-select:focus, .inline-num:focus, .text-input:focus { outline: none; border-color: var(--rk-primary); box-shadow: var(--rk-ring); }
.inline-num { width: 64px; font-family: var(--rk-font-mono); }
.text-input { flex: 1; padding: var(--rk-space-2) var(--rk-space-3); }

.btn-primary {
    background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); border: none;
    border-radius: var(--rk-radius); padding: var(--rk-space-2) var(--rk-space-4);
    font-size: var(--rk-text-sm); font-weight: 600; cursor: pointer; box-shadow: var(--rk-glow-primary);
    transition: transform var(--rk-transition), filter var(--rk-transition); white-space: nowrap;
}
.btn-primary:hover { filter: brightness(1.05); }
.btn-primary:active { transform: translateY(1px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.rules, .events { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--rk-space-3); }

.rule {
    display: flex; align-items: center; justify-content: space-between; gap: var(--rk-space-3);
    padding: var(--rk-space-3) var(--rk-space-4); border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius); background: var(--rk-bg-soft);
    transition: border-color var(--rk-transition);
}
.rule:hover { border-color: var(--rk-border-strong); }
.rule--off { opacity: 0.6; }
.rule__main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.rule__name { font-size: var(--rk-text-sm); font-weight: 600; color: var(--rk-text); }
.rule__cond { font-size: var(--rk-text-xs); color: var(--rk-text-faint); font-family: var(--rk-font-mono); }
.rule__actions { display: flex; align-items: center; gap: var(--rk-space-3); flex-shrink: 0; }

.pill {
    font-size: var(--rk-text-2xs); font-family: var(--rk-font-mono); border-radius: var(--rk-radius-full);
    padding: 2px var(--rk-space-3); border: 1px solid var(--rk-border-strong); background: transparent; cursor: pointer;
}
.pill--on { color: var(--rk-success); border-color: color-mix(in srgb, var(--rk-success) 55%, transparent); background: var(--rk-success-soft); }
.pill--off { color: var(--rk-text-faint); }

.link-danger { background: none; border: none; cursor: pointer; color: var(--rk-danger); font-size: var(--rk-text-xs); padding: 0; }
.link-danger:hover { text-decoration: underline; }

.event {
    padding: var(--rk-space-3) var(--rk-space-4); border: 1px solid var(--rk-border);
    border-left: 3px solid var(--rk-warning); border-radius: var(--rk-radius); background: var(--rk-bg-soft);
}
.event__head { display: flex; align-items: center; gap: var(--rk-space-3); margin-bottom: var(--rk-space-2); }
.event__badge { font-family: var(--rk-font-mono); font-size: var(--rk-text-2xs); font-weight: 700; border-radius: var(--rk-radius-sm); padding: 1px var(--rk-space-2); }
.event__badge--down { color: var(--rk-danger); background: var(--rk-danger-soft); }
.event__badge--up { color: var(--rk-success); background: var(--rk-success-soft); }
.event__period { font-family: var(--rk-font-mono); font-size: var(--rk-text-xs); color: var(--rk-text-muted); }
.event__time { margin-left: auto; font-size: var(--rk-text-xs); color: var(--rk-text-faint); }
.event__msg { margin: 0 0 var(--rk-space-1); font-size: var(--rk-text-sm); color: var(--rk-text); }
.event__detail { margin: 0; font-size: var(--rk-text-xs); color: var(--rk-text-faint); font-family: var(--rk-font-mono); }
.event__vs { color: var(--rk-text-faint); }

.empty { border: 1px dashed var(--rk-border-strong); border-radius: var(--rk-radius); padding: var(--rk-space-8); text-align: center; }
.muted { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-text-faint); }
.error { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-danger); }
</style>
