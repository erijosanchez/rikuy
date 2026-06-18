<script setup>
/* Layout de autenticación: panel de marca + tarjeta de formulario. */
import { Link } from '@inertiajs/vue3';
import BrandMark from './BrandMark.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const highlights = [
    'Modelado dimensional real en PostgreSQL',
    'Dashboards, alertas y forecasting',
    'Asistente que responde en español',
];
</script>

<template>
    <main class="auth">
        <!-- Panel de marca (oculto en móvil) -->
        <aside class="brandside">
            <div class="brandside__top">
                <BrandMark href="/" />
            </div>
            <div class="brandside__mid">
                <p class="brandside__eyebrow">Business Intelligence as a Product</p>
                <h2 class="brandside__title">Mira todo tu negocio de un vistazo.</h2>
                <ul class="hl">
                    <li v-for="h in highlights" :key="h">
                        <span class="hl__tick">✓</span>{{ h }}
                    </li>
                </ul>
            </div>
            <Link href="/demo" class="brandside__demo">Ver el demo sin registrarte →</Link>
        </aside>

        <!-- Tarjeta de formulario -->
        <section class="panel">
            <div class="panel__inner">
                <BrandMark href="/" class="panel__brand" />
                <h1 class="title">{{ title }}</h1>
                <p v-if="subtitle" class="subtitle">{{ subtitle }}</p>

                <slot />

                <div v-if="$slots.footer" class="footer">
                    <slot name="footer" />
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.auth {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    max-width: 1040px;
    margin: 0 auto;
    padding: var(--rk-space-6);
    gap: var(--rk-space-8);
    align-items: center;
}

@media (max-width: 860px) {
    .auth { grid-template-columns: 1fr; max-width: 440px; }
    .brandside { display: none; }
}

/* --- Panel de marca --- */
.brandside {
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: var(--rk-space-10);
    border-radius: var(--rk-radius-xl);
    border: 1px solid var(--rk-border);
    background:
        radial-gradient(600px 320px at 0% 0%, rgba(46, 196, 182, 0.12), transparent 60%),
        radial-gradient(500px 320px at 100% 100%, rgba(139, 108, 255, 0.12), transparent 55%),
        var(--rk-surface);
    box-shadow: var(--rk-shadow-lg);
}

.brandside__eyebrow {
    margin: 0 0 var(--rk-space-3);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: var(--rk-tracking-eyebrow);
    color: var(--rk-primary);
}

.brandside__title {
    margin: 0 0 var(--rk-space-6);
    font-size: var(--rk-text-2xl);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: var(--rk-tracking-tight);
}

.hl { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--rk-space-3); }
.hl li { display: flex; align-items: center; gap: var(--rk-space-3); font-size: var(--rk-text-sm); color: var(--rk-text-muted); }
.hl__tick {
    flex-shrink: 0;
    width: 20px; height: 20px;
    display: grid; place-items: center;
    border-radius: var(--rk-radius-full);
    background: var(--rk-primary-soft);
    color: var(--rk-primary);
    font-size: var(--rk-text-2xs);
}

.brandside__demo { color: var(--rk-primary); text-decoration: none; font-size: var(--rk-text-sm); font-weight: 600; }
.brandside__demo:hover { text-decoration: underline; }

/* --- Tarjeta --- */
.panel {
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-xl);
    box-shadow: var(--rk-shadow-lg);
}
.panel__inner { padding: var(--rk-space-10); }
.panel__brand { margin-bottom: var(--rk-space-8); }
@media (min-width: 861px) { .panel__brand { display: none; } }

.title { margin: 0; font-size: var(--rk-text-xl); font-weight: 700; letter-spacing: var(--rk-tracking-tight); }
.subtitle { margin: var(--rk-space-2) 0 var(--rk-space-8); color: var(--rk-text-muted); font-size: var(--rk-text-sm); }

.footer { margin-top: var(--rk-space-6); display: flex; flex-direction: column; gap: var(--rk-space-2); }
</style>
