<template>
    <Modal v-model="isOpen" :title="issue?.title || 'Issue quick read'" size="modal-lg">
        <article v-if="issue" class="vstack gap-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <StatusPill :status="issue.status" />
                <span v-if="issue.parent_issue" class="badge rounded-pill text-bg-light border">Parent: {{ issue.parent_issue.title }}</span>
                <span v-if="issue.user?.name" class="text-muted small">Created by {{ issue.user.name }}</span>
                <span class="text-muted small">Created {{ formatIssueDate(issue.created_at) }}</span>
                <span v-if="issue.updated_at" class="text-muted small">Updated {{ formatIssueDate(issue.updated_at) }}</span>
            </div>

            <div>
                <p class="section-kicker mb-1">Issue details</p>
                <div v-if="issue.description" class="rich-display" v-html="issue.description" />
                <p v-else class="text-muted mb-0">No description added yet.</p>
            </div>

            <div v-if="issue.tags?.length">
                <p class="section-kicker mb-2">Tags</p>
                <div class="d-flex flex-wrap gap-1"><span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span></div>
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
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import StatusPill from './StatusPill.vue';
import { formatIssueDate } from '../utils/date';

const props = defineProps({ modelValue: Boolean, issue: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const isOpen = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
</script>

<style scoped>
.rich-display {
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
}
</style>
