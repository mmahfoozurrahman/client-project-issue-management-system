<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    projectId: {
        type: [Number, String],
        default: '',
    },
    status: {
        type: String,
        default: '',
    },
    tagId: {
        type: [Number, String],
        default: '',
    },
    tagIds: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Search title, description, or link...',
    },
    debounceMs: {
        type: Number,
        default: 300,
    },
    minLength: {
        type: Number,
        default: 2,
    },
    inputClass: {
        type: String,
        default: 'form-control',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'search', 'select']);

const rootRef = ref(null);
const inputRef = ref(null);
const suggestions = ref([]);
const isLoading = ref(false);
const isOpen = ref(false);
const activeIndex = ref(-1);
const errorMessage = ref('');

let debounceTimer = null;
let abortController = null;

const currentTerm = computed(() => String(props.modelValue || '').trim());

const cancelPending = () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
    if (abortController) {
        abortController.abort();
        abortController = null;
    }
};

const fetchSuggestions = async (explicitTerm = null) => {
    const term = String(explicitTerm !== null ? explicitTerm : (inputRef.value?.value || currentTerm.value)).trim();
    if (term.length < props.minLength) {
        suggestions.value = [];
        isOpen.value = false;
        isLoading.value = false;
        errorMessage.value = '';
        return;
    }

    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    isLoading.value = true;
    errorMessage.value = '';
    try {
        const params = {
            q: term,
        };
        if (props.projectId) {
            params.project_id = props.projectId;
        }
        if (props.status) {
            params.status = props.status;
        }
        if (props.tagId) {
            params.tag_id = props.tagId;
        }
        if (props.tagIds?.length) {
            params.tag_ids = props.tagIds;
        }

        const response = await axios.get('/issues/search-suggestions', {
            params,
            signal: abortController.signal,
        });

        suggestions.value = Array.isArray(response.data) ? response.data : [];
        activeIndex.value = -1;
        isOpen.value = true;
    } catch (error) {
        if (!axios.isCancel(error) && error.name !== 'CanceledError' && error.name !== 'AbortError') {
            console.error('[IssueSearchAutocomplete] Failed to fetch suggestions:', error);
            suggestions.value = [];
            if (error.response?.status === 404) {
                errorMessage.value = 'Route not found (404). Please clear route cache on server.';
            } else if (error.response?.status) {
                errorMessage.value = `Server error (${error.response.status}). Could not load suggestions.`;
            } else {
                errorMessage.value = 'Network error while loading suggestions.';
            }
        }
    } finally {
        isLoading.value = false;
    }
};

const onInput = (event) => {
    const val = event.target.value;
    emit('update:modelValue', val);

    cancelPending();
    errorMessage.value = '';

    const trimmed = String(val || '').trim();
    if (trimmed.length < props.minLength) {
        suggestions.value = [];
        isOpen.value = false;
        isLoading.value = false;
        return;
    }

    isOpen.value = true;
    isLoading.value = true;
    debounceTimer = setTimeout(() => {
        fetchSuggestions(trimmed);
    }, props.debounceMs);
};

const onFocus = () => {
    if (currentTerm.value.length >= props.minLength) {
        if (suggestions.value.length) {
            isOpen.value = true;
        } else {
            fetchSuggestions();
        }
    }
};

const onKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!isOpen.value) {
            if (currentTerm.value.length >= props.minLength) {
                isOpen.value = true;
            }
            return;
        }
        if (!suggestions.value.length) return;
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
        scrollActiveIntoView();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!isOpen.value || !suggestions.value.length) return;
        activeIndex.value = activeIndex.value <= 0 ? suggestions.value.length - 1 : activeIndex.value - 1;
        scrollActiveIntoView();
    } else if (event.key === 'Enter') {
        if (isOpen.value && activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
            event.preventDefault();
            selectSuggestion(suggestions.value[activeIndex.value]);
        } else {
            isOpen.value = false;
            emit('search');
        }
    } else if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

