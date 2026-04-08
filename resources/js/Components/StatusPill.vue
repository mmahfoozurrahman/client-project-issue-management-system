<script setup>
const props = defineProps({
    status: {
        type: String,
        default: 'todo',
    },
    size: {
        type: String,
        default: 'md',
    },
});

const labels = {
    todo: 'Todo',
    inprogress: 'In Progress',
    done: 'Done',
};
</script>

<template>
    <span class="table-pill status-pill" :class="[`status-${props.status}`, `status-pill-${size}`]">
        <span class="status-dot" />
        {{ labels[props.status] ?? props.status }}
    </span>
</template>
