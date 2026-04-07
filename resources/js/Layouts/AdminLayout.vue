<script setup>
import AppLayout from './AppLayout.vue';

defineProps({
    title: {
        type: String,
        default: 'Admin',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="breadcrumbs">
        <section class="admin-layout-shell">
            <slot />
        </section>
    </AppLayout>
</template>
