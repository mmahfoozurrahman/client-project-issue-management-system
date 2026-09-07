<template>
    <AdminLayout :pending-count="0">
        <Head :title="lesson.title + ' - ইন্টারভিউ প্রশ্ন'" />

        <nav aria-label="breadcrumb" class="mb-3 a-breadcrumb animate-fade-in">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <Link :href="route('admin.lessons.index')">লেসন ও নোট ব্যবস্থাপনা</Link>
                </li>
                <li class="breadcrumb-item active" aria-current="page">ইন্টারভিউ প্রশ্ন</li>
            </ol>
        </nav>

        <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2 animate-fade-in">
            <div>
                <h4 class="fw-700 mb-1" style="color:#1a2e22;">
                    <i class="bi bi-question-circle me-2"></i>{{ lesson.title }}
                </h4>
                <p class="text-muted mb-0" style="font-size:.9rem;">
                    {{ lesson.vault_title }} · {{ lesson.folder_title }} — লেসনের সম্ভাব্য ইন্টারভিউ প্রশ্ন পরিচালনা করুন
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a v-if="lesson.vault_slug" :href="route('lessons.show', { vault: lesson.vault_slug, lesson: lesson.id })" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>পাবলিক পেজ
                </a>
                <button class="btn-green" @click="openCreate">
                    <i class="bi bi-plus-circle me-1"></i>প্রশ্ন যোগ করুন
                </button>
                <button class="btn btn-sm btn-outline-primary fw-600" @click="openGroqModal">
                    <i class="bi bi-stars me-1"></i>Groq দিয়ে তৈরি করুন
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-list-check"></i></div>
                    <div><div class="stat-value">{{ toBn(questions.length) }}</div><div class="stat-label">মোট প্রশ্ন</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-eye-fill"></i></div>
                    <div><div class="stat-value">{{ toBn(publishedCount) }}</div><div class="stat-label">প্রকাশিত</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef9c3;color:#ca8a04;"><i class="bi bi-pencil-square"></i></div>
                    <div><div class="stat-value">{{ toBn(draftCount) }}</div><div class="stat-label">ড্রাফট</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-stars"></i></div>
                    <div><div class="stat-value">{{ toBn(groqCount) }}</div><div class="stat-label">Groq উৎস</div></div>
                </div>
            </div>
        </div>

        <div class="admin-card animate-slide-up">
            <div class="admin-card-hd d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-700">প্রশ্ন তালিকা</h6>
                <small class="text-muted">প্রকাশিত প্রশ্ন শিক্ষার্থীরা দেখতে পাবে</small>
            </div>

            <div class="table-responsive">
                <table class="table table-sm mb-0 admin-table">
                    <thead>
                        <tr>
                            <th>ক্রম</th>
                            <th>প্রশ্ন</th>
                            <th>স্তর</th>
                            <th>উৎস</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in questions" :key="item.id">
                            <td class="text-muted">{{ toBn(item.sort_order) }}</td>
                            <td>
                                <div class="fw-700 text-dark question-text">{{ item.question }}</div>
                                <div v-if="item.answer_hint" class="text-muted mt-1 answer-preview" v-html="item.answer_hint"></div>
                            </td>
                            <td><span class="sbadge sbadge-level">{{ difficultyLabel(item.difficulty) }}</span></td>
                            <td><span class="sbadge" :class="sourceClass(item.source)">{{ sourceLabel(item.source) }}</span></td>
                            <td><span class="sbadge" :class="item.is_published ? 'sbadge-active' : 'sbadge-draft'">{{ item.is_published ? 'প্রকাশিত' : 'ড্রাফট' }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary py-1 px-2" @click="openEdit(item)" title="সম্পাদনা">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-1 px-2" @click="deleteQuestion(item)" title="মুছুন">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!questions.length">
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-question-circle display-6 d-block mb-2" style="color:#cbd5e1;"></i>
                                এই লেসনের জন্য এখনো কোনো ইন্টারভিউ প্রশ্ন যোগ করা হয়নি।
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="interviewQuestionModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form @submit.prevent="submitForm" novalidate>
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-700">{{ editingId ? 'প্রশ্ন সম্পাদনা' : 'নতুন প্রশ্ন যোগ করুন' }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" @click="form.clearErrors()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-600">ক্রম <span class="text-danger">*</span></label>
                                    <input v-model.number="form.sort_order" type="number" min="1" class="form-control" :class="{ 'is-invalid': form.errors.sort_order }">
                                    <FormError :message="form.errors.sort_order" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">স্তর</label>
                                    <select v-model="form.difficulty" class="form-select" :class="{ 'is-invalid': form.errors.difficulty }">
                                        <option value="easy">সহজ</option>
                                        <option value="medium">মাঝারি</option>
                                        <option value="hard">কঠিন</option>
                                    </select>
                                    <FormError :message="form.errors.difficulty" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-600">উৎস</label>
                                    <select v-model="form.source" class="form-select" :class="{ 'is-invalid': form.errors.source }">
                                        <option value="manual">Manual</option>
                                        <option value="external">External</option>
                                        <option value="groq">Groq</option>
                                    </select>
                                    <FormError :message="form.errors.source" />
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-600">প্রশ্ন <span class="text-danger">*</span></label>
                                <textarea v-model="form.question" rows="3" class="form-control" :class="{ 'is-invalid': form.errors.question }" placeholder="এই লেসন থেকে সম্ভাব্য ইন্টারভিউ প্রশ্ন লিখুন"></textarea>
                                <FormError :message="form.errors.question" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-600">উত্তরের ধারণা</label>
                                <RichEditor
                                    v-model="form.answer_hint"
                                    :invalid="!!form.errors.answer_hint"
                                    placeholder="সংক্ষিপ্ত উত্তর, hint, বা expected points লিখুন"
                                />
                                <FormError :message="form.errors.answer_hint" />
                            </div>

                            <div class="form-check">
                                <input v-model="form.is_published" type="checkbox" class="form-check-input" id="questionPublished">
                                <label class="form-check-label fw-600" for="questionPublished">শিক্ষার্থীদের জন্য প্রকাশ করুন</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>সংরক্ষণ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="groqGenerateModal" tabindex="-1">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <form @submit.prevent="generateWithGroq" novalidate>
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-700">
                                <i class="bi bi-stars me-2 text-primary"></i>Groq দিয়ে ড্রাফট তৈরি
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" @click="groqForm.clearErrors()"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3" style="font-size:.9rem;">
                                এই লেসনের কনটেন্ট থেকে প্রশ্ন ও rich formatted answer draft তৈরি হবে। এগুলো ড্রাফট হিসেবে থাকবে, রিভিউ করার পর প্রকাশ করতে পারবেন।
                            </p>
                            <label class="form-label fw-600">এই লেসন কভার করতে কতটি প্রশ্ন তৈরি করবেন?</label>
                            <input v-model.number="groqForm.count" type="number" min="1" max="20" class="form-control" :class="{ 'is-invalid': groqForm.errors.count || groqForm.errors.groq }">
                            <FormError :message="groqForm.errors.count || groqForm.errors.groq" />
                            <div class="text-muted mt-2" style="font-size:.82rem;">
                                সিস্টেমের সাজেশন: {{ toBn(lesson.recommended_question_count ?? 5) }}টি। চাইলে আপনি এটি কম বা বেশি করতে পারবেন।
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                            <button type="submit" class="btn btn-primary fw-600" :disabled="groqForm.processing">
                                <span v-if="groqForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-stars me-1"></i>জেনারেট করুন
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormError from '@/Components/FormError.vue';
import RichEditor from '@/Components/RichEditor.vue';
import { useNotify } from '@/composables/useNotify.js';

const props = defineProps({
    lesson: { type: Object, default: () => ({}) },
    questions: { type: Array, default: () => [] },
});

const { toast, confirm } = useNotify();
const editingId = ref(null);

const publishedCount = computed(() => props.questions.filter((item) => item.is_published).length);
const draftCount = computed(() => props.questions.length - publishedCount.value);
const groqCount = computed(() => props.questions.filter((item) => item.source === 'groq').length);
const nextOrder = computed(() => (props.questions.length ? Math.max(...props.questions.map((item) => item.sort_order)) + 1 : 1));

const form = useForm({
    question: '',
    answer_hint: '',
    difficulty: 'medium',
    sort_order: 1,
    source: 'manual',
    is_published: false,
});

const groqForm = useForm({
    count: props.lesson.recommended_question_count ?? 5,
});

function modal() {
    return new Modal(document.getElementById('interviewQuestionModal'));
}

function groqModal() {
    return new Modal(document.getElementById('groqGenerateModal'));
}

function openCreate() {
    editingId.value = null;
    form.reset();
    form.question = '';
    form.answer_hint = '';
    form.difficulty = 'medium';
    form.sort_order = nextOrder.value;
    form.source = 'manual';
    form.is_published = false;
    form.clearErrors();
    modal().show();
}

function openEdit(item) {
    editingId.value = item.id;
    form.question = item.question;
    form.answer_hint = item.answer_hint ?? '';
    form.difficulty = item.difficulty ?? 'medium';
    form.sort_order = item.sort_order;
    form.source = item.source ?? 'manual';
    form.is_published = item.is_published;
    form.clearErrors();
    modal().show();
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            Modal.getInstance(document.getElementById('interviewQuestionModal'))?.hide();
            toast.success(editingId.value ? 'প্রশ্ন আপডেট হয়েছে।' : 'প্রশ্ন সংরক্ষণ হয়েছে।');
            editingId.value = null;
            form.reset();
        },
        onError: () => toast.error('প্রশ্ন সংরক্ষণ করা যায়নি।'),
    };

    if (editingId.value) {
        form.put(route('admin.lessons.interview-questions.update', [props.lesson.id, editingId.value]), options);
        return;
    }

    form.post(route('admin.lessons.interview-questions.store', props.lesson.id), options);
}

