<template>
    <Modal v-model="isOpen" :title="issue?.title || 'Issue quick read'" size="modal-lg">
        <article v-if="issue" class="vstack gap-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <StatusPill :status="issue.status" />
                <button
                    v-if="narration.is_available"
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-1"
                    :disabled="isLoadingAudio"
                    @click="toggleNarration(issue.id)"
                >
                    <span v-if="isLoadingAudio" class="spinner-border spinner-border-sm" />
                    <svg v-else-if="isPlaying" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" /><rect x="14" y="5" width="4" height="14" /></svg>
                    <svg v-else viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                    {{ isPlaying ? 'Pause narration' : (isPaused ? 'Resume narration' : 'Play narration') }}
                </button>
                <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-outline-secondary rounded-pill">Open full issue</Link>
                <span v-if="issue.parent_issue" class="badge rounded-pill text-bg-light border">Parent: {{ issue.parent_issue.title }}</span>
                <span v-if="issue.user?.name" class="text-muted small">Created by {{ issue.user.name }}</span>
                <span class="text-muted small">Created {{ formatIssueDate(issue.created_at) }}</span>
                <span v-if="issue.updated_at" class="text-muted small">Updated {{ formatIssueDate(issue.updated_at) }}</span>
            </div>

            <!-- Narration seek slider with forward / backward scrubber -->
            <div
                v-if="narration.is_available && (isPlaying || isPaused || currentTime > 0)"
                class="narration-seek d-flex align-items-center gap-2"
                title="Use Left/Right arrow keys (← / →) to skip 5 seconds back or forward"
            >
                <small class="text-muted narration-time">{{ formatNarrationTime(currentTime) }}</small>
                <input
                    type="range"
                    class="form-range narration-range"
                    min="0"
                    :max="duration || 0"
                    step="0.1"
                    :value="currentTime"
                    title="Audio slider (Use ← / → arrow keys to seek ±5s)"
                    @input="onNarrationSeek"
                />
                <small class="text-muted narration-time">{{ formatNarrationTime(duration) }}</small>
                <span v-if="narration.track_count > 1" class="badge rounded-pill text-bg-light border text-nowrap small">
                    {{ toBengaliNumber(currentTrackIndex + 1) }}/{{ toBengaliNumber(narration.track_count) }}
                </span>
            </div>

            <div v-if="isAdmin" class="narration-admin-strip">
                <span class="text-muted small">
                    Bengali narration:
                    <strong>{{ narrationStatusLabel }}</strong>
                    <template v-if="narration.error_message"> — {{ narration.error_message }}</template>
                </span>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill"
                    :disabled="isGenerating"
                    @click="generateNarration(issue.id, { force: narration.status === 'ready' })"
                >
                    <span v-if="isGenerating" class="spinner-border spinner-border-sm me-1" />
                    {{ narration.status === 'ready' ? 'Refresh narration' : 'Generate narration' }}
                </button>
            </div>

            <form v-if="canChangeStatus" class="quick-read-status-control" @submit.prevent="updateStatus">
                <label :for="`quick-read-status-${issue.id}`">Change status</label>
                <select :id="`quick-read-status-${issue.id}`" v-model="status" class="form-select form-select-sm" :disabled="savingStatus">
                    <option value="todo">Todo</option>
                    <option value="inprogress">In Progress</option>
                    <option value="done">Done</option>
                </select>
                <button type="submit" class="btn btn-sm btn-accent rounded-pill" :disabled="savingStatus || status === issue.status">
                    {{ savingStatus ? 'Saving…' : 'Update' }}
                </button>
            </form>

            <div>
                <p class="section-kicker mb-1">Issue details</p>
                <div v-if="issue.description" class="rich-display" v-html="issue.description" />
                <p v-else class="text-muted mb-0">No description added yet.</p>
            </div>

            <div v-if="issue.tags?.length">
                <p class="section-kicker mb-2">Tags</p>
                <div class="d-flex flex-wrap gap-1"><Link v-for="tag in issue.tags" :key="tag.id" :href="`/issues?tag_id=${tag.id}&project_id=${issue.project_id}`" class="badge rounded-pill text-bg-light border text-decoration-none">{{ tag.name }}</Link></div>
            </div>

            <div class="row g-3 text-center">
                <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ issue.sub_issues_count ?? 0 }}</strong><small class="text-muted">Sub-issues</small></div></div>
                <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ issue.images?.length ?? issue.images_count ?? 0 }}</strong><small class="text-muted">Images</small></div></div>
                <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ issue.files?.length ?? issue.files_count ?? 0 }}</strong><small class="text-muted">Files</small></div></div>
            </div>

            <div v-if="issue.images?.length"><p class="section-kicker mb-2">Images</p><div class="row g-2"><div v-for="image in issue.images" :key="image.id" class="col-6 col-md-4"><a :href="image.url" target="_blank" rel="noopener noreferrer"><img :src="image.url" :alt="image.original_name || issue.title" class="img-fluid rounded-3 border"></a></div></div></div>
            <div v-if="issue.files?.length"><p class="section-kicker mb-2">Files</p><div class="list-group list-group-flush border rounded-3"><a v-for="file in issue.files" :key="file.id" :href="file.url" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">{{ file.original_name || 'Attachment' }}</a></div></div>
            <div v-if="issue.links?.length"><p class="section-kicker mb-2">Links</p><div class="list-group list-group-flush border rounded-3"><a v-for="link in issue.links" :key="link.id" :href="link.url" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">{{ link.label || link.url }}</a></div></div>

            <div class="d-flex justify-content-end"><Link :href="`/issues/${issue.id}`" class="btn btn-outline-secondary rounded-pill">Open full issue</Link></div>
        </article>
    </Modal>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import StatusPill from './StatusPill.vue';
