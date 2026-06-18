<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppShell from '../Components/AppShell.vue';

// window.axios viene configurado en bootstrap.js (X-Requested-With → respuestas
// JSON de Laravel y envío automático del token XSRF en same-origin).
const axios = window.axios;

const props = defineProps({
    organization: { type: Object, required: true },
    readOnly: { type: Boolean, default: false },
    enabled: { type: Boolean, default: false },
    suggestions: { type: Array, default: () => [] },
});

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
        messages.value.push({ role: 'assistant', text: data.answer, steps: data.steps ?? [], ok: data.ok });
    } catch (e) {
        const msg = e.response?.data?.errors?.question?.[0] ?? 'No pude procesar la pregunta. Intenta de nuevo.';
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
</script>

<template>
    <Head :title="readOnly ? 'Asistente · Demo' : 'Asistente'" />

    <AppShell current="assistant" :read-only="readOnly" eyebrow="Asistente de datos" :title="`Pregunta sobre ${organization.name}`">
        <div v-if="!enabled" class="notice">
            <span class="notice__icon">⚠</span>
            El asistente no está configurado en este entorno (falta <code>GROQ_API_KEY</code>). Las preguntas no se responderán.
        </div>

        <div class="panel chat">
            <div ref="thread" class="thread">
                <div v-if="!messages.length" class="empty">
                    <div class="empty__badge">✦</div>
                    <p class="empty__lead">Hazme una pregunta en español sobre tus ventas.</p>
                    <p class="empty__sub">Respondo con números reales de tu data, nunca inventados.</p>
                    <div class="chips">
                        <button v-for="s in suggestions" :key="s" class="chip" :disabled="busy" @click="send(s)">{{ s }}</button>
                    </div>
                </div>

                <div v-for="(m, i) in messages" :key="i" class="msg" :class="`msg--${m.role}`">
                    <div class="bubble" :class="{ 'bubble--err': m.ok === false }">{{ m.text }}</div>
                    <div v-if="m.steps && m.steps.length" class="steps">
                        <span class="steps__label">Consultó:</span>
                        <span v-for="(s, k) in m.steps" :key="k" class="step-pill">{{ toolLabel(s.tool) }}</span>
                    </div>
                </div>

                <div v-if="busy" class="msg msg--assistant">
                    <div class="bubble bubble--typing">
                        <span class="dots"><i></i><i></i><i></i></span> Consultando la data…
                    </div>
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
                <button type="submit" class="composer__send" :disabled="busy || !enabled || !question.trim()">
                    {{ busy ? 'Pensando…' : 'Preguntar' }}
                </button>
            </form>
        </div>
    </AppShell>
</template>

<style scoped>
.notice {
    display: flex; align-items: center; gap: var(--rk-space-3);
    margin-bottom: var(--rk-space-4); padding: var(--rk-space-3) var(--rk-space-4);
    border-radius: var(--rk-radius); border: 1px solid color-mix(in srgb, var(--rk-warning) 45%, transparent);
    background: var(--rk-warning-soft); color: var(--rk-text); font-size: var(--rk-text-sm);
}
.notice__icon { color: var(--rk-warning); }
.notice code { font-family: var(--rk-font-mono); color: var(--rk-warning); }

.panel {
    background: var(--rk-surface); border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg); box-shadow: var(--rk-shadow);
}

.chat { display: flex; flex-direction: column; gap: var(--rk-space-4); min-height: 480px; padding: var(--rk-space-6); }
.thread { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: var(--rk-space-4); max-height: 58vh; }

.empty { margin: auto 0; text-align: center; color: var(--rk-text-muted); padding: var(--rk-space-8) 0; }
.empty__badge {
    width: 44px; height: 44px; margin: 0 auto var(--rk-space-4);
    display: grid; place-items: center; border-radius: var(--rk-radius-lg);
    background: var(--rk-accent-soft); color: var(--rk-accent); font-size: var(--rk-text-lg);
    border: 1px solid color-mix(in srgb, var(--rk-accent) 35%, transparent);
}
.empty__lead { margin: 0; color: var(--rk-text); font-size: var(--rk-text-base); font-weight: 600; }
.empty__sub { margin: var(--rk-space-1) 0 var(--rk-space-6); font-size: var(--rk-text-sm); }