function openGroqModal() {
    groqForm.count = props.lesson.recommended_question_count ?? 5;
    groqForm.clearErrors();
    groqModal().show();
}

function generateWithGroq() {
    groqForm.post(route('admin.lessons.interview-questions.groq', props.lesson.id), {
        preserveScroll: true,
        onSuccess: () => {
            Modal.getInstance(document.getElementById('groqGenerateModal'))?.hide();
            toast.success('Groq draft প্রশ্ন তৈরি হয়েছে। এখন রিভিউ করে প্রকাশ করুন।');
        },
        onError: () => toast.error('Groq দিয়ে প্রশ্ন তৈরি করা যায়নি।'),
    });
}

async function deleteQuestion(item) {
    const ok = await confirm('এই প্রশ্নটি মুছবেন?', item.question, { icon: 'warning', confirmText: 'মুছুন' });
    if (!ok) return;

    router.delete(route('admin.lessons.interview-questions.destroy', [props.lesson.id, item.id]), {
        preserveScroll: true,
        onSuccess: () => toast.success('প্রশ্ন মুছে ফেলা হয়েছে।'),
        onError: () => toast.error('প্রশ্ন মুছতে সমস্যা হয়েছে।'),
    });
}

function difficultyLabel(value) {
    return { easy: 'সহজ', medium: 'মাঝারি', hard: 'কঠিন' }[value] ?? value;
}

