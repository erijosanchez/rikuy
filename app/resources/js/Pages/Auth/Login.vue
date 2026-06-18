<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Entrar" />

    <main class="auth">
        <div class="card">
            <Link href="/" class="brand">
                <span class="brand__dot"></span> Rikuy
            </Link>
            <h1 class="title">Entrar</h1>
            <p class="subtitle">Accede a tu workspace.</p>

            <form @submit.prevent="submit" class="form">
                <label class="field">
                    <span class="field__label">Email</span>
                    <input v-model="form.email" type="email" autocomplete="email" required class="input" />
                    <span v-if="form.errors.email" class="error">{{ form.errors.email }}</span>
                </label>

                <label class="field">
                    <span class="field__label">Contraseña</span>
                    <input v-model="form.password" type="password" autocomplete="current-password" required class="input" />
                    <span v-if="form.errors.password" class="error">{{ form.errors.password }}</span>
                </label>

                <label class="remember">
                    <input v-model="form.remember" type="checkbox" />
                    <span>Recordarme</span>
                </label>

                <button type="submit" class="btn" :disabled="form.processing">Entrar</button>
            </form>

            <p class="alt">
                ¿No tienes cuenta?
                <Link href="/register" class="link">Crea tu workspace</Link>
            </p>
            <p class="alt">
                <Link href="/demo" class="link">Ver el demo sin registrarte →</Link>
            </p>
        </div>
    </main>
</template>

<style scoped>
.auth {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: var(--rk-space-6);
}

.card {
    width: 100%;
    max-width: 400px;
    background: var(--rk-surface);
    border: 1px solid var(--rk-border);
    border-radius: var(--rk-radius-lg);
    padding: var(--rk-space-8);
    box-shadow: var(--rk-shadow);
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-weight: 700;
    color: var(--rk-text);
    text-decoration: none;
    margin-bottom: var(--rk-space-6);
}

.brand__dot {
    width: 10px;
    height: 10px;
    border-radius: var(--rk-radius-full);
    background: var(--rk-primary);
    box-shadow: var(--rk-glow-primary);
}

.title {
    margin: 0;
    font-size: var(--rk-text-xl);
    font-weight: 700;
}

.subtitle {
    margin: var(--rk-space-2) 0 var(--rk-space-6);
    color: var(--rk-text-muted);
    font-size: var(--rk-text-sm);
}

.form {
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-4);
}

.field {
    display: flex;
    flex-direction: column;
    gap: var(--rk-space-2);
}

.field__label {
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.input {
    background: var(--rk-bg);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius);
    padding: var(--rk-space-3);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
    font-family: inherit;
}

.input:focus {
    outline: none;
    border-color: var(--rk-primary);
    box-shadow: var(--rk-glow-primary);
}

.remember {
    display: flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.error {
    color: var(--rk-danger);
    font-size: var(--rk-text-xs);
}

.btn {
    margin-top: var(--rk-space-2);
    background: var(--rk-primary);
    color: var(--rk-primary-contrast);
    border: none;
    border-radius: var(--rk-radius);
    padding: var(--rk-space-3);
    font-weight: 600;
    font-size: var(--rk-text-sm);
    cursor: pointer;
    box-shadow: var(--rk-glow-primary);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.alt {
    margin: var(--rk-space-4) 0 0;
    font-size: var(--rk-text-sm);
    color: var(--rk-text-muted);
}

.link {
    color: var(--rk-primary);
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}
</style>
