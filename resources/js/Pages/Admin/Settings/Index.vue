<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../../Components/FormError.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    breadcrumbs: Array,
});

const form = useForm({
    site_name: props.settings?.site_name ?? '',
});

const submit = () => {
    form.put('/admin/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Site Settings" />

    <AdminLayout title="Site Settings" :breadcrumbs="breadcrumbs">
        <section class="panel-card settings-shell">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Branding</p>
                    <h3 class="panel-title">Control the workspace identity</h3>
                </div>
            </div>

            <div class="settings-grid">
                <div class="settings-preview-card">
                    <span class="pill-tag">Live preview</span>
                    <h4>{{ form.site_name || 'Issue Tracker' }}</h4>
                    <p>This name appears in the top-left sidebar brand area throughout the app.</p>
                </div>

                <form class="vstack gap-3" @submit.prevent="submit">
                    <div>
                        <label class="form-label">Site name</label>
                        <input v-model="form.site_name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.site_name }" required>
                        <FormError :message="form.errors.site_name" />
                    </div>

                    <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                        Save Settings
                    </button>
                </form>
            </div>
        </section>
    </AdminLayout>
</template>