function sourceLabel(value) {
    return { manual: 'Manual', external: 'External', groq: 'Groq' }[value] ?? 'Manual';
}

function sourceClass(value) {
    if (value === 'groq') return 'sbadge-groq';
    if (value === 'external') return 'sbadge-external';
    return 'sbadge-manual';
}

function toBn(val) {
    return String(val ?? 0).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
}
</script>

<style scoped>
.stat-card { background:#fff;border-radius:10px;padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem;box-shadow:0 1px 4px rgba(0,0,0,.07); }
.stat-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
.stat-value { font-size:1.3rem;font-weight:700;color:#111827;line-height:1.2; }
.stat-label { font-size:.78rem;color:#6b7280;margin-top:2px; }
.btn-green { background:#2d6a4f;color:#fff;border:none;border-radius:8px;padding:.45rem 1rem;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center; }
.btn-green:hover { background:#245a42; }
.admin-card { background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden; }
.admin-card-hd { padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9; }
.admin-table th { font-size:.78rem;font-weight:600;color:#6b7280;background:#f8fafc;padding:.6rem 1rem;white-space:nowrap; }
.admin-table td { padding:.75rem 1rem;vertical-align:middle; }
.admin-table tbody tr:hover { background:#f8fafc; }
.question-text { max-width: 620px; font-size:.9rem; line-height:1.55; }
.answer-preview { max-width:620px; font-size:.8rem; line-height:1.55; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.answer-preview :deep(p) { margin-bottom: .25rem; }
.answer-preview :deep(ul),
.answer-preview :deep(ol) { margin-bottom: .25rem; padding-left: 1.1rem; }
.sbadge { font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap; }
.sbadge-active { background:#dcfce7;color:#15803d; }
.sbadge-draft { background:#fef9c3;color:#a16207; }
.sbadge-level { background:#e0f2fe;color:#0369a1; }
.sbadge-manual { background:#e5e7eb;color:#4b5563; }
.sbadge-external { background:#f3e8ff;color:#6d28d9; }
.sbadge-groq { background:#dcfce7;color:#15803d; }
</style>
