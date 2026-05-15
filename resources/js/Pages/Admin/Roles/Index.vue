<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../../Components/FormError.vue';
import Modal from '../../../Components/Modal.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    roles:        { type: Array, default: () => [] },
    permissions:  { type: Array, default: () => [] },
    breadcrumbs:  { type: Array, default: () => [] },
});

const createModalOpen    = ref(false);
const editModalOpen      = ref(false);
const permModalOpen      = ref(false);
const activeRole         = ref(null);

const createForm = useForm({ name: '', slug: '' });
const editForm   = useForm({ name: '', slug: '' });
const permForm   = useForm({ permission_ids: [] });

const openCreate = () => {
    createForm.reset();
    createModalOpen.value = true;
};

const openEdit = (role) => {
    activeRole.value = role;
    editForm.name    = role.name;
    editForm.slug    = role.slug;
    editModalOpen.value = true;
};

const openPermissions = (role) => {
    activeRole.value           = role;
    permForm.permission_ids    = role.permissions.map((p) => p.id);
    permModalOpen.value        = true;
};

const submitCreate = () => {
    createForm.post('/admin/roles', {
        onSuccess: () => { createModalOpen.value = false; createForm.reset(); },
    });
};

const submitEdit = () => {
    editForm.put(`/admin/roles/${activeRole.value.id}`, {
        onSuccess: () => { editModalOpen.value = false; },
    });
};

const submitPermissions = () => {
    permForm.put(`/admin/roles/${activeRole.value.id}/permissions`, {
        onSuccess: () => { permModalOpen.value = false; },
    });
};

const togglePermission = (permId) => {
    const idx = permForm.permission_ids.indexOf(permId);
    if (idx === -1) {
        permForm.permission_ids.push(permId);
    } else {
        permForm.permission_ids.splice(idx, 1);
    }
};

const destroyRole = (role) => {
    Swal.fire({
        title: `Delete role "${role.name}"?`,
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
        }
    });
};
</script>

<template>
    <Head title="Roles" />

    <AppLayout title="Roles" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Access Control</p>
                    <h3 class="panel-title">Roles Management</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">+ Create Role</button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Permissions</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(role, index) in roles" :key="role.id">
                            <td>{{ index + 1 }}</td>
                            <td><strong>{{ role.name }}</strong><br><small class="text-muted">{{ role.slug }}</small></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <span
                                        v-for="perm in role.permissions"
                                        :key="perm.id"
                                        class="badge rounded-pill text-bg-light border"
                                    >
                                        {{ perm.slug }}
                                    </span>
                                    <span v-if="!role.permissions.length" class="text-muted small">No permissions</span>
                                </div>
                            </td>
                            <td>{{ new Date(role.created_at).toLocaleDateString() }}</td>
                            <td class="text-end">
                                <div class="table-actions justify-content-end">
                                    <button class="btn btn-sm btn-light rounded-pill" @click="openPermissions(role)">Permissions</button>
                                    <button class="btn btn-sm btn-light rounded-pill" @click="openEdit(role)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" @click="destroyRole(role)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!roles.length">
                            <td colspan="5"><div class="table-empty">No roles found.</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal v-model="createModalOpen" title="Create Role">
            <form class="vstack gap-3" @submit.prevent="submitCreate">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="createForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': createForm.errors.name }">
                    <FormError :message="createForm.errors.name" />
                </div>
                <div>
                    <label class="form-label">Slug</label>
                    <input v-model="createForm.slug" type="text" class="form-control" :class="{ 'is-invalid-soft': createForm.errors.slug }" placeholder="e.g. developer">
                    <FormError :message="createForm.errors.slug" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="createForm.processing">
                    <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Role
                </button>
            </form>
        </Modal>

        <Modal v-model="editModalOpen" title="Edit Role">
            <form class="vstack gap-3" @submit.prevent="submitEdit">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="editForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': editForm.errors.name }">
                    <FormError :message="editForm.errors.name" />
                </div>
                <div>
                    <label class="form-label">Slug</label>
                    <input v-model="editForm.slug" type="text" class="form-control" :class="{ 'is-invalid-soft': editForm.errors.slug }">
                    <FormError :message="editForm.errors.slug" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="editForm.processing">
                    <span v-if="editForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Save Changes
                </button>
            </form>
        </Modal>

        <Modal v-model="permModalOpen" :title="`Permissions — ${activeRole?.name}`" size="modal-lg">
            <form class="vstack gap-3" @submit.prevent="submitPermissions">
                <p class="text-muted small mb-0">Toggle permissions for this role. Changes take effect immediately on save.</p>
                <div class="row g-2">
                    <div v-for="perm in permissions" :key="perm.id" class="col-md-6">
                        <div
                            class="form-check border rounded p-3 cursor-pointer"
                            :class="permForm.permission_ids.includes(perm.id) ? 'border-primary bg-light' : ''"
                            style="cursor:pointer"
                            @click="togglePermission(perm.id)"
                        >
                            <input
                                :id="`perm-${perm.id}`"
                                type="checkbox"
                                class="form-check-input"
                                :checked="permForm.permission_ids.includes(perm.id)"
                                @click.stop="togglePermission(perm.id)"
                            >
                            <label :for="`perm-${perm.id}`" class="form-check-label ms-2" style="cursor:pointer">
                                <strong class="d-block">{{ perm.name }}</strong>
                                <small class="text-muted">{{ perm.slug }}</small>
                            </label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="permForm.processing">
                    <span v-if="permForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Save Permissions
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
