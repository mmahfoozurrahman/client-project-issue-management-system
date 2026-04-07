<script setup>
import { computed } from 'vue';
import IssueCard from './IssueCard.vue';

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
</script>

<template>
    <div v-if="normalizedIssues.length" class="issue-tree">
        <div v-for="issue in normalizedIssues" :key="issue.id" class="issue-tree-node">
            <div class="issue-tree-card" :style="{ '--tree-depth': depth }">
                <IssueCard :issue="issue" :compact="depth > 0" show-child-action @create-child="emit('create-child', $event)" />
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
