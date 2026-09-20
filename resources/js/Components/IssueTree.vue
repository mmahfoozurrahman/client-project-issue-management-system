<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import StatusPill from './StatusPill.vue';

const props = defineProps({
    issues: {
        type: Array,
        default: () => [],
    },
    path: {
        type: Array,
        default: () => [],
    },
    depth: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['create-child', 'quick-read']);

const normalizedIssues = computed(() => props.issues ?? []);

const getChildren = (issue) => issue.sub_issues ?? issue.subIssues ?? [];
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const issueNumber = (index) => [...props.path, index + 1].join('-');
</script>

<template>
    <div v-if="normalizedIssues.length" class="issue-tree">
        <div v-for="(issue, index) in normalizedIssues" :key="issue.id" class="issue-tree-node">
            <div class="issue-tree-card" :style="{ '--tree-depth': depth }">
                <div class="nested-issue-card">
                    <div class="nested-issue-header">
                        <span class="nested-depth-marker">{{ issueNumber(index) }}</span>
                        <div class="nested-issue-title-block">
                            <Link :href="`/issues/${issue.id}`" class="nested-issue-title-link">
                                <strong class="nested-issue-title">{{ issue.title }}</strong>
                            </Link>
                            <div v-if="issue.tags?.length" class="nested-issue-tags">
                                <Link
                                    v-for="tag in issue.tags"
                                    :key="tag.id"
                                    :href="`/issues?tag_id=${tag.id}&project_id=${issue.project_id}`"
                                    class="badge rounded-pill text-bg-light border text-decoration-none"
                                >
                                    {{ tag.name }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="nested-issue-footer">
                        <div class="nested-issue-meta">
                            <StatusPill :status="issue.status" size="sm" />
                            <span class="nested-children-count">
                                {{ getChildren(issue).length }} {{ getChildren(issue).length === 1 ? 'child' : 'children' }}
                            </span>
                        </div>
                        <div class="nested-issue-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-light rounded-pill nested-quick-read-btn"
                                @click="emit('quick-read', issue)"
                            >
                                Quick read
                            </button>
                            <button
                                type="button"
                                class="nested-add-btn"
                                @click="emit('create-child', issue)"
                            >
                                <Plus :size="14" />
                                <span>Child</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <IssueTree
                v-if="getChildren(issue).length"
                :issues="getChildren(issue)"
                :path="[...path, index + 1]"
                :depth="depth + 1"
                @create-child="emit('create-child', $event)"
                @quick-read="emit('quick-read', $event)"
            />
        </div>
    </div>
</template>
