<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

const form = useForm({
    name: '',
    organization: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear workspace" />

    <AuthCard title="Crea tu workspace" subtitle="Tu propio tenant, vacío y listo para subir datos.">
        <form @submit.prevent="submit" class="form">
            <label class="field">
                <span class="field__label">Tu nombre</span>
                <input v-model="form.name" type="text" autocomplete="name" required class="input" placeholder="Ada Lovelace" />
                <span v-if="form.errors.name" class="error">{{ form.errors.name }}</span>
            </label>

            <label class="field">
                <span class="field__label">Nombre del workspace <em>(opcional)</em></span>
                <input v-model="form.organization" type="text" class="input" placeholder="Mi empresa" />
                <span v-if="form.errors.organization" class="error">{{ form.errors.organization }}</span>
            </label>

            <label class="field">
                <span class="field__label">Email</span>
                <input v-model="form.email" type="email" autocomplete="email" required class="input" placeholder="tucorreo@empresa.com" />
                <span v-if="form.errors.email" class="error">{{ form.errors.email }}</span>
            </label>

            <div class="row">
                <label class="field">
                    <span class="field__label">Contraseña</span>
                    <input v-model="form.password" type="password" autocomplete="new-password" required class="input" placeholder="••••••••" />
                    <span v-if="form.errors.password" class="error">{{ form.errors.password }}</span>
                </label>

                <label class="field">
                    <span class="field__label">Confirmar</span>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="input" placeholder="••••••••" />
                </label>
            </div>

            <button type="submit" class="submit" :disabled="form.processing">
                {{ form.processing ? 'Creando…' : 'Crear workspace' }}
            </button>
        </form>

        <template #footer>
            <p class="alt">¿Ya tienes cuenta? <Link href="/login" class="link">Entrar</Link></p>
        </template>
    </AuthCard>
</template>

<style scoped>
.form { display: flex; flex-direction: column; gap: var(--rk-space-4); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--rk-space-3); }
@media (max-width: 460px) { .row { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: var(--rk-space-2); }
.field__label { font-size: var(--rk-text-sm); font-weight: 500; color: var(--rk-text-muted); }
.field__label em { color: var(--rk-text-faint); font-style: normal; }

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
