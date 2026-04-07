<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb">
        <ol class="app-breadcrumb">
            <li v-for="(item, index) in items" :key="`${item.label}-${index}`" class="app-breadcrumb-item">
                <Link v-if="item.href && index !== items.length - 1" :href="item.href" class="app-breadcrumb-link">
                    {{ item.label }}
                </Link>
                <span v-else class="app-breadcrumb-current">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
