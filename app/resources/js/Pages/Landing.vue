<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from '../Components/BrandMark.vue';

defineProps({
    phase: { type: String, default: '' },
});

const user = computed(() => usePage().props.auth?.user ?? null);

const pillars = [
    {
        tag: 'BI / Data',
        title: 'Modelado dimensional real',
        body: 'Hechos y dimensiones en PostgreSQL, métricas validadas y window functions. No gráficos de adorno: números que cuadran contra la fuente.',
    },
    {
        tag: 'Full-stack',
        title: 'Arquitectura de producto',
        body: 'Laravel 12 + Inertia/Vue, colas con Redis y un microservicio Python para forecasting. Multi-tenant desde el día uno.',
    },
    {
        tag: 'Producto',
        title: 'Pensado para el usuario',
        body: 'Sube tu CSV y mira tu negocio de un vistazo: KPIs, tendencias, alertas, proyecciones, asistente en español y reporte PDF.',
    },
];

const caps = ['Dashboard ejecutivo', 'Alertas', 'Forecasting', 'Asistente NL', 'Reporte PDF'];

// Datos decorativos del preview (no son data real).
const previewBars = [38, 52, 44, 66, 58, 79, 71, 92];
</script>

<template>
    <Head title="Business Intelligence as a Product" />

    <div class="page">
        <header class="nav">
            <BrandMark href="/" />
            <nav class="nav__links">
                <span v-if="phase" class="phase-chip">{{ phase }}</span>
                <template v-if="user">
                    <Link href="/dashboard" class="nav-link nav-link--cta">{{ user.name }} · Dashboard</Link>
                </template>
                <template v-else>
                    <Link href="/login" class="nav-link">Entrar</Link>
                    <Link href="/register" class="nav-link nav-link--cta">Crear workspace</Link>
                </template>
            </nav>
        </header>

        <section class="hero">
            <div class="hero__copy">
                <span class="eyebrow">Business Intelligence as a Product</span>
                <h1 class="title">
                    Sube tus datos y mira <span class="title__accent">todo tu negocio</span> de un vistazo.
                </h1>
                <p class="lede">
                    Plataforma de analítica comercial para PYMEs: KPIs, tendencias, alertas,
                    proyecciones y un asistente que responde preguntas sobre tu data en español.
                </p>
                <div class="actions">
                    <Link class="btn btn--primary" href="/demo">Ver el demo en vivo →</Link>
                    <a class="btn btn--ghost" href="#pilares">Cómo funciona</a>
                </div>
                <ul class="caps" aria-label="Capacidades">
                    <li v-for="cap in caps" :key="cap">{{ cap }}</li>
                </ul>
            </div>

            <!-- Preview decorativo del producto -->
            <div class="preview" aria-hidden="true">
                <div class="preview__bar">
                    <span class="preview__dot"></span><span class="preview__dot"></span><span class="preview__dot"></span>
                    <span class="preview__url">rikuy.app/demo</span>
                </div>
                <div class="preview__body">
                    <div class="preview__kpis">
                        <div class="pk"><span class="pk__l">Facturado</span><span class="pk__v">S/ 6.1M</span><span class="pk__d pk__d--up">▲ 12%</span></div>
                        <div class="pk"><span class="pk__l">Órdenes</span><span class="pk__v">180</span></div>
                        <div class="pk"><span class="pk__l">Ticket</span><span class="pk__v">S/ 34k</span></div>
                    </div>
                    <div class="preview__chart">
                        <div v-for="(b, i) in previewBars" :key="i" class="cbar" :style="{ height: b + '%' }"></div>
                        <svg class="preview__line" viewBox="0 0 100 40" preserveAspectRatio="none">
                            <polyline points="0,30 14,26 28,28 42,18 56,20 70,10 84,12 100,4" fill="none" stroke="var(--rk-accent)" stroke-width="1.5" />
                        </svg>
                    </div>
                </div>
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
            <span class="footer__status"><span class="status-dot"></span> MVP en vivo</span>
        </footer>
    </div>
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
}

/* --- Nav --- */
.nav { display: flex; align-items: center; justify-content: space-between; gap: var(--rk-space-4); }
.nav__links { display: flex; align-items: center; gap: var(--rk-space-3); flex-wrap: wrap; }

.phase-chip {
    font-size: var(--rk-text-2xs); font-family: var(--rk-font-mono); color: var(--rk-text-muted);
    background: var(--rk-surface); border: 1px solid var(--rk-border); padding: var(--rk-space-1) var(--rk-space-3); border-radius: var(--rk-radius-full);
}

.nav-link {
    font-size: var(--rk-text-sm); font-weight: 600; color: var(--rk-text-muted); text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-4); border-radius: var(--rk-radius); transition: color var(--rk-transition), background var(--rk-transition);
}
.nav-link:hover { color: var(--rk-text); }
.nav-link--cta { color: var(--rk-primary-contrast); background: var(--rk-gradient-primary); box-shadow: var(--rk-glow-primary); }
.nav-link--cta:hover { color: var(--rk-primary-contrast); filter: brightness(1.05); }

/* --- Hero --- */
.hero {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: var(--rk-space-10);
    align-items: center;
}
@media (max-width: 900px) { .hero { grid-template-columns: 1fr; } .preview { display: none; } }

.hero__copy { max-width: 600px; }

