<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    clients: Object,
    filters: Object,
    breadcrumbs: Array,
    canCreateClient: Boolean,
});

const clientRows = computed(() => props.clients?.data ?? props.clients ?? []);
const searchQuery = ref(props.filters?.q ?? '');
const modalOpen = ref(false);
const editingClient = ref(null);

const initials = (value) => String(value || '?').trim().slice(0, 1).toUpperCase();

const form = useForm({
    name: '',
    email: '',
});

const submitLabel = computed(() => (editingClient.value ? 'Update Client' : 'Create Client'));
const searchHint = computed(() => {
    const length = searchQuery.value.trim().length;
    if (!length) return 'Type at least 4 letters to search clients by name or email.';
    if (length < 4) return `${4 - length} more letter${4 - length === 1 ? '' : 's'} before live search starts.`;
    return 'Searching live across client name and email.';
});

let searchTimer = null;

const applySearch = () => {
    const query = searchQuery.value.trim();

    if (query !== '' && query.length < 4) {
        return;
    }

    router.get('/clients', query ? { q: query } : {}, {
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
    editingClient.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (client) => {
    editingClient.value = client;
    form.name = client.name;
    form.email = client.email ?? '';
    form.clearErrors();
    modalOpen.value = true;
};

const submit = () => {
    if (editingClient.value) {
        form.put(route('clients.update', editingClient.value.id), {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
        return;
    }

    form.post(route('clients.store'), {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const destroyClient = (client) => {
    Swal.fire({
        title: `Delete ${client.name}?`,
        text: 'Projects and issues under this client will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            form.delete(route('clients.destroy', client.id));
        }
    });
};
</script>

<template>
    <Head title="Clients" />

    <AppLayout title="Clients" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header client-index-header">
                <div>
                    <p class="section-kicker">Clients</p>
                    <h3 class="panel-title">Every tenant starts here</h3>
                </div>
                <button v-if="canCreateClient" class="btn btn-accent rounded-pill" @click="openCreate">Add Client</button>
            </div>

            <div class="search-toolbar">
                <div class="search-input-shell">
                    <span class="search-prefix">Search</span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="form-control search-input"
                        placeholder="Client name or email"
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

            <div v-if="clientRows.length" class="client-list">
                <div class="client-list-labels" aria-hidden="true">
                    <span>Client</span><span>Contact</span><span>Portfolio</span><span />
                </div>

                <article v-for="client in clientRows" :key="client.id" class="client-row">
                    <div class="client-identity">
                        <span class="entity-avatar entity-avatar-client">{{ initials(client.name) }}</span>
                        <div class="entity-copy">
                            <h4>{{ client.name }}</h4>
                            <p>Tenant workspace owner</p>
                        </div>
                    </div>

                    <div class="client-contact">
                        <span class="meta-label">Email address</span>
                        <strong>{{ client.email || 'No email provided' }}</strong>
                    </div>

                    <div class="client-portfolio">
                        <span class="project-count">{{ client.projects_count }}</span>
                        <span>project{{ client.projects_count === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="entity-actions">
                        <Link :href="`/projects?q=${encodeURIComponent(client.name)}`" class="btn btn-sm btn-accent rounded-pill">View projects</Link>
                        <button v-if="client.can_edit" class="btn btn-sm btn-light action-icon" @click="openEdit(client)">Edit</button>
                        <button v-if="client.can_delete" class="btn btn-sm btn-light action-icon action-delete" @click="destroyClient(client)">Delete</button>
                    </div>
                </article>
            </div>

            <div v-else class="entity-empty-state">
                <strong>No clients found.</strong>
                <span>Try a broader search or add the first client to start building the workspace.</span>
            </div>

            <Pagination :links="clients.links" :meta="clients" />
        </section>

        <Modal v-model="modalOpen" :title="submitLabel">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Client name</label>
                    <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }">
                    <FormError :message="form.errors.name" />
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid-soft': form.errors.email }">
                    <FormError :message="form.errors.email" />
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
.client-index-header {
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

.client-list {
    margin-bottom: 1.5rem;
    border: 1px solid rgba(25, 55, 47, 0.1);
    border-radius: 1.35rem;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.55);
}

.client-list-labels,
.client-row {
    display: grid;
    grid-template-columns: minmax(230px, 1.7fr) minmax(185px, 1.4fr) minmax(110px, 0.7fr) auto;
    align-items: center;
    column-gap: 1.25rem;
}

.client-list-labels {
    padding: 0.7rem 1.25rem;
    border-bottom: 1px solid rgba(25, 55, 47, 0.08);
    background: rgba(238, 246, 242, 0.72);
    color: #789087;
    font-size: 0.67rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.client-row {
    position: relative;
    min-height: 100px;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(25, 55, 47, 0.08);
    background: rgba(255, 255, 255, 0.5);
    transition: background 180ms ease, box-shadow 180ms ease;
}

.client-row::before {
    position: absolute;
    top: 20%; bottom: 20%; left: 0;
    width: 3px;
    border-radius: 0 5px 5px 0;
    background: transparent;
    content: '';
    transition: background 180ms ease;
}

.client-row:last-child { border-bottom: 0; }
.client-row:hover {
    z-index: 1;
    background: #fff;
    box-shadow: 0 10px 26px rgba(22, 45, 38, 0.07);
}
.client-row:hover::before { background: #247b6e; }

.client-identity {
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

.entity-avatar-client {
    background: #eef4f1;
    color: #25564b;
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

.client-contact {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.client-contact strong {
    overflow: hidden;
    color: #3d5349;
    font-size: 0.88rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.client-portfolio {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #597168;
    font-size: 0.79rem;
    white-space: nowrap;
}

.project-count {
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

    .client-list-labels { display: none; }

    .client-row {
        grid-template-columns: 1fr;
        gap: 0.85rem;
        padding: 1rem;
    }

    .client-contact {
        display: grid;
        grid-template-columns: 80px 1fr;
        align-items: center;
    }

    .client-portfolio { width: fit-content; }
    .entity-actions { justify-content: flex-start; }
}
</style>