import { formatIssueDate } from '../utils/date';
import { useIssueNarration } from '../composables/useIssueNarration';

const props = defineProps({ modelValue: Boolean, issue: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const isOpen = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const page = usePage();
const status = ref(props.issue?.status ?? 'todo');
const savingStatus = ref(false);
const canChangeStatus = computed(() => {
    const user = page.props.auth?.user;
    const allowedProjectIds = page.props.auth?.status_change_project_ids ?? [];

    return Boolean(user?.is_admin) || allowedProjectIds.map(Number).includes(Number(props.issue?.project_id));
});
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));

const {
    narration,
    isPlaying,
    isPaused,
    isGenerating,
    isLoadingAudio,
    currentTrackIndex,
    currentTime,
    duration,
    fetchStatus,
    generateNarration,
    toggleNarration,
    resetPlayback,
    seekTo,
    stopPolling,
} = useIssueNarration();

const bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
function toBengaliNumber(val) {
    return String(val ?? '').replace(/\d/g, (d) => bengaliDigits[Number(d)]);
}

function formatNarrationTime(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    return toBengaliNumber(`${mins}:${String(secs).padStart(2, '0')}`);
}

function onNarrationSeek(event) {
    seekTo(Number(event.target.value));
}

function isEditableTarget(target) {
    if (!target) return false;
    if (target.classList?.contains('narration-range')) return false;
    const tag = target.tagName?.toLowerCase();
    return ['input', 'textarea', 'select'].includes(tag) || target.isContentEditable;
}

function handleKeyDown(e) {
    if (!isOpen.value) return;
    if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey || e.isComposing) return;
    if (isEditableTarget(e.target)) return;

    if (narration.value.is_available && (isPlaying.value || isPaused.value || currentTime.value > 0)) {
        e.preventDefault();
        const delta = e.key === 'ArrowLeft' ? -5 : 5;
        seekTo((currentTime.value || 0) + delta);
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const narrationStatusLabel = computed(() => ({
    idle: 'not generated yet',
    queued: 'queued…',
    processing: 'generating…',
    ready: 'ready',
    stale: 'content changed, refresh needed',
    failed: 'failed',
}[narration.value.status] ?? narration.value.status));

watch(() => props.issue, (issue) => {
    status.value = issue?.status ?? 'todo';
}, { immediate: true });

watch(isOpen, (open) => {
    if (open && props.issue) {
        fetchStatus(props.issue.id);
    } else {
        stopPolling();
        resetPlayback();
    }
});

const updateStatus = () => {
    if (!props.issue || status.value === props.issue.status) return;

    savingStatus.value = true;
    router.patch(`/issues/${props.issue.id}/status`, { status: status.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            props.issue.status = status.value;
        },
        onFinish: () => {
            savingStatus.value = false;
        },
    });
};
</script>

<style scoped>
/* ── Narration seek slider ── */
.narration-seek {
    width: 100%;
    padding: 0.35rem 0.65rem;
    background: rgba(15, 118, 110, 0.04);
    border: 1px solid rgba(15, 118, 110, 0.16);
    border-radius: 9999px;
    user-select: none;
}

.narration-range {
    flex: 1 1 auto;
    cursor: pointer;
    margin: 0;
    accent-color: #2d6a4f;
}

.narration-time {
    min-width: 34px;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

.narration-range.form-range::-webkit-slider-thumb {
    background-color: #2d6a4f;
    box-shadow: 0 0 0 2px rgba(45, 106, 79, 0.2);
    width: 14px;
    height: 14px;
}

.narration-range.form-range::-webkit-slider-runnable-track {
    background-color: #d8f3dc;
    height: 6px;
    border-radius: 3px;
}

.narration-range.form-range::-moz-range-thumb {
    background-color: #2d6a4f;
    box-shadow: 0 0 0 2px rgba(45, 106, 79, 0.2);
    width: 14px;
    height: 14px;
}

.narration-range.form-range::-moz-range-track {
    background-color: #d8f3dc;
    height: 6px;
    border-radius: 3px;
}

.narration-range.form-range:focus::-webkit-slider-thumb {
    box-shadow: 0 0 0 4px rgba(45, 106, 79, 0.3);
}

.narration-range.form-range:focus::-moz-range-thumb {
    box-shadow: 0 0 0 4px rgba(45, 106, 79, 0.3);
}

.narration-admin-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 1px dashed rgba(15, 118, 110, 0.25);
    border-radius: 0.65rem;
    background: rgba(15, 118, 110, 0.03);
}

.rich-display {
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.quick-read-status-control {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    border: 1px solid rgba(15, 118, 110, 0.16);
    border-radius: 0.75rem;
    background: rgba(15, 118, 110, 0.05);
}

.quick-read-status-control label {
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
}

.quick-read-status-control .form-select {
    width: 180px;
    min-height: 38px;
    padding: 0.3rem 2rem 0.3rem 0.7rem;
    border-radius: 0.55rem;
}

.quick-read-status-control .btn {
    min-height: 38px;
    padding: 0.35rem 0.85rem;
    color: #fff;
}

.quick-read-status-control .btn:disabled {
    color: rgba(255, 255, 255, 0.9);
    opacity: 0.72;
}

@media (max-width: 575px) {
    .quick-read-status-control {
        align-items: stretch;
    }

    .quick-read-status-control .form-select {
        width: 100%;
    }
}
</style>
