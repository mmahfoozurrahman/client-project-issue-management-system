<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    projects: Object,
    clients: Array,
    filters: Object,
    breadcrumbs: Array,
    canCreateProject: Boolean,
});

const projectRows = computed(() => props.projects?.data ?? props.projects ?? []);
const searchQuery = ref(props.filters?.q ?? '');
const modalOpen = ref(false);
const editingProject = ref(null);

const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const initials = (value) => String(value || '?').trim().slice(0, 1).toUpperCase();
const summarize = (value, length = 132) => {
    const text = plainText(value);
    if (!text) return 'No description added yet.';
    return text.length > length ? `${text.slice(0, length).trim()}...` : text;
};

const form = useForm({
    name: '',
    description: '',
    client_id: '',
});

const submitLabel = computed(() => (editingProject.value ? 'Update Project' : 'Create Project'));
const searchHint = computed(() => {
    const length = searchQuery.value.trim().length;
    if (!length) return 'Type at least 4 letters to search projects, clients, or descriptions.';
    if (length < 4) return `${4 - length} more letter${4 - length === 1 ? '' : 's'} before live search starts.`;
    return 'Searching live across project name, client, and description.';
});

let searchTimer = null;

const applySearch = () => {
    const query = searchQuery.value.trim();

    if (query !== '' && query.length < 4) {
        return;
    }

    router.get('/projects', query ? { q: query } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(searchQuery, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    const query = searchQuery.value.trim();
    if (query !== '' && query.length < 4) {
        return;
    }

    searchTimer = setTimeout(() => {
        applySearch();
    }, 300);
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

const clearSearch = () => {
    searchQuery.value = '';
};

const openCreate = () => {
    editingProject.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (project) => {
    editingProject.value = project;
    form.name = project.name;
    form.description = project.description ?? '';
    form.client_id = project.client_id;
    form.clearErrors();
    modalOpen.value = true;
};

const submit = () => {
    if (editingProject.value) {
        form.put(`/projects/${editingProject.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
        return;
    }

    form.post('/projects', {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const destroyProject = (project) => {
    Swal.fire({
        title: `Delete ${project.name}?`,
        text: 'Issues inside this project will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            form.delete(`/projects/${project.id}`);
        }
    });
};
</script>

<template>
    <Head title="Projects" />

    <AppLayout title="Projects" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header project-index-header">
                <div>
                    <p class="section-kicker">Projects</p>
                    <h3 class="panel-title">Organize work by client-owned project spaces</h3>
                </div>
                <button v-if="canCreateProject" class="btn btn-accent rounded-pill" @click="openCreate">Add Project</button>
            </div>

            <div class="search-toolbar">
                <div class="search-input-shell">
                    <span class="search-prefix">Search</span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="form-control search-input"
                        placeholder="Project name, client, or description"
                    >
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="btn btn-sm btn-light rounded-pill search-clear"
                        @click="clearSearch"
                    >
                        Clear
                    </button>
                </div>
                <p class="search-hint">{{ searchHint }}</p>
            </div>

            <div v-if="projectRows.length" class="project-list">
                <div class="project-list-labels" aria-hidden="true">
                    <span>Project</span><span>Client</span><span>Workload</span><span />
                </div>

                <article v-for="project in projectRows" :key="project.id" class="project-row">
                    <div class="project-identity">
                        <span class="entity-avatar entity-avatar-project">{{ initials(project.name) }}</span>
                        <div class="entity-copy">
                            <h4>{{ project.name }}</h4>
                            <p>{{ summarize(project.description, 88) }}</p>
                        </div>
                    </div>

                    <div class="project-client">
                        <span class="meta-label">Client</span>
                        <strong>{{ project.client?.name || 'No client assigned' }}</strong>
                    </div>

                    <div class="project-workload">
                        <span class="issue-count">{{ project.issues_count }}</span>
                        <span>open issue{{ project.issues_count === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="entity-actions">
                        <Link :href="`/projects/${project.id}`" class="btn btn-sm btn-accent rounded-pill">Open project</Link>
                        <button v-if="project.can_edit" class="btn btn-sm btn-light action-icon" @click="openEdit(project)">Edit</button>
                        <button v-if="project.can_delete" class="btn btn-sm btn-light action-icon action-delete" @click="destroyProject(project)">Delete</button>
                    </div>
                </article>
            </div>

            <div v-else class="entity-empty-state">
                <strong>No projects found.</strong>
                <span>Try a broader search or create a new project workspace.</span>
            </div>

            <Pagination :links="projects.links" :meta="projects" />
        </section>

        <Modal v-model="modalOpen" :title="submitLabel">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Project name</label>
                    <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }">
                    <FormError :message="form.errors.name" />
                </div>

                <div>
                    <label class="form-label">Client</label>
                    <select v-model="form.client_id" class="form-select" :class="{ 'is-invalid-soft': form.errors.client_id }">
                        <option value="">Select a client</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
                    </select>
                    <FormError :message="form.errors.client_id" />
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <RichTextEditor v-model="form.description" :error="form.errors.description" placeholder="Describe goals, scope, and important project notes..." />
                    <FormError :message="form.errors.description" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    {{ submitLabel }}
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.project-index-header {
    gap: 1rem;
}

.search-toolbar {
    margin-bottom: 1.5rem;
}

.search-input-shell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid rgba(25, 55, 47, 0.12);
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(245, 248, 246, 0.98), rgba(240, 245, 242, 0.94));
}

.search-prefix {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #567064;
}

.search-input {
    border: 0;
    box-shadow: none;
    background: transparent;
    padding: 0;
    min-height: auto;
    font-size: 0.95rem;
}

.search-input:focus {
    box-shadow: none;
}

.search-clear {
    flex-shrink: 0;
}

.search-hint {
    margin: 0.55rem 0 0;
    font-size: 0.8rem;
    color: #6d7f76;
}

.project-list {
    margin-bottom: 1.5rem;
    border: 1px solid rgba(25, 55, 47, 0.1);
    border-radius: 1.35rem;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.55);
}

.project-list-labels,
.project-row {
    display: grid;
    grid-template-columns: minmax(260px, 2.2fr) minmax(150px, 1fr) minmax(110px, 0.7fr) auto;
    align-items: center;
    column-gap: 1.25rem;
}

.project-list-labels {
    padding: 0.7rem 1.25rem;
    background: rgba(238, 246, 242, 0.72);
    border-bottom: 1px solid rgba(25, 55, 47, 0.08);
    color: #789087;
    font-size: 0.67rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.project-row {
    position: relative;
    min-height: 108px;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.5);
    border-bottom: 1px solid rgba(25, 55, 47, 0.08);
    transition: background 180ms ease, box-shadow 180ms ease;
}

.project-row::before {
    position: absolute;
    top: 20%; bottom: 20%; left: 0;
    width: 3px;
    border-radius: 0 5px 5px 0;
    background: transparent;
    content: '';
    transition: background 180ms ease;
}

.project-row:last-child { border-bottom: 0; }

.project-row:hover {
    z-index: 1;
    background: #fff;
    box-shadow: 0 10px 26px rgba(22, 45, 38, 0.07);
}

.project-row:hover::before { background: #247b6e; }

.project-identity {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.entity-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    flex-shrink: 0;
}

.entity-avatar-project {
    background: #f6ebe3;
    color: #8e4f20;
}

.entity-copy {
    min-width: 0;
}

.entity-copy h4 {
    margin: 0;
    font-size: 1rem;
    line-height: 1.35;
    color: #233142;
}

.entity-copy p {
    margin: 0.15rem 0 0;
    font-size: 0.76rem;
    color: #77867f;
    line-height: 1.45;
}

.project-client {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.project-client strong {
    overflow: hidden;
    color: #3d5349;
    font-size: 0.88rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.project-workload {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #597168;
    font-size: 0.79rem;
    white-space: nowrap;
}

.issue-count {
    display: inline-grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 0.65rem;
    background: #e8f4ef;
    color: #1f6c5f;
    font-size: 0.85rem;
    font-weight: 800;
}

.meta-label {
    font-size: 0.68rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7b8f86;
}

.entity-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    justify-content: flex-end;
}

.action-icon { border-color: rgba(25, 55, 47, 0.1); color: #566961; }
.action-delete:hover { border-color: #dc5a63; color: #c33c47; }

.entity-empty-state {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px dashed rgba(25, 55, 47, 0.16);
    border-radius: 1.35rem;
    color: #64756d;
    background: #fbfcfb;
}

@media (max-width: 767.98px) {
    .search-input-shell {
        flex-wrap: wrap;
        border-radius: 1.2rem;
    }

    .project-list-labels { display: none; }

    .project-row {
        grid-template-columns: 1fr;
        gap: 0.85rem;
        padding: 1rem;
    }

    .project-client {
        display: grid;
        grid-template-columns: 80px 1fr;
        align-items: center;
    }

    .project-workload { width: fit-content; }
    .entity-actions { justify-content: flex-start; }
}
</style>
