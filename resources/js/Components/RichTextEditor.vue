<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Write a clear description...',
    },
    error: {
        type: [String, Boolean],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);

const syncFromDom = () => {
    emit('update:modelValue', editor.value?.innerHTML ?? '');
};

const runCommand = (command) => {
    document.execCommand(command, false, null);
    syncFromDom();
};

watch(() => props.modelValue, async (value) => {
    await nextTick();

    if (editor.value && editor.value.innerHTML !== (value || '')) {
        editor.value.innerHTML = value || '';
    }
}, { immediate: true });
</script>

<template>
    <div class="rich-editor" :class="{ 'is-invalid-soft': error }">
        <div class="rich-editor-toolbar">
            <button type="button" @click="runCommand('bold')">Bold</button>
            <button type="button" @click="runCommand('italic')">Italic</button>
            <button type="button" @click="runCommand('insertUnorderedList')">Bullets</button>
            <button type="button" @click="runCommand('insertOrderedList')">Numbers</button>
        </div>

        <div
            ref="editor"
            class="rich-editor-surface"
            contenteditable="true"
            :data-placeholder="placeholder"
            @input="syncFromDom"
            @blur="syncFromDom"
        />
    </div>
</template>
