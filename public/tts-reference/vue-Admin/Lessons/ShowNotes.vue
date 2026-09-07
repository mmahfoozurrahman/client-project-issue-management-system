<template>
    <AdminLayout :pending-count="0">
        <Head :title="lesson.title + ' - নোটসমূহ'" />

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3 a-breadcrumb animate-fade-in">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <Link :href="route('admin.lessons.index')">লেসন ও নোট ব্যবস্থাপনা</Link>
                </li>
                <li class="breadcrumb-item active" aria-current="page">সংরক্ষিত নোটসমূহ</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2 animate-fade-in">
            <div>
                <h4 class="fw-700 mb-1" style="color:#1a2e22;">
                    <i class="bi bi-journal-text me-2"></i>{{ lesson.title }}
                </h4>
                <p class="text-muted mb-0" style="font-size:.9rem;">লেসনটির ব্যবহারকারী নোট মনিটরিং</p>
            </div>
            <a v-if="lesson.vault_slug" :href="route('lessons.show', { vault: lesson.vault_slug, lesson: lesson.id })" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i>পাবলিক পেজ দেখুন
            </a>
        </div>

        <!-- Notes List -->
        <div class="notes-container animate-slide-up">
            <div v-for="note in notes" :key="note.id" class="admin-card mb-4">
                <!-- User Info Header -->
                <div class="admin-card-hd d-flex justify-content-between align-items-center bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar">{{ initials(note.user_name) }}</div>
                        <div>
                            <div class="fw-700 text-dark" style="font-size:.9rem;">{{ note.user_name }}</div>
                            <div class="text-muted" style="font-size:.78rem;">{{ note.user_email }}</div>
                        </div>
                    </div>
                    <div class="text-muted" style="font-size:.8rem;">
                        <i class="bi bi-clock me-1"></i>{{ formatBengaliDate(note.updated_at) }}
                    </div>
                </div>

                <!-- Note Content -->
                <div class="p-3">
                    <div class="note-view-container">
                        <div class="note-content-preview" v-html="renderedNoteContent(note.content)"></div>
                        
                        <!-- Images -->
                        <div v-if="note.images && note.images.length" class="mt-4 pt-3 border-top">
                            <h6 class="fw-700 text-secondary mb-2" style="font-size: .85rem;">
                                <i class="bi bi-images me-1 text-success"></i>সংযুক্ত ছবিসমূহ
                            </h6>
                            <div class="note-image-grid">
                                <a v-for="img in note.images" :key="img.id" :href="img.path" target="_blank" class="note-image-card">
                                    <img :src="img.path" :alt="img.original_name" />
                                    <div class="note-image-overlay">
                                        <i class="bi bi-zoom-in"></i>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Links -->
                        <div v-if="note.links && note.links.length" class="mt-4 pt-3 border-top">
                            <h6 class="fw-700 text-secondary mb-2" style="font-size: .85rem;">
                                <i class="bi bi-link-45deg me-1 text-success"></i>রেফারেন্স লিংকসমূহ
                            </h6>
                            <div class="note-links-list">
                                <a v-for="link in note.links" :key="link.id" :href="link.url" target="_blank" class="note-link-badge text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right me-1.5 text-success"></i>
                                    <span>{{ link.description || link.url }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!notes.length" class="admin-card p-5 text-center text-muted">
                <i class="bi bi-journal-x display-4 mb-3 d-block" style="color: #cbd5e1;"></i>
                <h5 class="fw-700 mb-1">কোনো নোট নেই</h5>
                <p class="mb-0" style="font-size: .9rem;">এই লেসনে এখন পর্যন্ত কোনো ব্যবহারকারী নোট যুক্ত করেননি।</p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBengaliDate } from '@/utils/date.js';
import { enhanceLessonContent, normalizeLessonHtml } from '@/utils/lessonContent';

const props = defineProps({
    lesson: { type: Object, default: () => ({}) },
    notes:  { type: Array,  default: () => [] },
});

const renderedNoteContent = (content) => enhanceLessonContent(normalizeLessonHtml(content ?? ''));

function initials(name) {
    if (!name || name === '—') return '?';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}
</script>

<style scoped>
.notes-container {
    --primary: #2d6a4f;
    --primary-light: #f2f7f4;
    --primary-dark: #1b4d36;
}

.admin-card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.07); overflow:hidden; }
.admin-card-hd { padding:.9rem 1.25rem; border-bottom:1px solid #f1f5f9; }

.user-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-size: .75rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── Note View Container & Preview ── */
.note-view-container {
    background: #fafbfd;
    border: 1px solid #eef2f6;
    border-radius: 12px;
    padding: 1.5rem;
}
.note-content-preview {
    font-size: .95rem;
    line-height: 1.8;
    color: #1a2333;
}
.note-content-preview :deep(h4) {
    margin-top: 1.25rem;
    font-weight: 700;
}
.note-content-preview :deep(p) {
    margin-bottom: .8rem;
}
.note-content-preview :deep(pre) {
    background: #1e293b;
    color: #e2e8f0;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    font-family: 'Courier New', monospace;
    font-size: .85rem;
    line-height: 1.6;
    overflow-x: auto;
    margin-bottom: 1.25rem;
}
.note-content-preview :deep(blockquote) {
    border-left: 4px solid var(--primary);
    padding-left: .75rem;
    color: #4b5563;
    margin: 1rem 0;
}
.note-content-preview :deep(ul),
.note-content-preview :deep(ol) {
    padding-left: 1.25rem;
    margin-bottom: .8rem;
}

/* ── Note Image Grid ── */
.note-image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: .75rem;
}
.note-image-card {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 1;
    border: 1px solid #e2e8f0;
    cursor: zoom-in;
    transition: transform .2s, box-shadow .2s;
}
.note-image-card:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.note-image-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.note-image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    opacity: 0;
    transition: opacity .2s;
}
.note-image-card:hover .note-image-overlay {
    opacity: 1;
}

/* ── Note Links ── */
.note-links-list {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.note-link-badge {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: .5rem .75rem;
    border-radius: 8px;
    color: var(--primary-dark);
    font-size: .8rem;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 1px 2px rgba(0,0,0,.02);
}
.note-link-badge:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    transform: translateY(-1px);
}
</style>
