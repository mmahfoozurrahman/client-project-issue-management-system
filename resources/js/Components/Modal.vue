<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'modal-lg',
    },
});

const emit = defineEmits(['update:modelValue']);
const close = () => emit('update:modelValue', false);
</script>

<template>
    <teleport to="body">
        <transition name="modal-fade">
            <div v-if="modelValue" class="modal-shell" @click.self="close">
                <div class="custom-modal-frame" :class="size">
                    <div class="custom-modal-card">
                        <div class="custom-modal-head">
                            <div class="custom-modal-copy">
                                <span class="custom-modal-kicker">Workspace Action</span>
                                <h3 class="custom-modal-title">{{ title }}</h3>
                            </div>
                            <button type="button" class="custom-modal-close" aria-label="Close modal" @click="close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>

                        <div class="custom-modal-divider" />

                        <div class="custom-modal-body">
                            <slot />
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
