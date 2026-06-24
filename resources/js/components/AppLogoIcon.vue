<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Spotlight Bracket & Mount -->
        <path fill="currentColor" d="M 6 2 h 12 v 1.5 h -12 z M 11.25 3.5 h 1.5 v 2 h -1.5 z" />
        <!-- Spotlight Canister Body -->
        <path fill="currentColor" d="M 8.5 6 h 7 L 14 10.2 h -4 z" />
        <!-- Spotlight Light Cone (Translucent) -->
        <path fill="currentColor" fill-opacity="0.25" d="M 9.5 11.2 h 5 L 19 21 h -14 z" />
        <!-- Center glowing source -->
        <circle cx="12" cy="14.5" r="1.2" fill="currentColor" />
    </svg>
</template>