.chips { display: flex; flex-wrap: wrap; gap: var(--rk-space-2); justify-content: center; }
.chip {
    font-size: var(--rk-text-sm); color: var(--rk-text); background: var(--rk-bg-soft);
    border: 1px solid var(--rk-border-strong); border-radius: var(--rk-radius-full);
    padding: var(--rk-space-2) var(--rk-space-4); cursor: pointer; transition: border-color var(--rk-transition), color var(--rk-transition);
}
.chip:hover { border-color: var(--rk-primary); color: var(--rk-primary); }
.chip:disabled { opacity: 0.5; cursor: not-allowed; }

.msg { display: flex; flex-direction: column; gap: var(--rk-space-2); max-width: 82%; }
.msg--user { align-self: flex-end; align-items: flex-end; }
.msg--assistant { align-self: flex-start; align-items: flex-start; }

.bubble { padding: var(--rk-space-3) var(--rk-space-4); border-radius: var(--rk-radius-lg); font-size: var(--rk-text-sm); line-height: 1.55; white-space: pre-wrap; }
.msg--user .bubble { background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); border-bottom-right-radius: var(--rk-radius-sm); }
.msg--assistant .bubble { background: var(--rk-surface-2); color: var(--rk-text); border: 1px solid var(--rk-border); border-bottom-left-radius: var(--rk-radius-sm); }
.bubble--err { border-color: color-mix(in srgb, var(--rk-danger) 50%, transparent); color: var(--rk-danger); }
.bubble--typing { color: var(--rk-text-faint); display: inline-flex; align-items: center; gap: var(--rk-space-2); }

.dots { display: inline-flex; gap: 3px; }
.dots i { width: 5px; height: 5px; border-radius: 50%; background: var(--rk-text-faint); animation: blink 1.2s infinite both; }
.dots i:nth-child(2) { animation-delay: 0.2s; }
.dots i:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink { 0%, 80%, 100% { opacity: 0.25; } 40% { opacity: 1; } }

.steps { display: flex; flex-wrap: wrap; align-items: center; gap: var(--rk-space-2); }
.steps__label { font-size: var(--rk-text-xs); color: var(--rk-text-faint); }
.step-pill {
    font-size: var(--rk-text-2xs); font-family: var(--rk-font-mono); color: var(--rk-accent);
    border: 1px solid color-mix(in srgb, var(--rk-accent) 35%, transparent); background: var(--rk-accent-soft);
    border-radius: var(--rk-radius-full); padding: 1px var(--rk-space-2);
}

.composer { display: flex; gap: var(--rk-space-3); }
.composer__input {
    flex: 1; background: var(--rk-bg-soft); border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius); padding: var(--rk-space-3) var(--rk-space-4);
    color: var(--rk-text); font-size: var(--rk-text-sm); font-family: inherit;
    transition: border-color var(--rk-transition), box-shadow var(--rk-transition);
}
.composer__input:focus { outline: none; border-color: var(--rk-primary); box-shadow: var(--rk-ring); }
.composer__input:disabled { opacity: 0.6; }
.composer__send {
    background: var(--rk-gradient-primary); color: var(--rk-primary-contrast); border: none;
    border-radius: var(--rk-radius); padding: var(--rk-space-3) var(--rk-space-5); font-weight: 700;
    font-size: var(--rk-text-sm); cursor: pointer; box-shadow: var(--rk-glow-primary);
    transition: transform var(--rk-transition), filter var(--rk-transition);
}
.composer__send:hover { filter: brightness(1.05); }
.composer__send:active { transform: translateY(1px); }
.composer__send:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
</style>
