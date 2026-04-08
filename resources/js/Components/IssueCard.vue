<script setup>
import { computed } from 'vue';
import { FolderKanban, Image as ImageIcon, ListChecks, Plus } from 'lucide-vue-next';
import StatusPill from './StatusPill.vue';

const props = defineProps({
    issue: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showChildAction: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['create-child']);

const thumbnail = computed(() => props.issue.images?.[0]?.url ?? null);
const subIssueCount = computed(() => props.issue.sub_issues_count ?? props.issue.subIssues_count ?? props.issue.subIssues?.length ?? 0);
</script>

<template>
    <article class="issue-card issue-card-shell" :class="{ 'issue-card-compact': compact }">
        <button
            v-if="showChildAction"
            type="button"
            class="issue-child-trigger"
            @click.stop="emit('create-child', issue)"
        >
            <Plus :size="14" />
            <span>Add child</span>
        </button>

        <Link :href="`/issues/${issue.id}`" class="text-decoration-none text-reset d-block h-100">
            <div class="issue-thumb">
                <img v-if="thumbnail" :src="thumbnail" :alt="issue.title">
                <div v-else class="issue-thumb-empty">
                    <ImageIcon :size="24" />
                </div>
            </div>

            <div class="issue-card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <h3 class="issue-card-title">{{ issue.title }}</h3>
                    <StatusPill :status="issue.status" size="sm" />
                </div>

                <p class="issue-card-text">{{ issue.description || 'No description added yet.' }}</p>

                <div class="issue-card-meta">
                    <span class="issue-meta-pill">
                        <FolderKanban :size="14" />
                        {{ issue.project?.name || 'Project' }}
                    </span>
                    <span class="issue-meta-pill">
                        <ListChecks :size="14" />
                        {{ subIssueCount }} sub-issues
                    </span>
                </div>
            </div>
        </Link>
    </article>
</template>
