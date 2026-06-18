<script setup>
/* Logo de Rikuy: punto con glow + wordmark. Enlaza al home salvo que se pida lo contrario. */
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: '/' },
    as: { type: String, default: 'link' }, // 'link' | 'span'
});
</script>

<template>
    <component :is="as === 'span' ? 'span' : Link" :href="as === 'span' ? undefined : href" class="brand">
        <span class="brand__mark"><span class="brand__dot"></span></span>
        <span class="brand__word">Rikuy</span>
    </component>
</template>

<style scoped>
.brand {
    display: inline-flex;
    align-items: center;
    gap: var(--rk-space-2);
    font-weight: 700;
    font-size: var(--rk-text-base);
    letter-spacing: var(--rk-tracking-tight);
    color: var(--rk-text);
    text-decoration: none;
}

.brand__mark {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: var(--rk-surface-2);
    border: 1px solid var(--rk-border-strong);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.brand__dot {
    width: 9px;
    height: 9px;
    border-radius: var(--rk-radius-full);
    background: var(--rk-gradient-primary);
    box-shadow: 0 0 10px rgba(46, 196, 182, 0.7);
}

.brand__word {
    background: var(--rk-gradient-brand);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
</style>
