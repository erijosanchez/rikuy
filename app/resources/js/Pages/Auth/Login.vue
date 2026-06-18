<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

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

    <AuthCard title="Bienvenido de vuelta" subtitle="Accede a tu workspace.">
        <form @submit.prevent="submit" class="form">
            <label class="field">
                <span class="field__label">Email</span>
                <input v-model="form.email" type="email" autocomplete="email" required class="input" placeholder="tucorreo@empresa.com" />
                <span v-if="form.errors.email" class="error">{{ form.errors.email }}</span>
            </label>

            <label class="field">
                <span class="field__label">Contraseña</span>
                <input v-model="form.password" type="password" autocomplete="current-password" required class="input" placeholder="••••••••" />
                <span v-if="form.errors.password" class="error">{{ form.errors.password }}</span>
            </label>

            <label class="remember">
                <input v-model="form.remember" type="checkbox" />
                <span>Recordarme</span>
            </label>

            <button type="submit" class="submit" :disabled="form.processing">
                {{ form.processing ? 'Entrando…' : 'Entrar' }}
            </button>
        </form>

        <template #footer>
            <p class="alt">¿No tienes cuenta? <Link href="/register" class="link">Crea tu workspace</Link></p>
            <p class="alt"><Link href="/demo" class="link">Ver el demo sin registrarte →</Link></p>
        </template>
    </AuthCard>
</template>

<style scoped>
.form { display: flex; flex-direction: column; gap: var(--rk-space-4); }
.field { display: flex; flex-direction: column; gap: var(--rk-space-2); }
.field__label { font-size: var(--rk-text-sm); font-weight: 500; color: var(--rk-text-muted); }

.input {
    background: var(--rk-bg-soft);
    border: 1px solid var(--rk-border-strong);
    border-radius: var(--rk-radius);
    padding: var(--rk-space-3) var(--rk-space-4);
    color: var(--rk-text);
    font-size: var(--rk-text-sm);
    font-family: inherit;
    transition: border-color var(--rk-transition), box-shadow var(--rk-transition);
}
.input:focus { outline: none; border-color: var(--rk-primary); box-shadow: var(--rk-ring); }

.remember { display: flex; align-items: center; gap: var(--rk-space-2); font-size: var(--rk-text-sm); color: var(--rk-text-muted); cursor: pointer; }
.remember input { accent-color: var(--rk-primary); width: 15px; height: 15px; }

.error { color: var(--rk-danger); font-size: var(--rk-text-xs); }

.submit {
    margin-top: var(--rk-space-2);
    background: var(--rk-gradient-primary);
    color: var(--rk-primary-contrast);
    border: none;
    border-radius: var(--rk-radius);
    padding: var(--rk-space-3);
    font-weight: 700;
    font-size: var(--rk-text-sm);
    cursor: pointer;
    box-shadow: var(--rk-glow-primary);
    transition: transform var(--rk-transition), filter var(--rk-transition);
}
.submit:hover { filter: brightness(1.05); }
.submit:active { transform: translateY(1px); }
.submit:disabled { opacity: 0.6; cursor: not-allowed; }

.alt { margin: 0; font-size: var(--rk-text-sm); color: var(--rk-text-muted); }
.link { color: var(--rk-primary); text-decoration: none; font-weight: 600; }
.link:hover { text-decoration: underline; }
</style>
