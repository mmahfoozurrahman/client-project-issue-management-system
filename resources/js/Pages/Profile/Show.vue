<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
    breadcrumbs: Array,
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    avatar: null,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post('/profile', {
        forceFormData: true,
    });
};

const onAvatarChange = (event) => {
    form.avatar = event.target.files?.[0] ?? null;
};
</script>

<template>
    <Head title="Profile" />

    <AppLayout title="Profile" :breadcrumbs="breadcrumbs">
        <section class="panel-card profile-shell">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Account</p>
                    <h3 class="panel-title">Personal profile and avatar</h3>
                </div>
            </div>

            <div class="profile-grid">
                <div class="profile-preview-card">
                    <div class="profile-preview-avatar">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user.name">
                        <span v-else>{{ user?.name?.slice(0, 1) ?? 'U' }}</span>
                    </div>
                    <h4>{{ user?.name }}</h4>
                    <p>{{ user?.email }}</p>
                    <span class="table-pill">Visible in the sidebar</span>
                </div>

                <form class="vstack gap-3" @submit.prevent="submit">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }" required>
                        <FormError :message="form.errors.name" />
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid-soft': form.errors.email }" required>
                        <FormError :message="form.errors.email" />
                    </div>

                    <div>
                        <label class="form-label">Profile image</label>
                        <input type="file" accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': form.errors.avatar }" @change="onAvatarChange">
                        <FormError :message="form.errors.avatar" />
                    </div>

                    <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                        Save Profile
                    </button>
                </form>
            </div>
        </section>
    </AppLayout>
</template>
