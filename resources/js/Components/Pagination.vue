<script setup>
defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    meta: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <div v-if="links.length > 3" class="pagination-shell">
        <div class="pagination-summary">
            Showing {{ meta.from || 0 }}-{{ meta.to || 0 }} of {{ meta.total || 0 }}
        </div>

        <div class="pagination-pills">
            <template v-for="(link, index) in links" :key="`${link.label}-${index}`">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="pagination-pill"
                    :class="{ active: link.active }"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="pagination-pill disabled"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