const onSearchClear = () => {
    cancelPending();
    suggestions.value = [];
    isOpen.value = false;
    emit('search');
};

const selectSuggestion = (issue) => {
    cancelPending();
    isOpen.value = false;
    emit('update:modelValue', issue.title);
    emit('select', issue);
    emit('search');
};

const scrollActiveIntoView = () => {
    nextTick(() => {
        if (!rootRef.value) return;
        const activeEl = rootRef.value.querySelector('.suggestion-item-active');
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    });
};

const handleClickOutside = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

watch(
    () => [props.projectId, props.status, props.tagId, JSON.stringify(props.tagIds)],
    () => {
        if (isOpen.value && currentTerm.value.length >= props.minLength) {
            cancelPending();
            fetchSuggestions();
        }
    }
);

onMounted(() => {
    document.addEventListener('pointerdown', handleClickOutside);
});

onBeforeUnmount(() => {
    cancelPending();
    document.removeEventListener('pointerdown', handleClickOutside);
});
</script>

<template>
    <div ref="rootRef" class="position-relative flex-grow-1 issue-search-autocomplete">
        <input
            ref="inputRef"
            :value="modelValue"
            type="search"
            :class="inputClass"
            :placeholder="placeholder"
            :disabled="disabled"
            autocomplete="off"
            @input="onInput"
            @focus="onFocus"
            @keydown="onKeyDown"
            @search="onSearchClear"
        >

        <div
            v-if="isOpen && (suggestions.length || isLoading || currentTerm.length >= minLength)"
            class="position-absolute top-100 start-0 mt-1 w-100 bg-white border rounded shadow-sm issue-suggestions-dropdown"
            style="z-index: 1050; max-height: 280px; overflow-y: auto;"
        >
            <div v-if="isLoading && !suggestions.length" class="d-flex align-items-center justify-content-center py-3 text-muted small gap-2">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                <span>Searching suggestions...</span>
            </div>

            <template v-else-if="suggestions.length">
                <button
                    v-for="(issue, index) in suggestions"
                    :key="issue.id"
                    type="button"
                    class="w-100 text-start px-3 py-2 border-0 bg-transparent text-decoration-none suggestion-item d-flex flex-column gap-1"
                    :class="{ 'suggestion-item-active': activeIndex === index }"
                    style="cursor: pointer; font-size: 0.9rem; transition: background-color 0.15s ease;"
                    @click="selectSuggestion(issue)"
                    @mouseenter="activeIndex = index"
                >
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong class="text-truncate text-dark mb-0">{{ issue.title }}</strong>
                        <span v-if="issue.project_name" class="badge rounded-pill text-bg-light border text-secondary flex-shrink-0" style="font-size: 0.72rem; font-weight: 500;">
                            {{ issue.project_name }}
                        </span>
                    </div>

                    <div v-if="issue.match_type === 'description' && issue.snippet" class="small text-muted text-truncate">
                        <span class="badge text-bg-secondary-subtle text-secondary me-1" style="font-size: 0.7rem;">In description</span>
                        <span>{{ issue.snippet }}</span>
                    </div>

                    <div v-else-if="issue.match_type === 'link' && issue.matched_links?.length" class="small text-muted text-truncate">
                        <span class="badge text-bg-info-subtle text-info-emphasis me-1" style="font-size: 0.7rem;">Link</span>
                        <span>{{ issue.matched_links.join(' · ') }}</span>
                    </div>
                </button>
            </template>

            <div v-if="errorMessage" class="px-3 py-3 text-danger small text-center">
                {{ errorMessage }}
            </div>

            <div v-else-if="!isLoading && currentTerm.length >= minLength" class="px-3 py-3 text-muted small text-center">
                No matching issues found for "<strong>{{ currentTerm }}</strong>"
            </div>
        </div>
    </div>
</template>

<style scoped>
.suggestion-item:hover,
.suggestion-item.suggestion-item-active {
    background-color: #f8fafc !important;
}
.suggestion-item:not(:last-child) {
    border-bottom: 1px solid #f1f5f9 !important;
}
</style>
