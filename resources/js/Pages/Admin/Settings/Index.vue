<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../../Components/FormError.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    breadcrumbs: Array,
});

const form = useForm({
    site_name: props.settings?.site_name ?? '',
    issue_daily_target: props.settings?.issue_daily_target ?? 3,
    issue_stale_days: props.settings?.issue_stale_days ?? 3,
    issue_critical_days: props.settings?.issue_critical_days ?? 7,

    gemini_api_key: '',
    gemini_tts_model: props.settings?.gemini_tts_model ?? 'gemini-2.5-flash-preview-tts',
    gemini_tts_voice_name: props.settings?.gemini_tts_voice_name ?? 'Kore',
    gemini_tts_prompt: props.settings?.gemini_tts_prompt ?? '',
    gemini_tts_chunk_chars: props.settings?.gemini_tts_chunk_chars ?? 4800,
    gemini_tts_max_rpm: props.settings?.gemini_tts_max_rpm ?? 10,
    gemini_tts_max_rpd: props.settings?.gemini_tts_max_rpd ?? 100,
    gemini_tts_chunk_delay: props.settings?.gemini_tts_chunk_delay ?? 3,
});

const geminiVoices = [
    { value: 'Kore', label: 'Kore (Female voice — recommended)' },
    { value: 'Puck', label: 'Puck (Male voice)' },
    { value: 'Fenrir', label: 'Fenrir (Deep male voice)' },
    { value: 'Aoede', label: 'Aoede (Female voice)' },
    { value: 'Leda', label: 'Leda (Female voice)' },
    { value: 'Zephyr', label: 'Zephyr (Female voice)' },
    { value: 'Charon', label: 'Charon (Male voice)' },
];

const showApiKey = ref(false);

const submit = () => {
    form.put('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => {
            form.gemini_api_key = '';
        },
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
                        <input v-model="form.site_name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.site_name }">
                        <FormError :message="form.errors.site_name" />
                    </div>

                    <div>
                        <label class="form-label">Issue daily target</label>
                        <input v-model.number="form.issue_daily_target" type="number" min="1" max="50" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_daily_target }">
                        <small class="text-muted d-block mt-1">Used by Kanban "Today&apos;s target" widget. Range: 1 to 50.</small>
                        <FormError :message="form.errors.issue_daily_target" />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Stale threshold (days)</label>
                            <input v-model.number="form.issue_stale_days" type="number" min="1" max="60" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_stale_days }">
                            <small class="text-muted d-block mt-1">Issues idle beyond this are treated as at-risk.</small>
                            <FormError :message="form.errors.issue_stale_days" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Critical threshold (days)</label>
                            <input v-model.number="form.issue_critical_days" type="number" min="1" max="120" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_critical_days }">
                            <small class="text-muted d-block mt-1">Must be greater than or equal to stale threshold.</small>
                            <FormError :message="form.errors.issue_critical_days" />
                        </div>
                    </div>

                    <hr class="my-2">

                    <div>
                        <p class="section-kicker mb-1">Google Gemini TTS — Bengali audio narration</p>
                        <p class="text-muted small mb-3">Configure the Gemini text-to-speech settings used to generate Bengali narration audio for issues.</p>
                    </div>

                    <div>
                        <label class="form-label">Gemini API key</label>
                        <div class="input-group">
                            <input
                                v-model="form.gemini_api_key"
                                :type="showApiKey ? 'text' : 'password'"
                                class="form-control"
                                :class="{ 'is-invalid-soft': form.errors.gemini_api_key }"
                                :placeholder="settings?.gemini_api_key_set ? 'Leave blank to keep the current key' : 'Enter Gemini API key'"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" @click="showApiKey = !showApiKey">
                                {{ showApiKey ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <span v-if="settings?.gemini_api_key_set">A key is currently configured. Leave blank to keep it, or type a new one to replace it.</span>
                            <span v-else>Falls back to <code>GEMINI_API_KEY</code> in .env when left empty.</span>
                        </small>
                        <FormError :message="form.errors.gemini_api_key" />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Gemini TTS model</label>
                            <input v-model="form.gemini_tts_model" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_model }">
                            <FormError :message="form.errors.gemini_tts_model" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Bengali voice</label>
                            <select v-model="form.gemini_tts_voice_name" class="form-select" :class="{ 'is-invalid-soft': form.errors.gemini_tts_voice_name }">
                                <option v-for="voice in geminiVoices" :key="voice.value" :value="voice.value">{{ voice.label }}</option>
                            </select>
                            <FormError :message="form.errors.gemini_tts_voice_name" />
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Gemini TTS prompt</label>
                        <textarea v-model="form.gemini_tts_prompt" rows="3" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_prompt }" />
                        <small class="text-muted d-block mt-1">Leave blank to use the built-in default narration prompt.</small>
                        <FormError :message="form.errors.gemini_tts_prompt" />
                    </div>

                    <div>
                        <p class="section-kicker mb-1">Google AI Studio rate limits &amp; quota safety</p>
                        <p class="text-muted small mb-3">Google AI Studio's free tier enforces per-minute and daily request quotas. Set local limits so the app self-throttles instead of hitting audio errors.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Max requests / minute (RPM)</label>
                            <input v-model.number="form.gemini_tts_max_rpm" type="number" min="1" max="1000" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_max_rpm }">
                            <FormError :message="form.errors.gemini_tts_max_rpm" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max requests / day (RPD)</label>
                            <input v-model.number="form.gemini_tts_max_rpd" type="number" min="1" max="100000" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_max_rpd }">
                            <FormError :message="form.errors.gemini_tts_max_rpd" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Delay per chunk (seconds)</label>
                            <input v-model.number="form.gemini_tts_chunk_delay" type="number" min="0" max="60" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_chunk_delay }">
                            <FormError :message="form.errors.gemini_tts_chunk_delay" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max characters / chunk</label>
                            <input v-model.number="form.gemini_tts_chunk_chars" type="number" min="200" max="8000" class="form-control" :class="{ 'is-invalid-soft': form.errors.gemini_tts_chunk_chars }">
                            <FormError :message="form.errors.gemini_tts_chunk_chars" />
                        </div>
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
