<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

// window.axios viene configurado en bootstrap.js (X-Requested-With → respuestas
// JSON de Laravel y envío automático del token XSRF en same-origin).
const axios = window.axios;

const props = defineProps({
    organization: { type: Object, required: true },
    readOnly: { type: Boolean, default: false },
    enabled: { type: Boolean, default: false },
    suggestions: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const dashboardHref = computed(() => (props.readOnly ? '/demo' : '/dashboard'));
const alertsHref = computed(() => (props.readOnly ? '/demo/alerts' : '/alerts'));

const question = ref('');
const busy = ref(false);
const messages = ref([]); // { role: 'user' | 'assistant', text, steps? }
const thread = ref(null);

const scrollDown = async () => {
    await nextTick();
    if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
};

const send = async (text) => {
    const q = (text ?? question.value).trim();
    if (!q || busy.value) return;

    messages.value.push({ role: 'user', text: q });
    question.value = '';
    busy.value = true;
    await scrollDown();

    try {
        const { data } = await axios.post(window.location.pathname, { question: q });
        messages.value.push({
            role: 'assistant',
            text: data.answer,
            steps: data.steps ?? [],
            ok: data.ok,
        });
    } catch (e) {
        const msg = e.response?.data?.errors?.question?.[0]
            ?? 'No pude procesar la pregunta. Intenta de nuevo.';
        messages.value.push({ role: 'assistant', text: msg, ok: false });
    } finally {
        busy.value = false;
        await scrollDown();
    }
};

const toolLabel = (name) =>
    ({
        periodo_reciente: 'periodo más reciente',
        resumen_ventas: 'resumen de ventas',
        top_productos: 'top de productos',
        ventas_por_region: 'ventas por región',
        tendencia_mensual: 'tendencia mensual',
        comparar_anios: 'comparar años',
    })[name] ?? name;

const logout = () => router.post('/logout');
</script>

<template>
    <Head :title="readOnly ? 'Asistente · Demo' : 'Asistente'" />

    <div class="shell">
        <header class="topbar">
            <Link href="/" class="brand">
                <span class="brand__dot"></span> Rikuy
            </Link>
            <nav class="nav">
                <Link :href="dashboardHref" class="nav__link">Dashboard</Link>
                <Link :href="alertsHref" class="nav__link">Alertas</Link>
                <span class="nav__link nav__link--on">Asistente</span>
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
                    <p class="eyebrow">Asistente de datos</p>
                    <h1 class="org">Pregunta sobre {{ organization.name }}</h1>
                </div>
                <span v-if="readOnly" class="ro-badge">Sandbox · solo lectura</span>
            </div>

            <div v-if="!enabled" class="notice">
                El asistente no está configurado en este entorno (falta
                <code>GROQ_API_KEY</code>). Las preguntas no se responderán.
            </div>

            <div class="panel chat">
                <div ref="thread" class="thread">
                    <div v-if="!messages.length" class="empty">
                        <p>Hazme una pregunta en español sobre tus ventas. Respondo con
                            números reales de tu data, nunca inventados.</p>
                        <div class="chips">
                            <button
                                v-for="s in suggestions"
                                :key="s"
                                class="chip"
                                :disabled="busy"
                                @click="send(s)"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <div v-for="(m, i) in messages" :key="i" class="msg" :class="`msg--${m.role}`">
                        <div class="bubble" :class="{ 'bubble--err': m.ok === false }">
                            {{ m.text }}
                        </div>
                        <div v-if="m.steps && m.steps.length" class="steps">
                            <span class="steps__label">Consultó:</span>
                            <span v-for="(s, k) in m.steps" :key="k" class="step-pill">
                                {{ toolLabel(s.tool) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="busy" class="msg msg--assistant">
                        <div class="bubble bubble--typing">Consultando la data…</div>
                    </div>
                </div>

                <form class="composer" @submit.prevent="send()">
                    <input
                        v-model="question"
                        type="text"
                        class="composer__input"
                        placeholder="Ej.: ¿cuál fue el top 5 de productos del último mes?"
                        :disabled="busy || !enabled"
                    />
                    <button type="submit" class="btn-primary" :disabled="busy || !enabled || !question.trim()">
                        {{ busy ? 'Pensando…' : 'Preguntar' }}
                    </button>
                </form>
            </div>
        </main>
    </div>
</template>

<style scoped>
.shell { min-height: 100vh; display: flex; flex-direction: column; }

.topbar {
    display: flex;
    align-items: center;
    gap: var(--rk-space-6);
    padding: var(--rk-space-4) var(--rk-space-6);
    border-bottom: 1px solid var(--rk-border);
    background: var(--rk-surface);
}

.brand { display: inline-flex; align-items: center; gap: var(--rk-space-2); font-weight: 700; color: var(--rk-text); text-decoration: none; }
.brand__dot { width: 10px; height: 10px; border-radius: var(--rk-radius-full); background: var(--rk-primary); box-shadow: var(--rk-glow-primary); }

.nav { display: flex; gap: var(--rk-space-4); }
.nav__link { font-size: var(--rk-text-sm); color: var(--rk-text-muted); text-decoration: none; }
.nav__link--on { color: var(--rk-primary); font-weight: 600; }

.topbar__right { margin-left: auto; display: flex; align-items: center; gap: var(--rk-space-3); }
.who { font-size: var(--rk-text-sm); color: var(--rk-text-muted); }

.btn-ghost, .btn-primary {
    font-size: var(--rk-text-sm); font-weight: 600; text-decoration: none;
    padding: var(--rk-space-2) var(--rk-space-4); border-radius: var(--rk-radius);
    cursor: pointer; border: none;
}
.btn-ghost { background: transparent; border: 1px solid var(--rk-border-strong); color: var(--rk-text); }
.btn-primary { background: var(--rk-primary); color: var(--rk-primary-contrast); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.content {
    flex: 1; width: 100%; max-width: var(--rk-maxw);
    margin: 0 auto; padding: var(--rk-space-8) var(--rk-space-6);
    display: flex; flex-direction: column;
}

.head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--rk-space-4); margin-bottom: var(--rk-space-6); }
.eyebrow { margin: 0 0 var(--rk-space-2); font-family: var(--rk-font-mono); font-size: var(--rk-text-xs); text-transform: uppercase; letter-spacing: 0.18em; color: var(--rk-text-faint); }
.org { margin: 0; font-size: var(--rk-text-2xl); font-weight: 700; letter-spacing: -0.02em; }

.ro-badge {
    flex-shrink: 0; font-size: var(--rk-text-xs); font-family: var(--rk-font-mono);
    color: var(--rk-warning); border: 1px solid var(--rk-warning);
    border-radius: var(--rk-radius-full); padding: var(--rk-space-1) var(--rk-space-3);
}

.notice {
    margin-bottom: var(--rk-space-4); padding: var(--rk-space-3) var(--rk-space-4);
    border-radius: var(--rk-radius); border: 1px solid var(--rk-warning);
    background: rgba(217, 164, 65, 0.1); color: var(--rk-text); font-size: var(--rk-text-sm);
}
.notice code { font-family: var(--rk-font-mono); color: var(--rk-warning); }

.panel {
    background: var(--rk-surface); border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg); padding: var(--rk-space-6);
}

.chat { display: flex; flex-direction: column; gap: var(--rk-space-4); flex: 1; min-height: 460px; }

.thread { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: var(--rk-space-4); max-height: 56vh; }

.empty { color: var(--rk-text-muted); font-size: var(--rk-text-sm); }
.empty p { margin: 0 0 var(--rk-space-4); }

.chips { display: flex; flex-wrap: wrap; gap: var(--rk-space-2); }
.chip {
    font-size: var(--rk-text-sm); color: var(--rk-text); background: var(--rk-bg);
    border: 1px solid var(--rk-border-strong); border-radius: var(--rk-radius-full);
    padding: var(--rk-space-2) var(--rk-space-4); cursor: pointer; text-align: left;
}
.chip:hover { border-color: var(--rk-primary); }
.chip:disabled { opacity: 0.5; cursor: not-allowed; }

.msg { display: flex; flex-direction: column; gap: var(--rk-space-2); max-width: 80%; }
.msg--user { align-self: flex-end; align-items: flex-end; }
.msg--assistant { align-self: flex-start; align-items: flex-start; }

.bubble {
    padding: var(--rk-space-3) var(--rk-space-4); border-radius: var(--rk-radius-lg);
    font-size: var(--rk-text-sm); line-height: 1.5; white-space: pre-wrap;
}
.msg--user .bubble { background: var(--rk-primary); color: var(--rk-primary-contrast); border-bottom-right-radius: var(--rk-radius-sm); }
.msg--assistant .bubble { background: var(--rk-surface-2); color: var(--rk-text); border: 1px solid var(--rk-border); border-bottom-left-radius: var(--rk-radius-sm); }
.bubble--err { border-color: var(--rk-danger); color: var(--rk-danger); }
.bubble--typing { color: var(--rk-text-faint); }

.steps { display: flex; flex-wrap: wrap; align-items: center; gap: var(--rk-space-2); }
.steps__label { font-size: var(--rk-text-xs); color: var(--rk-text-faint); }
.step-pill {
    font-size: var(--rk-text-xs); font-family: var(--rk-font-mono);
    color: var(--rk-accent); border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius-full); padding: 1px var(--rk-space-2);
}

.composer { display: flex; gap: var(--rk-space-3); }
.composer__input {
    flex: 1; background: var(--rk-bg); border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius); padding: var(--rk-space-3) var(--rk-space-4);
    color: var(--rk-text); font-size: var(--rk-text-sm);
}
.composer__input:disabled { opacity: 0.6; }
</style>
