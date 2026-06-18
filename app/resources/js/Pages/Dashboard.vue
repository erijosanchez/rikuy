<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    organization: { type: Object, required: true },
    datasets: { type: Array, default: () => [] },
    readOnly: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash?.status ?? null);

const numberFmt = new Intl.NumberFormat('es-PE');

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
