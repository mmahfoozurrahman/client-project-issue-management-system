<script setup>
import { computed } from 'vue';
import { Plus } from 'lucide-vue-next';
import StatusPill from './StatusPill.vue';

const props = defineProps({
    issues: {
        type: Array,
        default: () => [],
    },
    depth: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['create-child']);

const normalizedIssues = computed(() => props.issues ?? []);

const getChildren = (issue) => issue.sub_issues ?? issue.subIssues ?? [];
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
</script>

<template>
    <div v-if="normalizedIssues.length" class="issue-tree">
        <div v-for="issue in normalizedIssues" :key="issue.id" class="issue-tree-node">
            <div class="issue-tree-card" :style="{ '--tree-depth': depth }">
                <div class="nested-issue-row">
                    <Link :href="`/issues/${issue.id}`" class="nested-issue-main">
                        <span class="nested-depth-marker">{{ depth + 1 }}</span>
                        <div>
                            <strong>{{ issue.title }}</strong>
                            <small>{{ plainText(issue.description) || 'No description added yet.' }}</small>
                        </div>
                    </Link>
                    <div class="nested-issue-meta">
                        <StatusPill :status="issue.status" size="sm" />
                        <span>{{ getChildren(issue).length }} children</span>
                        <button type="button" class="nested-add-btn" @click="emit('create-child', issue)">
                            <Plus :size="14" />
                            Child
                        </button>
                    </div>
                </div>
            </div>

            <IssueTree
                v-if="getChildren(issue).length"
                :issues="getChildren(issue)"
                :depth="depth + 1"
                @create-child="emit('create-child', $event)"
            />
        </div>
    </div>
</template>
