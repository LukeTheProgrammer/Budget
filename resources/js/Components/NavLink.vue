<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    route: {
        type: String,
        required: true,
    },
});

const href = route(props.route);

const active = route().current(props.route);

const classes = computed(() =>
    active
        ? 'nav-link active'
        : 'nav-link',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
