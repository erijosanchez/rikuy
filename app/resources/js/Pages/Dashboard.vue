<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    organization: { type: Object, required: true },
    datasets: { type: Array, default: () => [] },
    readOnly: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const numberFmt = new Intl.NumberFormat('es-PE');

const logout = () => router.post('/logout');
</script>

<template>
    <Head :title="readOnly ? 'Demo' : 'Dashboard'" />

    <div class="shell">
        <header class="topbar">
            <Link href="/" class="brand">
                <span class="brand__dot"></span> Rikuy
            </Link>

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

            <section v-if="datasets.length" class="grid">
                <article v-for="ds in datasets" :key="ds.id" class="ds">
                    <div class="ds__top">
                        <h2 class="ds__name">{{ ds.name }}</h2>
                        <span class="ds__status">{{ ds.status }}</span>
                    </div>
                    <p class="ds__rows">{{ numberFmt.format(ds.rows) }} <span>filas</span></p>
                </article>
            </section>

            <section v-else class="empty">
                <h2>Aún no hay datasets</h2>
                <p>Tu workspace está vacío. En la Fase 2 podrás subir tu CSV y procesarlo.</p>
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

.topbar__right {
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
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--rk-border-strong);
    color: var(--rk-text);
}

.btn-primary {
    background: var(--rk-primary);
    border: none;
    color: var(--rk-primary-contrast);
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
    margin-bottom: var(--rk-space-8);
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
    color: var(--rk-success);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius-full);
    padding: 2px var(--rk-space-2);
}

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
