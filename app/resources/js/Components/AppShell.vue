<script setup>
/*
 * Chrome común de la app autenticada / sandbox: topbar sticky con navegación,
 * identidad del usuario y cabecera de página. Unifica Dashboard, Alertas y
 * Asistente para que compartan exactamente el mismo marco.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from './BrandMark.vue';

const props = defineProps({
    current: { type: String, default: '' }, // 'dashboard' | 'alerts' | 'assistant'
    readOnly: { type: Boolean, default: false },
    eyebrow: { type: String, default: '' },
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash?.status ?? null);

const nav = computed(() => {
    const base = props.readOnly ? '/demo' : '';
    return [
        { key: 'dashboard', label: 'Dashboard', href: props.readOnly ? '/demo' : '/dashboard' },
        { key: 'alerts', label: 'Alertas', href: `${base}/alerts` },
        { key: 'assistant', label: 'Asistente', href: `${base}/assistant` },
    ];
});

const initials = computed(() => {
    const name = user.value?.name ?? '';
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join('') || '·';
});

const logout = () => router.post('/logout');
</script>

<template>
    <div class="shell">
        <header class="topbar">
            <div class="topbar__inner">
                <div class="topbar__left">
                    <BrandMark href="/" />
                    <span class="sep" aria-hidden="true"></span>
                    <nav class="nav">
                        <Link
                            v-for="item in nav"
                            :key="item.key"
                            :href="item.href"
                            class="nav__link"
                            :class="{ 'nav__link--on': current === item.key }"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div class="topbar__right">
                    <span v-if="readOnly" class="badge badge--demo">Sandbox</span>
                    <template v-if="user">
                        <span class="user">
                            <span class="user__avatar">{{ initials }}</span>
                            <span class="user__name">{{ user.name }}</span>
                        </span>
                        <button class="btn btn--ghost" @click="logout">Salir</button>
                    </template>
                    <template v-else>
                        <Link href="/login" class="btn btn--ghost">Entrar</Link>
                        <Link href="/register" class="btn btn--primary">Crear workspace</Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="content">
            <div class="pagehead">
                <div class="pagehead__titles">
                    <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
                    <h1 v-if="title" class="title">{{ title }}</h1>
                </div>
                <div class="pagehead__actions">
                    <slot name="actions" />
                    <span v-if="readOnly" class="badge badge--ro">Solo lectura</span>
                </div>
            </div>

            <transition name="flash">
                <div v-if="flash" class="flash">
                    <span class="flash__dot"></span>{{ flash }}
                </div>
            </transition>

            <slot />
        </main>
    </div>
</template>

<style scoped>
.shell { min-height: 100vh; display: flex; flex-direction: column; }

/* --- Topbar --- */
.topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: color-mix(in srgb, var(--rk-bg) 78%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--rk-border);
}

.topbar__inner {
    width: 100%;
    max-width: var(--rk-maxw);
    margin: 0 auto;
    height: var(--rk-topbar-h);
    padding: 0 var(--rk-space-6);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--rk-space-4);
}

.topbar__left { display: flex; align-items: center; gap: var(--rk-space-4); min-width: 0; }
.sep { width: 1px; height: 22px; background: var(--rk-border-strong); }

.nav { display: flex; align-items: center; gap: var(--rk-space-1); }

.nav__link {
    font-size: var(--rk-text-sm);
    font-weight: 500;
    color: var(--rk-text-muted);
    text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-3);
    border-radius: var(--rk-radius);
    transition: color var(--rk-transition), background var(--rk-transition);
}

.nav__link:hover { color: var(--rk-text); background: var(--rk-surface-2); }

.nav__link--on {
    color: var(--rk-text);
    background: var(--rk-surface-2);
    box-shadow: inset 0 0 0 1px var(--rk-border-strong);
}

.topbar__right { display: flex; align-items: center; gap: var(--rk-space-3); }

.user { display: inline-flex; align-items: center; gap: var(--rk-space-2); }
.user__avatar {
    width: 28px; height: 28px;
    display: grid; place-items: center;
    border-radius: var(--rk-radius-full);
    background: var(--rk-gradient-primary);
    color: var(--rk-primary-contrast);
    font-size: var(--rk-text-2xs);
    font-weight: 700;
}
.user__name { font-size: var(--rk-text-sm); color: var(--rk-text-muted); }
@media (max-width: 640px) { .user__name, .sep { display: none; } }

/* --- Botones compartidos --- */
.btn {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-size: var(--rk-text-sm);
    font-weight: 600;
    text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-4);
    border-radius: var(--rk-radius);
    cursor: pointer;
    border: 1px solid transparent;
    transition: transform var(--rk-transition), background var(--rk-transition), border-color var(--rk-transition);
}
.btn:active { transform: translateY(1px); }
.btn--ghost { background: var(--rk-surface); border-color: var(--rk-border-strong); color: var(--rk-text); }
.btn--ghost:hover { background: var(--rk-surface-2); }
.btn--primary { background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); box-shadow: var(--rk-glow-primary); }
.btn--primary:hover { filter: brightness(1.05); }

/* --- Contenido + cabecera de página --- */
.content {
    flex: 1;
    width: 100%;
    max-width: var(--rk-maxw);
    margin: 0 auto;
    padding: var(--rk-space-8) var(--rk-space-6) var(--rk-space-12);
}

.pagehead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--rk-space-4);
    margin-bottom: var(--rk-space-6);
    flex-wrap: wrap;
}

.eyebrow {
    margin: 0 0 var(--rk-space-2);
    font-family: var(--rk-font-mono);
    font-size: var(--rk-text-xs);
    text-transform: uppercase;
    letter-spacing: var(--rk-tracking-eyebrow);
    color: var(--rk-text-faint);
}

.title {
    margin: 0;
    font-size: var(--rk-text-2xl);
    font-weight: 700;
    letter-spacing: var(--rk-tracking-tight);
}

.pagehead__actions { display: flex; align-items: center; gap: var(--rk-space-3); }

.badge {
    flex-shrink: 0;
    font-size: var(--rk-text-2xs);
    font-family: var(--rk-font-mono);
    border-radius: var(--rk-radius-full);
    padding: var(--rk-space-1) var(--rk-space-3);
    border: 1px solid;
}
.badge--demo { color: var(--rk-warning); border-color: var(--rk-warning); }
.badge--ro { color: var(--rk-text-faint); border-color: var(--rk-border-strong); }

/* --- Flash --- */
.flash {
    display: flex;
    align-items: center;
    gap: var(--rk-space-3);
    margin-bottom: var(--rk-space-6);
    padding: var(--rk-space-3) var(--rk-space-4);
    border-radius: var(--rk-radius);
    background: var(--rk-primary-soft);
    border: 1px solid color-mix(in srgb, var(--rk-primary) 40%, transparent);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
}
.flash__dot { width: 8px; height: 8px; border-radius: var(--rk-radius-full); background: var(--rk-primary); box-shadow: 0 0 8px var(--rk-primary); }
.flash-enter-active { transition: opacity var(--rk-transition), transform var(--rk-transition); }
.flash-enter-from { opacity: 0; transform: translateY(-4px); }
</style>
