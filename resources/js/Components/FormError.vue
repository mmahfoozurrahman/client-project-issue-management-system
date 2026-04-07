<script setup>
defineProps({
    message: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <transition name="field-error-fade">
        <div v-if="message" class="field-error" role="alert">{{ message }}</div>
    </transition>
</template>
