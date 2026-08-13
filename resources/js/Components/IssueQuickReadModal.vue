<template>
    <Modal v-model="isOpen" :title="issue?.title || 'Issue quick read'" size="modal-lg">
        <article v-if="issue" class="vstack gap-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <StatusPill :status="issue.status" />
                <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-outline-secondary rounded-pill">Open full issue</Link>
                <span v-if="issue.parent_issue" class="badge rounded-pill text-bg-light border">Parent: {{ issue.parent_issue.title }}</span>
                <span v-if="issue.user?.name" class="text-muted small">Created by {{ issue.user.name }}</span>
                <span class="text-muted small">Created {{ formatIssueDate(issue.created_at) }}</span>
                <span v-if="issue.updated_at" class="text-muted small">Updated {{ formatIssueDate(issue.updated_at) }}</span>
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
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import StatusPill from './StatusPill.vue';
import { formatIssueDate } from '../utils/date';

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

watch(() => props.issue, (issue) => {
    status.value = issue?.status ?? 'todo';
}, { immediate: true });

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
