<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/login');
</script>

<template>
    <Head title="Login" />

    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-copy">
                <span class="pill-tag">Multi-tenant Workspace</span>
                <h1>Sign in to manage clients, projects, and issues.</h1>
                <p>Super Admins can also access the user management area after login.</p>
            </div>

            <form class="auth-form" @submit.prevent="submit">
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-control form-control-lg" :class="{ 'is-invalid-soft': form.errors.email }" required>
                    <FormError :message="form.errors.email" />
                </div>

                <div>
                    <label class="form-label">Password</label>
                    <input v-model="form.password" type="password" class="form-control form-control-lg" :class="{ 'is-invalid-soft': form.errors.password }" required>
                    <FormError :message="form.errors.password" />
                </div>

                <label class="remember-check">
                    <input v-model="form.remember" type="checkbox">
                    <span>Remember me</span>
                </label>

                <button class="btn btn-accent btn-lg w-100 rounded-pill" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    Login
                </button>
            </form>
        </div>
    </div>
</template>
