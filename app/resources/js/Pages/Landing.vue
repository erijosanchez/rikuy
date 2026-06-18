<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    phase: {
        type: String,
        default: '',
    },
});

const user = computed(() => usePage().props.auth?.user ?? null);

const pillars = [
    {
        tag: 'BI / Data',
        title: 'Modelado dimensional real',
        body: 'Hechos y dimensiones en PostgreSQL, métricas validadas y window functions. No gráficos de adorno: números que cuadran.',
    },
    {
        tag: 'Full-stack',
        title: 'Arquitectura de producto',
        body: 'Laravel 12 + Inertia/Vue, colas con Redis y un microservicio Python para forecasting. Multi-tenant desde el día uno.',
    },
    {
        tag: 'Producto',
        title: 'Pensado para el usuario',
        body: 'Sube tu CSV y mira tu negocio de un vistazo: KPIs, tendencias, alertas y un asistente que responde en español.',
    },
];
</script>

<template>
    <Head title="Business Intelligence as a Product" />

    <main class="page">
        <div class="bg-grid" aria-hidden="true"></div>

        <header class="nav">
            <span class="brand">
                <span class="brand__dot"></span>
                Rikuy
            </span>
            <nav class="nav__links">
                <span v-if="phase" class="phase-chip">{{ phase }}</span>
                <template v-if="user">
                    <Link href="/dashboard" class="nav-link">{{ user.name }} · Dashboard</Link>
                </template>
                <template v-else>
                    <Link href="/login" class="nav-link">Entrar</Link>
                    <Link href="/register" class="nav-link nav-link--cta">Crear workspace</Link>
                </template>
            </nav>
        </header>

        <section class="hero">
            <p class="eyebrow">Business Intelligence as a Product</p>
            <h1 class="title">
                Sube tus datos y mira <span class="title__accent">todo tu negocio</span>
                de un vistazo.
            </h1>
            <p class="lede">
                Plataforma de analítica comercial para PYMEs: KPIs, tendencias,
                alertas, proyecciones y un asistente que responde preguntas sobre
                tu data en español.
            </p>

            <div class="actions">
                <Link class="btn btn--primary" href="/demo">Ver el demo</Link>
                <a class="btn btn--ghost" href="#pilares">Cómo funciona</a>
            </div>
        </section>

        <section id="pilares" class="pillars">
            <article v-for="pillar in pillars" :key="pillar.tag" class="card">
                <span class="card__tag">{{ pillar.tag }}</span>
                <h2 class="card__title">{{ pillar.title }}</h2>
                <p class="card__body">{{ pillar.body }}</p>
            </article>
        </section>

        <footer class="footer">
            <span>Rikuy · <span class="mono">rikuy</span> (quechua: ver, observar)</span>
            <span class="footer__status">
                <span class="status-dot"></span> Cimientos en marcha
            </span>
        </footer>
    </main>
</template>

<style scoped>
.page {
    position: relative;
    min-height: 100vh;
    max-width: var(--rk-maxw);
    margin: 0 auto;
    padding: var(--rk-space-6) var(--rk-space-6) var(--rk-space-8);
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-16);
    overflow: hidden;
}

.bg-grid {
    position: fixed;
    inset: 0;
    z-index: -1;
    background-image:
        radial-gradient(circle at 50% -10%, rgba(46, 196, 182, 0.12), transparent 55%),
        linear-gradient(var(--rk-border) 1px, transparent 1px),
        linear-gradient(90deg, var(--rk-border) 1px, transparent 1px);
    background-size: 100% 100%, 48px 48px, 48px 48px;
    opacity: 0.5;
    mask-image: linear-gradient(180deg, #000 0%, transparent 80%);
}

/* --- Nav --- */
.nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-weight: 700;
    font-size: var(--rk-text-lg);
    letter-spacing: -0.01em;
}

.brand__dot {
    width: 10px;
    height: 10px;
    border-radius: var(--rk-radius-full);
    background: var(--rk-primary);
    box-shadow: var(--rk-glow-primary);
}

.nav__links {
    display: flex;
    align-items: center;
    gap: var(--rk-space-3);
}

.phase-chip {
    font-size: var(--rk-text-xs);
    font-family: var(--rk-font-mono);
    color: var(--rk-text-muted);
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    padding: var(--rk-space-1) var(--rk-space-3);
    border-radius: var(--rk-radius-full);
}

.nav-link {
    font-size: var(--rk-text-sm);
    font-weight: 600;
    color: var(--rk-text-muted);
    text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-3);
    border-radius: var(--rk-radius);
}

.nav-link:hover {
    color: var(--rk-text);
}

.nav-link--cta {
    color: var(--rk-primary-contrast);
    background: var(--rk-primary);
}

.nav-link--cta:hover {
    color: var(--rk-primary-contrast);
    background: var(--rk-primary-hover);
}

/* --- Hero --- */
.hero {
    max-width: 720px;
}

.eyebrow {
    margin: 0 0 var(--rk-space-4);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-sm);
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: var(--rk-primary);
}

.title {
    margin: 0;
    font-size: var(--rk-text-3xl);
    line-height: 1.08;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.title__accent {
    color: var(--rk-primary);
}

.lede {
    margin: var(--rk-space-6) 0 0;
    max-width: 560px;
    font-size: var(--rk-text-lg);
    line-height: 1.6;
    color: var(--rk-text-muted);
}

.actions {
    margin-top: var(--rk-space-8);
    display: flex;
    gap: var(--rk-space-3);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    padding: var(--rk-space-3) var(--rk-space-6);
    border-radius: var(--rk-radius);
    font-weight: 600;
    font-size: var(--rk-text-sm);
    text-decoration: none;
    transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease;
}

.btn:active {
    transform: translateY(1px);
}

.btn--primary {
    background: var(--rk-primary);
    color: var(--rk-primary-contrast);
    box-shadow: var(--rk-glow-primary);
}

.btn--primary:hover {
    background: var(--rk-primary-hover);
}

.btn--ghost {
    background: var(--rk-surface);
    color: var(--rk-text);
    border: 1px solid var(--rk-border-strong);
}

.btn--ghost:hover {
    background: var(--rk-surface-2);
}

/* --- Pilares --- */
.pillars {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: var(--rk-space-4);
}

.card {
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6);
    box-shadow: var(--rk-shadow);
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.card:hover {
    border-color: var(--rk-border-strong);
    transform: translateY(-2px);
}

.card__tag {
    display: inline-block;
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
    color: var(--rk-accent);
    margin-bottom: var(--rk-space-4);
}

.card__title {
    margin: 0 0 var(--rk-space-2);
    font-size: var(--rk-text-lg);
    font-weight: 600;
}

.card__body {
    margin: 0;
    font-size: var(--rk-text-sm);
    line-height: 1.6;
    color: var(--rk-text-muted);
}

/* --- Footer --- */
.footer {
    margin-top: auto;
    padding-top: var(--rk-space-6);
    border-top: 1px solid var(--rk-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--rk-space-3);
    font-size: var(--rk-text-sm);
    color: var(--rk-text-faint);
}

.mono {
    font-family: var(--rk-font-mono);
    color: var(--rk-text-muted);
}

.footer__status {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: var(--rk-radius-full);
    background: var(--rk-success);
    box-shadow: 0 0 8px var(--rk-success);
}
</style>
