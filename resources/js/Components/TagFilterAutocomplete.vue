<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Type to filter tags...',
    },
    maxSuggestions: {
        type: Number,
        default: 50,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const rootRef = ref(null);
const inputRef = ref(null);
const term = ref('');
const isOpen = ref(false);

const selectedIds = computed(() =>
    (Array.isArray(props.modelValue) ? props.modelValue : []).map((id) => String(id))
);

const selectedCount = computed(() => selectedIds.value.length);

const filteredTags = computed(() => {
    const query = term.value.trim().toLowerCase();

    return (props.tags || [])
        .filter((tag) => !query || String(tag.name || '').toLowerCase().includes(query))
        .slice(0, props.maxSuggestions);
});

const isSelected = (tag) => selectedIds.value.includes(String(tag.id));

const emitValue = (ids) => {
    emit('update:modelValue', ids);
    emit('change', ids);
};

const addTag = (tag) => {
    const id = String(tag.id);
    if (selectedIds.value.includes(id)) return;
    emitValue([...selectedIds.value, id]);
};

const removeTag = (tag) => {
    const id = String(tag.id);
    emitValue(selectedIds.value.filter((existing) => existing !== id));
};

const toggleTag = (tag) => {
    if (isSelected(tag)) {
        removeTag(tag);
    } else {
        addTag(tag);
    }
};

const clearAll = () => {
    if (!selectedCount.value) return;
    emitValue([]);
};

const onFocus = () => {
    isOpen.value = true;
};

const onInput = () => {
    isOpen.value = true;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

const closeOnOutsideClick = (event) => {
    if (isOpen.value && rootRef.value && !rootRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', closeOnOutsideClick));
</script>

<template>
    <div ref="rootRef" class="tag-filter-autocomplete">
        <div class="tag-filter-shell" :class="{ 'is-open': isOpen }" @click="inputRef?.focus()">
            <input
                ref="inputRef"
                v-model="term"
                type="text"
                class="tag-filter-input"
                :placeholder="placeholder"
                autocomplete="off"
                @focus="onFocus"
                @input="onInput"
                @keydown="onKeydown"
            >

            <span v-if="selectedCount" class="tag-filter-count">{{ selectedCount }}</span>

            <button
                v-if="selectedCount"
                type="button"
                class="tag-filter-clear"
                @click.stop="clearAll"
            >
                Clear
            </button>
        </div>

        <div v-if="isOpen" class="tag-filter-menu">
            <div class="tag-filter-menu-head">
                <span class="tag-filter-menu-title">
                    {{ selectedCount ? `${selectedCount} selected` : 'Select one or more tags' }}
                </span>
                <button v-if="selectedCount" type="button" class="tag-filter-clear" @click="clearAll">Clear</button>
            </div>

            <label v-for="tag in filteredTags" :key="tag.id" class="tag-filter-option">
                <input type="checkbox" :checked="isSelected(tag)" @change="toggleTag(tag)">
                <span>{{ tag.name }}</span>
            </label>

            <p v-if="!filteredTags.length" class="tag-filter-empty">
                {{ term.trim() ? `No tags match "${term.trim()}"` : 'No tags available for this project.' }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.tag-filter-autocomplete {
    position: relative;
}

.tag-filter-shell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 4px 6px 4px 16px;
    background: #fff;
    border: 1px solid rgba(103, 80, 54, 0.22);
    border-radius: 999px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    cursor: text;
}

.tag-filter-shell.is-open,
.tag-filter-shell:focus-within {
    border-color: rgba(15, 118, 110, 0.55);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
}

.tag-filter-input {
    flex: 1 1 auto;
    min-width: 60px;
    padding: 4px 0;
    border: 0;
    background: transparent;
    font-size: 0.92rem;
    color: #333;
    outline: none;
}

.tag-filter-count {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 7px;
    background: rgba(15, 118, 110, 0.1);
    color: #0f766e;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
}

.tag-filter-clear {
    flex-shrink: 0;
    padding: 0 8px;
    border: 0;
    background: transparent;
    color: #0f766e;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
}

.tag-filter-clear:hover {
    text-decoration: underline;
}

.tag-filter-menu-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.tag-filter-menu-title {
    font-size: 0.78rem;
    color: #7b8f86;
}

.tag-filter-empty {
    margin: 0;
    padding: 8px 4px 2px;
    color: #77867f;
    font-size: 0.85rem;
}
</style>
