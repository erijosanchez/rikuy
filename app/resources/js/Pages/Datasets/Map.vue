<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    dataset: { type: Object, required: true },
    headers: { type: Array, default: () => [] },
    canonicalFields: { type: Array, default: () => [] },
    suggested: { type: Object, default: () => ({}) },
    sample: { type: Array, default: () => [] },
});

// Pre-rellena con las sugerencias automáticas.
const initialMap = {};
props.canonicalFields.forEach((f) => {
    initialMap[f.key] = props.suggested[f.key] ?? '';
});

const form = useForm({ map: initialMap });

const submit = () => {
    form.post(`/datasets/${props.dataset.id}/map`);
};
</script>

<template>
    <Head title="Mapear columnas" />

    <div class="shell">
        <header class="topbar">
            <Link href="/dashboard" class="brand">
                <span class="brand__dot"></span> Rikuy
            </Link>
            <Link href="/dashboard" class="back">← Volver al dashboard</Link>
        </header>

        <main class="content">
            <p class="eyebrow">Ingesta · paso 2</p>
            <h1 class="title">Mapear columnas</h1>
            <p class="subtitle">
                Dataset <strong>{{ dataset.name }}</strong>
                <span v-if="dataset.original_filename">· {{ dataset.original_filename }}</span>
            </p>

            <p v-if="form.errors.map" class="error">{{ form.errors.map }}</p>

            <form @submit.prevent="submit" class="mapper">
                <div v-for="field in canonicalFields" :key="field.key" class="row">
                    <div class="row__label">
                        <span class="row__name">{{ field.label }}</span>
                        <span v-if="field.required" class="req">obligatorio</span>
                        <span class="type">{{ field.type }}</span>
                    </div>
                    <select v-model="form.map[field.key]" class="select">
                        <option value="">— sin mapear —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Encolando…' : 'Procesar dataset' }}
                </button>
            </form>

            <section v-if="sample.length" class="preview">
                <h2 class="preview__title">Vista previa</h2>
                <div class="preview__scroll">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th v-for="h in headers" :key="h">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in sample" :key="i">
                                <td v-for="h in headers" :key="h">{{ r[h] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.shell { min-height: 100vh; }

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--rk-space-4) var(--rk-space-6);
    border-bottom: 1px solid var(--rk-border);
    background: var(--rk-surface);
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

.back {
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
    text-decoration: none;
}

.content {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    padding: var(--rk-space-8) var(--rk-space-6);
}

.eyebrow {
    margin: 0 0 var(--rk-space-2);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: var(--rk-text-faint);
}

.title {
    margin: 0;
    font-size: var(--rk-text-2xl);
    font-weight: 700;
}

.subtitle {
    margin: var(--rk-space-2) 0 var(--rk-space-6);
    color: var(--rk-text-muted);
    font-size: var(--rk-text-sm);
}

.error {
    color: var(--rk-danger);
    font-size: var(--rk-text-sm);
    margin-bottom: var(--rk-space-4);
}

.mapper {
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-3);
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
}

.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--rk-space-4);
}

.row__label {
    display: flex;
    align-items: center;
    gap: var(--rk-space-2);
}

.row__name {
    font-weight: 600;
    font-size: var(--rk-text-sm);
}

.req {
    font-size: var(--rk-text-xs);
    color: var(--rk-warning);
}

.type {
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
    color: var(--rk-text-faint);
}

.select {
    min-width: 240px;
    background: var(--rk-bg);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius);
    padding: var(--rk-space-2) var(--rk-space-3);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
}

.btn-primary {
    align-self: flex-start;
    margin-top: var(--rk-space-2);
    background: var(--rk-primary);
    color: var(--rk-primary-contrast);
    border: none;
    border-radius: var(--rk-radius);
    padding: var(--rk-space-3) var(--rk-space-6);
    font-weight: 600;
    font-size: var(--rk-text-sm);
    cursor: pointer;
}

.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.preview {
    margin-top: var(--rk-space-8);
}

.preview__title {
    margin: 0 0 var(--rk-space-3);
    font-size: var(--rk-text-base);
    font-weight: 600;
}

.preview__scroll {
    overflow-x: auto;
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius);
}

.tbl {
    border-collapse: collapse;
    width: 100%;
    font-size: var(--rk-text-xs);
}

.tbl th,
.tbl td {
    text-align: left;
    padding: var(--rk-space-2) var(--rk-space-3);
    border-bottom: 1px solid var(--rk-border);
    white-space: nowrap;
}

.tbl th {
    background: var(--rk-surface-2);
    color: var(--rk-text-muted);
    font-family: var(--rk-font-mono);
    font-weight: 500;
}
</style>