.eyebrow {
    display: inline-block; margin-bottom: var(--rk-space-4);
    font-family: var(--rk-font-mono); font-size: var(--rk-text-sm); text-transform: uppercase;
    letter-spacing: var(--rk-tracking-eyebrow); color: var(--rk-primary);
}

.title { margin: 0; font-size: var(--rk-text-4xl); line-height: 1.06; font-weight: 800; letter-spacing: var(--rk-tracking-tight); }
.title__accent {
    background: var(--rk-gradient-brand); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}

.lede { margin: var(--rk-space-6) 0 0; max-width: 540px; font-size: var(--rk-text-lg); line-height: 1.6; color: var(--rk-text-muted); }

.actions { margin-top: var(--rk-space-8); display: flex; gap: var(--rk-space-3); flex-wrap: wrap; }
.btn {
    display: inline-flex; align-items: center; padding: var(--rk-space-3) var(--rk-space-6);
    border-radius: var(--rk-radius); font-weight: 600; font-size: var(--rk-text-sm); text-decoration: none;
    transition: transform var(--rk-transition), filter var(--rk-transition), background var(--rk-transition), border-color var(--rk-transition);
}
.btn:active { transform: translateY(1px); }
.btn--primary { background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); box-shadow: var(--rk-glow-primary); }
.btn--primary:hover { filter: brightness(1.05); }
.btn--ghost { background: var(--rk-surface); color: var(--rk-text); border: 1px solid var(--rk-border-strong); }
.btn--ghost:hover { background: var(--rk-surface-2); }

.caps { list-style: none; margin: var(--rk-space-8) 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: var(--rk-space-2); }
.caps li {
    font-family: var(--rk-font-mono); font-size: var(--rk-text-2xs); color: var(--rk-text-muted);
    border: 1px solid var(--rk-border); border-radius: var(--rk-radius-full); padding: var(--rk-space-1) var(--rk-space-3); background: var(--rk-surface);
}

/* --- Preview decorativo --- */
.preview {
    border: 1px solid var(--rk-border); border-radius: var(--rk-radius-lg); overflow: hidden;
    background: var(--rk-surface); box-shadow: var(--rk-shadow-lg);
    transform: perspective(1200px) rotateY(-7deg) rotateX(2deg);
}
.preview__bar { display: flex; align-items: center; gap: var(--rk-space-2); padding: var(--rk-space-3) var(--rk-space-4); border-bottom: 1px solid var(--rk-border); background: var(--rk-bg-soft); }
.preview__dot { width: 9px; height: 9px; border-radius: 50%; background: var(--rk-border-strong); }
.preview__url { margin-left: var(--rk-space-2); font-family: var(--rk-font-mono); font-size: var(--rk-text-2xs); color: var(--rk-text-faint); }
.preview__body { padding: var(--rk-space-5); }
.preview__kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--rk-space-3); margin-bottom: var(--rk-space-5); }
.pk { background: var(--rk-bg-soft); border: 1px solid var(--rk-border); border-radius: var(--rk-radius); padding: var(--rk-space-3); display: flex; flex-direction: column; gap: 2px; }
.pk__l { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--rk-text-faint); }
.pk__v { font-family: var(--rk-font-mono); font-weight: 800; font-size: var(--rk-text-base); color: var(--rk-primary); }
.pk__d { font-size: 9px; font-family: var(--rk-font-mono); }
.pk__d--up { color: var(--rk-success); }

.preview__chart { position: relative; display: flex; align-items: flex-end; gap: 5px; height: 110px; padding-top: var(--rk-space-4); }
.cbar { flex: 1; background: var(--rk-gradient-primary); border-radius: 3px 3px 0 0; opacity: 0.85; }
.preview__line { position: absolute; inset: 0; width: 100%; height: 100%; }

/* --- Pilares --- */
.pillars { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--rk-space-4); }
.card {
    background: var(--rk-surface); border: 1px solid var(--rk-border); border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-6); box-shadow: var(--rk-shadow); transition: border-color var(--rk-transition), transform var(--rk-transition);
}
.card:hover { border-color: var(--rk-border-strong); transform: translateY(-3px); }
.card__tag {
    display: inline-block; font-family: var(--rk-font-mono); font-size: var(--rk-text-2xs); color: var(--rk-accent);
    border: 1px solid color-mix(in srgb, var(--rk-accent) 30%, transparent); background: var(--rk-accent-soft);
    border-radius: var(--rk-radius-full); padding: 2px var(--rk-space-2); margin-bottom: var(--rk-space-4);
}
.card__title { margin: 0 0 var(--rk-space-2); font-size: var(--rk-text-lg); font-weight: 600; }
.card__body { margin: 0; font-size: var(--rk-text-sm); line-height: 1.6; color: var(--rk-text-muted); }

/* --- Footer --- */
.footer {
    margin-top: auto; padding-top: var(--rk-space-6); border-top: 1px solid var(--rk-border);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--rk-space-3);
    font-size: var(--rk-text-sm); color: var(--rk-text-faint);
}
.mono { font-family: var(--rk-font-mono); color: var(--rk-text-muted); }
.footer__status { display: inline-flex; align-items: center; gap: var(--rk-space-2); }
.status-dot { width: 8px; height: 8px; border-radius: var(--rk-radius-full); background: var(--rk-success); box-shadow: 0 0 8px var(--rk-success); }
</style>
