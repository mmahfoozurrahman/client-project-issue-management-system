<template>
    <AppLayout :has-sidebar="true">
        <Head :title="`${lesson.title} — ${vault.title}`" />

        <!-- ── Reading progress bar ── -->
        <Teleport to="body">
            <div class="read-progress-track">
                <div class="read-progress-fill" :style="{ width: readProgress + '%' }"></div>
            </div>
        </Teleport>
        <LessonNavSidebar
            :all-lessons="localLessons"
            :all-folders="localFolders"
            :completed-count="completedCount"
            :total-count="totalCount"
            :current-lesson-id="lesson.id"
            :vault="vault"
            :folder="folder"
            :has-subscription="hasSubscription"
        />

        <main class="main-content" id="mainContent">
            <Transition name="sk-fade" mode="out-in">

                <!-- Skeleton loader -->
                <div v-if="isLoading" class="content-section">
                    <div class="sk-line mb-2" style="width:40%;height:.8rem;"></div>
                    <div class="sk-line mb-4" style="width:75%;height:1.75rem;"></div>
                    <div v-for="n in 7" :key="n" class="sk-line mb-3"
                         :style="{ height: '.9rem', width: (55 + n * 6) + '%' }"></div>
                </div>

                <!-- Real content -->
                <div v-else>

                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item">
                                <Link :href="route('vaults')" class="text-decoration-none" style="color:var(--primary);">
                                    ভল্টসমূহ
                                </Link>
                            </li>
                            <li class="breadcrumb-item">
                                <Link :href="route('vaults.show', vault.slug)" class="text-decoration-none" style="color:var(--primary);">
                                    {{ vault.title }}
                                </Link>
                            </li>
                            <li class="breadcrumb-item active text-muted">{{ lesson.title }}</li>
                        </ol>
                    </nav>

                    <!-- Came-from link — shows where the learner was reading before this lesson -->
                    <div v-if="cameFrom" class="came-from-banner mb-3">
                        <i class="bi bi-arrow-90deg-left"></i>
                        <span>
                            আপনি
                            <template v-if="!cameFrom.same_vault">
                                <strong>{{ cameFrom.vault_title }}</strong> ভল্টের
                            </template>
                            <template v-else>এই ভল্টের</template>
                            একটি লেসন দেখছিলেন —
                        </span>
                        <Link
                            :href="route('lessons.show', { vault: cameFrom.vault_slug, lesson: cameFrom.lesson_id })"
                            class="fw-600 text-decoration-none"
                        >
                            {{ cameFrom.lesson_title }}
                        </Link>
                    </div>

                    <!-- ── Lesson card ─────────────────────────────── -->
                    <div class="content-section">

                        <!-- Topic header -->
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 pb-3 border-bottom">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <span class="badge rounded-pill text-bg-secondary" style="font-size:.72rem;">
                                        লেসন {{ toBengaliNumber(lesson.sort_order) }}
                                    </span>
                                    <span class="text-muted" style="font-size:.8rem;">
                                        <i class="bi bi-clock me-1"></i>{{ lesson.reading_time != null ? toBengaliNumber(lesson.reading_time) : '—' }} মিনিট
                                    </span>
                                    <span class="text-muted" style="font-size:.8rem;">
                                        <i class="bi bi-bar-chart me-1"></i>{{ difficultyLabel }}
                                    </span>
                                    <span v-if="lesson.is_free" class="badge text-bg-success" style="font-size:.68rem;">ফ্রি</span>
                                    <span class="text-muted" style="font-size:.8rem;" v-if="lesson.updated_at">
                                        <i class="bi bi-calendar3 me-1"></i>আপডেট: {{ formatUpdatedDate(lesson.updated_at) }}
                                    </span>
                                </div>
                                <h2 class="fw-800 mb-1">{{ lesson.title }}</h2>
                                <div class="text-muted" style="font-size:.875rem;">{{ vault.title }} · {{ folder.title }}</div>
                            </div>

                            <!-- Action Buttons Group (PDF & Bookmark) -->
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Riya Narration Button -->
                                <button
                                    v-if="shouldShowLessonNarration"
                                    type="button"
                                    class="btn btn-sm fw-600 d-inline-flex align-items-center gap-1.5"
                                    :class="lessonNarration.isPlaying.value ? 'btn-success' : lessonNarration.isPaused.value ? 'btn-outline-success' : 'btn-outline-primary'"
                                    :disabled="!lessonNarration.supported.value"
                                    @click="handleNarrationClick"
                                    :aria-pressed="lessonNarration.isPlaying.value"
                                    :title="lessonNarration.buttonLabel.value"
                                >
                                    <span v-if="narrationLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <i v-else-if="lessonNarration.isPlaying.value" class="bi bi-pause-fill"></i>
                                    <i v-else-if="lessonNarration.isPaused.value" class="bi bi-play-fill"></i>
                                    <i v-else class="bi bi-volume-up"></i>
                                    <span>{{ lessonNarration.buttonLabel.value }}</span>
                                </button>
                                <!-- PDF / Print Button -->
                                <button
                                    class="btn btn-sm btn-outline-primary fw-600 d-inline-flex align-items-center gap-1.5"
                                    @click="downloadPDF"
                                >
                                    <i class="bi bi-file-pdf"></i>&nbsp;
                                    <span>PDF / প্রিন্ট</span>
                                </button>

                                <!-- Bookmark button (auth only) -->
                                <AppButton
                                    v-if="authUser"
                                    :loading="bookmarkForm.processing"
                                    class="btn btn-sm fw-600"
                                    :class="localBookmarked ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="toggleBookmark"
                                >
                                    <i class="bi me-1" :class="localBookmarked ? 'bi-bookmark-fill' : 'bi-bookmark'"></i>
                                    {{ localBookmarked ? 'সংরক্ষিত' : 'বুকমার্ক' }}
                                </AppButton>
                                <button
                                    v-if="hasInterviewQuestions"
                                    type="button"
                                    class="btn btn-sm btn-outline-success fw-600 d-inline-flex align-items-center gap-1.5"
                                    @click="openInterviewModal"
                                >
                                    <i class="bi bi-question-circle"></i>&nbsp;
                                    <span>ইন্টারভিউ প্রশ্ন</span>
                                </button>
                                <button
                                    v-if="authUser && lesson.can_access"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary fw-600 d-inline-flex align-items-center gap-1.5"
                                    @click="openAiCompanionModal('explain_lesson')"
                                >
                                    <i class="bi bi-stars"></i>&nbsp;
                                    <span>AI Companion</span>
                                </button>
                            </div>
                        </div>

                        <!-- Narration seek slider (own row so it never pushes other action buttons) -->
                        <div
                            v-if="shouldShowLessonNarration && (lessonNarration.isPlaying.value || lessonNarration.isPaused.value)"
                            class="d-flex align-items-center gap-2 narration-seek mb-3"
                        >
                            <small class="text-muted narration-time">{{ formatNarrationTime(lessonNarration.currentTime.value) }}</small>
                            <input
                                type="range"
                                class="form-range narration-range"
                                min="0"
                                :max="lessonNarration.duration.value || 0"
                                step="0.1"
                                :value="lessonNarration.currentTime.value"
                                @input="onNarrationSeek"
                            />
                            <small class="text-muted narration-time">{{ formatNarrationTime(lessonNarration.duration.value) }}</small>
                        </div>

                        <!-- Locked lesson: partial preview + subscribe CTA -->
                        <div v-if="!lesson.can_access">
                            <div class="locked-preview-wrap">
                                <SegmentedContent
                                    :segments="renderedSegments"
                                    class="content-protected"
                                />
                                <div class="locked-preview-fade"></div>
                            </div>

                            <div class="text-center py-5">
                                <i class="bi bi-lock fs-1 text-muted d-block mb-3"></i>
                                <h5 class="text-muted fw-700">এই লেসনটি সাবস্ক্রিপশনের প্রয়োজন</h5>
                                <p class="text-muted mb-4" style="font-size:.9rem;">
                                    সম্পূর্ণ কোর্স আনলক করতে একটি প্ল্যান বেছে নিন।
                                </p>
                                <Link href="/checkout?plan=monthly" class="btn btn-primary fw-700 px-5">
                                    <i class="bi bi-unlock me-2"></i>এখনই সাবস্ক্রাইব করুন — ৳{{ toBengaliNumber(500) }}/মাস
                                </Link>
                            </div>
                        </div>

                        <!-- Lesson content -->
                        <div v-else>
                            <SegmentedContent
                                ref="contentRef"
                                :segments="renderedSegments"
                                :class="{ 'content-protected': contentProtection && !canHighlightAndSearch }"
                                @content-click="handleLessonContentClick"
                                @revealed="onSegmentRevealed"
                            />

                            <!-- Last Updated & PDF (Bottom) -->
                            <div class="d-flex align-items-center justify-content-between mt-3 text-muted flex-wrap gap-2" style="font-size: 0.8rem;" v-if="lesson.updated_at">
                                <button 
                                    class="btn btn-link text-muted p-0 d-inline-flex align-items-center gap-1.5 text-decoration-none hover-primary-link" 
                                    @click="downloadPDF" 
                                    style="font-size: 0.8rem; border: none; background: transparent; cursor: pointer;"
                                >
                                    <i class="bi bi-printer-fill text-primary"></i>&nbsp;
                                    <span>PDF ডাউনলোড / প্রিন্ট করুন</span>
                                </button>
                                <div class="d-flex align-items-center gap-1.5">
                                    <i class="bi bi-clock-history text-primary"></i>&nbsp;
                                    <span>সর্বশেষ আপডেট: <strong class="text-dark">{{ formatUpdatedDate(lesson.updated_at) }}</strong></span>
                                </div>
                            </div>

                            <!-- Prev / Next / Complete row -->
                            <div class="d-flex justify-content-between align-items-center pt-4 mt-4 border-top gap-2 flex-wrap">

                                <!-- Prev -->
                                <Link
                                    v-if="prevLessonData"
                                    :href="route('lessons.show', { vault: vault.slug, lesson: prevLessonData.id })"
                                    class="btn btn-outline-secondary fw-600"
                                >
                                    <i class="bi bi-arrow-left me-1"></i>পূর্ববর্তী
                                </Link>
                                <span v-else class="btn btn-outline-secondary fw-600 disabled opacity-50">
                                    <i class="bi bi-arrow-left me-1"></i>পূর্ববর্তী
                                </span>

                                <!-- Mark complete / toggle -->
                                <template v-if="authUser">
                                    <AppButton
                                        v-if="!isCompleted"
                                        :loading="completeForm.processing"
                                        class="btn btn-outline-success fw-600 px-4"
                                        @click="submitToggleComplete"
                                    >
                                        <i class="bi bi-check2 me-1"></i>পড়া সম্পন্ন হয়েছে?
                                    </AppButton>
                                    <button
                                        v-else
                                        class="btn btn-success fw-600 px-4 d-inline-flex align-items-center gap-2"
                                        :disabled="completeForm.processing"
                                        @click="submitToggleComplete"
                                        title="আবার পড়তে চাইলে ক্লিক করুন"
                                    >
                                        <i class="bi bi-check-circle-fill"></i>
                                        পড়া সম্পন্ন
                                        <span
                                            v-if="completionCount > 1"
                                            class="badge bg-success rounded-pill"
                                            style="font-size:.68rem;"
                                        >×{{ toBengaliNumber(completionCount) }}</span>
                                    </button>
                                </template>

                                <!-- Next -->
                                <template v-if="nextLessonData">
                                    <Link
                                        v-if="nextLessonData.can_access"
                                        :href="route('lessons.show', { vault: vault.slug, lesson: nextLessonData.id })"
                                        class="btn btn-primary fw-600"
                                    >
                                        পরবর্তী <i class="bi bi-arrow-right ms-1"></i>
                                    </Link>
                                    <Link v-else href="/checkout?plan=monthly" class="btn btn-primary fw-600">
                                        পরবর্তী <i class="bi bi-lock ms-1" style="font-size:.8rem;"></i>
                                    </Link>
                                </template>
                                <span v-else class="btn btn-primary fw-600 disabled opacity-50">পরবর্তী</span>
                            </div>
                        </div>

                    </div><!-- /lesson card -->

                    <!-- ── Note Display Panel (auth + accessible only) ── -->
                    <!-- Focus music control -->
                    <Teleport to="body">
                        <div v-if="showReadingSoundControl" class="reading-sound-control">
                            <button
                                type="button"
                                class="reading-sound-button"
                                :class="{ active: isReadingSoundPlaying }"
                                @click="toggleReadingSound"
                                :aria-pressed="isReadingSoundPlaying"
                            >
                                <i class="bi" :class="isReadingSoundPlaying ? 'bi-pause-fill' : 'bi-music-note-beamed'"></i>
                                <span>{{ isReadingSoundPlaying ? 'ফোকাস সাউন্ড বন্ধ করুন' : 'ফোকাস সাউন্ড চালু করুন' }}</span>
                            </button>
                        </div>

                        <!-- Mobile Floating Narration Player -->
                        <Transition name="floating-audio">
                            <div
                                v-if="shouldShowLessonNarration && (lessonNarration.isPlaying.value || lessonNarration.isPaused.value || narrationLoading)"
                                class="mobile-floating-audio-bar"
                            >
                                <div class="floating-audio-inner">
                                    <div class="floating-audio-left">
                                        <button
                                            type="button"
                                            class="floating-audio-play-btn"
                                            :class="lessonNarration.isPlaying.value ? 'is-playing' : ''"
                                            :disabled="!lessonNarration.supported.value"
                                            @click="handleNarrationClick"
                                            :aria-label="lessonNarration.isPlaying.value ? 'পজ করুন' : 'প্লে করুন'"
                                        >
                                            <span v-if="narrationLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <i v-else-if="lessonNarration.isPlaying.value" class="bi bi-pause-fill"></i>
                                            <i v-else class="bi bi-play-fill"></i>
                                        </button>
                                    </div>

                                    <div class="floating-audio-track">
                                        <div class="floating-audio-info">
                                            <span class="floating-audio-title">
                                                <i class="bi bi-soundwave me-1"></i>রিয়া অডিও
                                            </span>
                                            <span class="floating-audio-time">
                                                {{ formatNarrationTime(lessonNarration.currentTime.value) }} / {{ formatNarrationTime(lessonNarration.duration.value) }}
                                            </span>
                                        </div>
                                        <div class="floating-audio-slider-wrap">
                                            <button
                                                type="button"
                                                class="floating-audio-skip-btn"
                                                @click="skipNarrationTime(-5)"
                                                title="৫ সেকেন্ড পেছনে"
                                                aria-label="৫ সেকেন্ড পেছনে"
                                            >
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                <span class="skip-sec">৫</span>
                                            </button>
                                            <input
                                                type="range"
                                                class="form-range floating-narration-range"
                                                min="0"
                                                :max="lessonNarration.duration.value || 0"
                                                step="0.1"
                                                :value="lessonNarration.currentTime.value"
                                                @input="onNarrationSeek"
                                            />
                                            <button
                                                type="button"
                                                class="floating-audio-skip-btn"
                                                @click="skipNarrationTime(5)"
                                                title="৫ সেকেন্ড সামনে"
                                                aria-label="৫ সেকেন্ড সামনে"
                                            >
                                                <i class="bi bi-arrow-clockwise"></i>
                                                <span class="skip-sec">৫</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </Teleport>

                    <div v-if="authUser && lesson.can_access && canNote" class="content-section mt-0">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                            <h5 class="fw-800 mb-0">
                                <i class="bi bi-journal-text me-2 text-primary"></i>আমার নোট
                            </h5>
                            <div class="d-flex gap-2">
                                <button v-if="hasNoteContent" class="btn btn-sm btn-outline-danger fw-600 animate-fade-in" @click="clearNote">
                                    <i class="bi bi-trash me-1"></i>মুছুন
                                </button>
                                <button
                                    class="btn btn-sm btn-primary fw-600 px-3 d-inline-flex align-items-center gap-1.5"
                                    @click="openNoteModal"
                                >
                                    <i class="bi" :class="hasNoteContent ? 'bi-pencil-square' : 'bi-plus-circle'"></i>
                                    &nbsp;<span>{{ hasNoteContent ? 'সম্পাদনা করুন' : 'নোট যুক্ত করুন' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Read-only View when note exists -->
                        <div v-if="hasNoteContent" class="note-view-container animate-fade-in">
                            <!-- Note Text Content -->
                            <div v-if="note.content" class="note-content-preview mb-4" v-html="renderedNoteContent"></div>
                            
                            <!-- Attached Images Grid -->
                            <div v-if="note.images && note.images.length > 0" class="mb-4">
                                <h6 class="fw-700 text-secondary mb-2" style="font-size: .85rem;">
                                    <i class="bi bi-images me-1 text-primary"></i>সংযুক্ত ছবিসমূহ
                                </h6>
                                <div class="note-image-grid">
                                    <div 
                                        v-for="(img, idx) in note.images" 
                                        :key="img.id" 
                                        class="note-image-card"
                                        @click="openLightbox(idx)"
                                    >
                                        <img :src="img.path" :alt="img.original_name" class="img-fluid" />
                                        <div class="note-image-overlay">
                                            <i class="bi bi-zoom-in"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reference Links -->
                            <div v-if="note.links && note.links.length > 0 && note.links.some(l => l.url)">
                                <h6 class="fw-700 text-secondary mb-2" style="font-size: .85rem;">
                                    <i class="bi bi-link-45deg me-1 text-primary"></i>রেফারেন্স লিংকসমূহ
                                </h6>
                                <div class="note-links-list">
                                    <a 
                                        v-for="link in note.links" 
                                        :key="link.id" 
                                        :href="link.url" 
                                        target="_blank" 
                                        class="note-link-badge text-decoration-none"
                                        v-show="link.url"
                                    >
                                        <i class="bi bi-box-arrow-up-right me-1.5 text-primary"></i>
                                        <span class="fw-600">{{ link.description || link.url }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Placeholder when note doesn't exist -->
                        <div v-else class="text-center py-4 px-3 bg-light rounded-3 border border-dashed animate-fade-in">
                            <i class="bi bi-journal-plus fs-3 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-3" style="font-size: .85rem;">এই লেসন সম্পর্কে আপনার কোনো নোট নেই।</p>
                            <button class="btn btn-sm btn-outline-primary fw-600" @click="openNoteModal">
                                <i class="bi bi-plus-circle me-1"></i>প্রথম নোট তৈরি করুন
                            </button>
                        </div>
                    </div><!-- /note display panel -->

                </div>
            </Transition>
        </main>

        <!-- ── DevTools blank screen ── -->
        <Teleport to="body">
            <div v-if="contentProtection && devToolsOpen" class="devtools-blank"></div>
        </Teleport>

        <!-- ── Image Lightbox ── -->
        <Teleport to="body">
            <Transition name="lb">
                <div v-if="lightboxOpen" class="lb-overlay" @click.self="closeLightbox">

                    <!-- Close -->
                    <button class="lb-close" @click="closeLightbox" aria-label="বন্ধ করুন">
                        <i class="bi bi-x-lg"></i>
                    </button>

                    <!-- Counter -->
                    <div class="lb-counter">{{ toBengaliNumber(lightboxIndex + 1) }} / {{ toBengaliNumber(lightboxImages.length) }}</div>

                    <!-- Prev -->
                    <button
                        v-if="lightboxImages.length > 1"
                        class="lb-arrow lb-prev"
                        @click="prevImage"
                        aria-label="আগের ছবি"
                    ><i class="bi bi-chevron-left"></i></button>

                    <!-- Image -->
                    <Transition name="lb-img" mode="out-in">
                        <img
                            :key="lightboxIndex"
                            :src="lightboxImages[lightboxIndex].src"
                            :alt="lightboxImages[lightboxIndex].alt"
                            class="lb-img"
                        />
                    </Transition>

                    <!-- Next -->
                    <button
                        v-if="lightboxImages.length > 1"
                        class="lb-arrow lb-next"
                        @click="nextImage"
                        aria-label="পরের ছবি"
                    ><i class="bi bi-chevron-right"></i></button>

                    <!-- Dot indicators -->
                    <div v-if="lightboxImages.length > 1" class="lb-dots">
                        <button
                            v-for="(_, i) in lightboxImages"
                            :key="i"
                            class="lb-dot"
                            :class="{ active: i === lightboxIndex }"
                            @click="lightboxIndex = i"
                        ></button>
                    </div>

                </div>
            </Transition>
        </Teleport>

        <!-- ── Note Editor Modal ── -->
        <Teleport to="body">
            <div class="modal fade" id="noteEditModal" tabindex="-1" aria-labelledby="noteEditModalLabel" aria-hidden="true" ref="noteModalRef">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content note-modal-content">
                        <!-- Modal Header -->
                        <div class="modal-header border-0 pb-0 pt-4 px-4 interview-modal-header">
                            <h5 class="modal-title fw-800" id="noteEditModalLabel">
                                <i class="bi bi-journal-text me-2 text-primary"></i>{{ hasNoteContent ? 'নোট সম্পাদনা' : 'নতুন নোট তৈরি করুন' }}
                            </h5>
                            <button type="button" class="btn-close-modal" data-bs-dismiss="modal" @click="closeNoteModal" aria-label="বন্ধ করুন">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body p-4">
                            <!-- Text Note / Rich-text editor -->
                            <div class="mb-4">
                                <label class="form-label fw-700 mb-2" style="font-size:.875rem;">
                                    <i class="bi bi-pencil-square me-1 text-primary"></i>টেক্সট নোট
                                </label>
                                <RichEditor
                                    v-model="noteForm.content"
                                    placeholder="এই লেসন সম্পর্কে আপনার চিন্তা, বোঝাপড়া বা গুরুত্বপূর্ণ পয়েন্ট লিখুন…"
                                    :invalid="!!noteForm.errors.content"
                                />
                                <FormError :message="noteForm.errors.content" />
                            </div>

                            <!-- Image Upload Section -->
                            <div class="mb-4">
                                <label class="form-label fw-700 mb-2" style="font-size:.875rem;">
                                    <i class="bi bi-image me-1 text-primary"></i>ছবি / স্ক্রিনশট
                                </label>
                                <div class="note-image-zone" @click="imageInputRef?.click()">
                                    <i class="bi bi-cloud-upload fs-4 text-primary mb-1 d-block"></i>
                                    <div class="fw-600" style="font-size:.875rem;">ক্লিক করে ছবি যুক্ত করুন</div>
                                    <div class="text-muted" style="font-size:.78rem;">PNG, JPG, GIF — একাধিক ছবি সম্ভব</div>
                                </div>
                                <input ref="imageInputRef" type="file" accept="image/*" multiple class="d-none" @change="addImages" />

                                <div v-if="savedImages.length || pendingFiles.length" class="note-image-preview">
                                    <div v-for="(img, i) in savedImages" :key="'s' + img.id" class="note-image-thumb" @click="openLightbox(i)">
                                        <img :src="img.path" :alt="img.original_name">
                                        <button type="button" @click.stop="removeSavedImage(img.id)" title="সরান">×</button>
                                    </div>
                                    <div v-for="(p, i) in pendingFiles" :key="'p' + i" class="note-image-thumb" @click="openLightbox(savedImages.length + i)">
                                        <img :src="p.url" alt="নতুন ছবি">
                                        <button type="button" @click.stop="removePendingFile(i)" title="সরান">×</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Reference Links Section -->
                            <div class="mb-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-700 mb-0" style="font-size:.875rem;">
                                        <i class="bi bi-link-45deg me-1 text-primary"></i>রেফারেন্স লিংক
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-600" @click="addLinkRow">
                                        <i class="bi bi-plus me-1"></i>লিংক যুক্ত করুন
                                    </button>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div v-for="(link, i) in noteLinks" :key="i" class="link-input-row">
                                        <input
                                            v-model="link.url"
                                            type="url"
                                            class="form-control form-control-sm"
                                            placeholder="https://..."
                                            style="font-size:.875rem;"
                                        >
                                        <input
                                            v-model="link.description"
                                            type="text"
                                            class="form-control form-control-sm"
                                            placeholder="লিংকের বিবরণ (ঐচ্ছিক)"
                                            style="font-size:.875rem;max-width:180px;"
                                        >
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            :disabled="noteLinks.length === 1"
                                            @click="removeLinkRow(i)"
                                            title="সরান"
                                        >
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer border-0 pt-0 px-4 pb-4">
                            <button type="button" class="btn btn-secondary fw-600" data-bs-dismiss="modal" @click="closeNoteModal">বাতিল</button>
                            <AppButton
                                :loading="noteForm.processing"
                                class="btn btn-primary fw-600 px-4"
                                @click="submitNote"
                            >
                                <i class="bi bi-floppy me-1"></i>সংরক্ষণ
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div class="modal fade" id="interviewQuestionsModal" tabindex="-1" aria-hidden="true" ref="interviewModalRef">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content interview-modal-content">
                        <div class="modal-header border-0 pb-0 pt-4 px-4 interview-modal-header">
                            <div class="interview-modal-title">
                                <h5 class="modal-title fw-800 mb-1">
                                    <i class="bi bi-question-circle me-2 text-primary"></i>&nbsp;সম্ভাব্য ইন্টারভিউ প্রশ্ন
                                </h5>
                                <div class="text-muted" style="font-size:.84rem;">{{ lesson.title }}</div>
                            </div>
                            <button type="button" class="btn-close-modal interview-close-btn" data-bs-dismiss="modal" @click="closeInterviewModal" aria-label="বন্ধ করুন">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="interview-question-list">
                                <div v-for="(item, index) in interviewQuestions" :key="item.id" class="interview-question-item">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="interview-question-number">{{ toBengaliNumber(index + 1) }}</div>
                                        <div class="flex-grow-1">
                                            <span class="interview-difficulty">{{ interviewDifficultyLabel(item.difficulty) }}</span>
                                            <h6 class="fw-800 my-2 interview-question-title">{{ cleanInterviewQuestionText(item.question) }}</h6>
                                            <div v-if="item.answer_hint" class="interview-answer-hint">
                                                <div class="fw-700 mb-1"><i class="bi bi-lightbulb me-1"></i>উত্তরের ধারণা</div>
                                                <div class="mb-0" v-html="cleanInterviewAnswerHtml(item.answer_hint)"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0 px-4 pb-4">
                            <button type="button" class="btn btn-primary fw-600 px-4" data-bs-dismiss="modal" @click="closeInterviewModal">বন্ধ করুন</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div class="modal fade" id="aiCompanionModal" tabindex="-1" aria-hidden="true" ref="aiCompanionModalRef">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content ai-modal-content">
                        <div class="modal-header border-0 pb-0 pt-4 px-4 interview-modal-header">
                            <div class="interview-modal-title">
                                <h5 class="modal-title fw-800 mb-1">
                                    <i class="bi bi-stars me-2 text-primary"></i>&nbsp;AI Lesson Companion
                                </h5>
                                <div class="text-muted" style="font-size:.84rem;">এই লেসনের কনটেক্সট ধরে সহজ বাংলায় সাহায্য করবে।</div>
                            </div>
                            <button type="button" class="btn-close-modal interview-close-btn" data-bs-dismiss="modal" @click="closeAiCompanionModal" aria-label="বন্ধ করুন">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <div v-if="aiQuota?.is_limited" class="ai-quota-status mb-3">
                                <div class="d-flex align-items-center gap-2"><i class="bi bi-stars"></i><span>আজকের AI সহায়তা</span></div>
                                <strong>{{ toBengaliNumber(aiQuota.remaining) }}টি বাকি</strong>
                                <small>{{ toBengaliNumber(aiQuota.used) }} / {{ toBengaliNumber(aiQuota.daily_limit) }}টি ব্যবহার হয়েছে · রাত ১২টায় রিসেট হবে</small>
                            </div>
                            <div class="ai-action-grid mb-3">
                                <button
                                    v-for="action in aiActions"
                                    :key="action.value"
                                    type="button"
                                    class="ai-action-btn"
                                    :class="{ active: aiAction === action.value }"
                                    :disabled="aiLoading"
                                    @click="selectAiAction(action.value)"
                                >
                                    <i class="bi" :class="action.icon"></i>
                                    <span>{{ action.label }}</span>
                                </button>
                            </div>

                            <div v-if="aiSelectedText" class="ai-selected-context mb-3">
                                <div class="ai-context-label">নির্বাচিত অংশ</div>
                                <p class="mb-0">{{ aiSelectedText }}</p>
                            </div>

                            <div class="mb-3">
                                <label for="aiCompanionQuestion" class="form-label fw-700 small">নির্দেশনা বা প্রশ্ন</label>
                                <textarea
                                    id="aiCompanionQuestion"
                                    v-model="aiQuestion"
                                    class="form-control ai-question-input"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="নির্বাচিত ট্যাবের কনটেক্সট নিয়ে কী করতে চান লিখুন..."
                                    :disabled="aiLoading"
                                ></textarea>
                            </div>

                            <div v-if="aiResponseHtml || aiLoading" class="ai-response-panel">
                                <div v-if="aiLoading" class="ai-loading">
                                    <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                                    <span>AI ভাবছে...</span>
                                </div>
                                <template v-else>
                                    <div class="ai-response-tools">
                                        <span>AI উত্তর</span>
                                        <button type="button" class="btn btn-sm ai-save-note-btn" :disabled="aiSavingToNote || aiResponseSaved" @click="saveAiResponseToNote">
                                            <span v-if="aiSavingToNote" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                            <i v-else class="bi" :class="aiResponseSaved ? 'bi-check2' : 'bi-journal-plus'"></i>
                                            {{ aiResponseSaved ? 'নোটে সংরক্ষিত' : 'নোটে রাখুন' }}
                                        </button>
                                    </div>
                                    <div class="ai-response-content" v-html="aiResponseHtml"></div>
                                </template>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0 px-4 pb-4">
                            <button type="button" class="btn btn-outline-secondary fw-600" data-bs-dismiss="modal" @click="closeAiCompanionModal">বন্ধ করুন</button>
                            <button type="button" class="btn btn-primary fw-700 px-4" :disabled="aiLoading || aiQuotaExhausted" @click="submitAiCompanion">
                                <span v-if="aiLoading" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                <i v-else class="bi bi-send me-1"></i>
                                পাঠান
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AppLayout         from '@/Layouts/AppLayout.vue';
import AppButton         from '@/Components/AppButton.vue';
import FormError         from '@/Components/FormError.vue';
import LessonNavSidebar  from '@/Components/LessonNavSidebar.vue';
import SegmentedContent  from '@/Components/SegmentedContent.vue';
import RichEditor        from '@/Components/RichEditor.vue';
import { useLessonNarration } from '@/composables/useLessonNarration';
import { useNotify }     from '@/composables/useNotify';
import { toBengaliNumber } from '@/utils/number.js';
import { formatBengaliDate } from '@/utils/date';
import { enhanceLessonContent, normalizeLessonHtml, splitLessonContent } from '@/utils/lessonContent';
import { useContentProtection } from '@/utils/contentProtection';
import { useTextHighlightAndSearch } from '@/utils/textHighlightAndSearch';

const props = defineProps({
    vault:           { type: Object,  required: true },
    folder:          { type: Object,  required: true },
    lesson:          { type: Object,  required: true },
    allLessons:      { type: Array,   default: () => [] },
    completedCount:  { type: Number,  default: 0 },
    totalCount:      { type: Number,  default: 0 },
    prevLesson:      { type: Object,  default: null },
    nextLesson:      { type: Object,  default: null },
    note:              { type: Object,  default: null },
    interviewQuestions:{ type: Array,   default: () => [] },
    hasSubscription:   { type: Boolean, default: false },
    allFolders:        { type: Array,   default: () => [] },
    contentProtection: { type: Boolean, default: false },
    aiCompanionQuota: { type: Object, default: null },
    cameFrom:          { type: Object,  default: null },
});

const { toast, confirm } = useNotify();
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const { highlights, canHighlightAndSearch, loadHighlights, applyAllHighlights, hideTooltip, setTooltipSuppressed } = useTextHighlightAndSearch('.lesson-content', {
    lessonId: computed(() => props.lesson.id),
});

const canDownloadPdf = computed(() => authUser.value?.role === 'admin' || !!page.props.auth?.subscription?.pdf_download_allowed);
const canBookmark = computed(() => authUser.value?.role === 'admin' || (!!page.props.auth?.subscription && page.props.auth?.subscription?.plan_slug !== 'trial'));
const canComplete = computed(() => authUser.value?.role === 'admin' || (!!page.props.auth?.subscription && page.props.auth?.subscription?.plan_slug !== 'trial'));
const canNote = computed(() => authUser.value?.role === 'admin' || page.props.auth?.subscription !== null);
const canUseAiCompanion = computed(() => authUser.value?.role === 'admin' || ['half-yearly', 'yearly'].includes(page.props.auth?.subscription?.plan_slug));
const canUseLessonNarration = computed(() => authUser.value?.role === 'admin' || !!page.props.auth?.lesson_audio?.can_use);
const readingSound = computed(() => page.props.auth?.reading_sound ?? null);
const readingSoundTrackUrls = computed(() => Array.isArray(readingSound.value?.track_urls) ? readingSound.value.track_urls.filter(Boolean) : []);
const canUseReadingSound = computed(() => !!readingSound.value?.can_use && readingSoundTrackUrls.value.length > 0);
const showReadingSoundControl = computed(() => props.lesson.can_access && canUseReadingSound.value);
const isReadingSoundPlaying = ref(false);
const readingSoundIndex = ref(0);
const readingSoundAudio = typeof Audio !== 'undefined' ? new Audio() : null;

const lessonNarrationStatusUrl = computed(() => route('lessons.narration.show', { vault: props.vault.slug, lesson: props.lesson.id }));
const lessonNarration = useLessonNarration({
    canUse: computed(() => canUseLessonNarration.value && !!props.lesson.can_access),
    statusUrl: lessonNarrationStatusUrl,
    initialState: computed(() => props.lesson.narration ?? null),
    lessonKey: computed(() => `${props.lesson.id}:${props.lesson.narration?.source_hash ?? ''}`),
});

const narrationLoading = ref(false);

function handleNarrationClick() {
    if (lessonNarration.isPlaying.value) {
        // Pause — no loader needed
        lessonNarration.toggleNarration();
        return;
    }
    narrationLoading.value = true;
    lessonNarration.toggleNarration();
}

function formatNarrationTime(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    return toBengaliNumber(`${mins}:${String(secs).padStart(2, '0')}`);
}

function onNarrationSeek(event) {
    lessonNarration.seekTo(Number(event.target.value));
}

function skipNarrationTime(seconds) {
    lessonNarration.seekTo((lessonNarration.currentTime.value || 0) + seconds);
}

// Auto-clear loading spinner when playback starts, pauses, or errors
watch(() => lessonNarration.isPlaying.value, (playing) => {
    if (playing) narrationLoading.value = false;
});
watch(() => lessonNarration.isPaused.value, (paused) => {
    if (paused) narrationLoading.value = false;
});
watch(() => lessonNarration.status.value, (status) => {
    if (['ready', 'failed', 'idle', 'stale'].includes(status) && !lessonNarration.isPlaying.value) {
        narrationLoading.value = false;
    }
});

const shouldShowLessonNarration = computed(() => {
    if (!props.lesson.can_access || !canUseLessonNarration.value) {
        return false;
    }

    return !!lessonNarration.isVisible.value;
});

const renderedSegments = computed(() => splitLessonContent(props.lesson.content ?? '').map((segment) => enhanceLessonContent(
    normalizeLessonHtml(segment),
    { copyButtonHtml: codeCopyButtonHtml },
)));
const renderedNoteContent = computed(() => enhanceLessonContent(normalizeLessonHtml(props.note?.content ?? '')));
const isLoading = ref(true);
const difficultyLabel = computed(() => ({ beginner: 'বেসিক', intermediate: 'ইন্টারমিডিয়েট', advanced: 'অ্যাডভান্সড' }[props.lesson.difficulty] ?? '—'));
const formatUpdatedDate = formatBengaliDate;

const downloadPDF = () => {
    if (!canDownloadPdf.value) {
        toast.error('PDF ডাউনলোড করার জন্য আপনার একটি Half-yearly সাবস্ক্রিপশন প্রয়োজন।');
        return;
    }

    window.open(route('lessons.pdf', { vault: props.vault.slug, lesson: props.lesson.id }), '_blank');
};

const prevLessonData = computed(() => props.prevLesson ? (props.allLessons.find((l) => l.id === props.prevLesson.id) ?? null) : null);
const nextLessonData = computed(() => props.nextLesson ? (props.allLessons.find((l) => l.id === props.nextLesson.id) ?? null) : null);

function loadReadingSoundTrack(index) {
    if (!readingSoundAudio) return;

    const urls = readingSoundTrackUrls.value;
    if (!urls.length) return;

    const safeIndex = ((index % urls.length) + urls.length) % urls.length;
    readingSoundIndex.value = safeIndex;
    readingSoundAudio.src = urls[safeIndex];
    readingSoundAudio.load();
}

async function playReadingSound(index = readingSoundIndex.value) {
    if (!readingSoundAudio || !canUseReadingSound.value) {
        return false;
    }

    loadReadingSoundTrack(index);

    try {
        await readingSoundAudio.play();
        isReadingSoundPlaying.value = true;
        return true;
    } catch {
        isReadingSoundPlaying.value = false;
        return false;
    }
}

function pauseReadingSound() {
    if (!readingSoundAudio) return;

    readingSoundAudio.pause();
    isReadingSoundPlaying.value = false;
}

function stopReadingSound() {
    if (!readingSoundAudio) return;

    readingSoundAudio.pause();
    readingSoundAudio.removeAttribute('src');
    readingSoundAudio.load();
    isReadingSoundPlaying.value = false;
    readingSoundIndex.value = 0;
}

async function toggleReadingSound() {
    if (!canUseReadingSound.value) {
        return;
    }

    if (isReadingSoundPlaying.value) {
        pauseReadingSound();
        return;
    }

    await playReadingSound();
}

function onReadingSoundEnded() {
    const urls = readingSoundTrackUrls.value;
    if (!urls.length) {
        stopReadingSound();
        return;
    }

    const nextIndex = (readingSoundIndex.value + 1) % urls.length;
    loadReadingSoundTrack(nextIndex);

    if (readingSoundAudio) {
        readingSoundAudio.play().catch(() => {
            isReadingSoundPlaying.value = false;
        });
    }
}

function onReadingSoundError() {
    const urls = readingSoundTrackUrls.value;
    if (!urls.length) {
        stopReadingSound();
        return;
    }

    const nextIndex = (readingSoundIndex.value + 1) % urls.length;
    if (nextIndex === readingSoundIndex.value) {
        stopReadingSound();
        return;
    }

    loadReadingSoundTrack(nextIndex);

    if (readingSoundAudio) {
        readingSoundAudio.play().catch(() => {
            isReadingSoundPlaying.value = false;
        });
    }
}

function isEditableTarget(target) {
    if (!(target instanceof Element)) return false;
    return !!target.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""]');
}

function isModalVisible() {
    return !!document.querySelector('.modal.show');
}

function visitPreviousLesson() {
    if (!prevLessonData.value) return;
    router.visit(route('lessons.show', { vault: props.vault.slug, lesson: prevLessonData.value.id }));
}

function visitNextLesson() {
    if (!nextLessonData.value) return;

    if (!nextLessonData.value.can_access) {
        router.visit('/checkout?plan=monthly');
        return;
    }

    router.visit(route('lessons.show', { vault: props.vault.slug, lesson: nextLessonData.value.id }));
}

const NARRATION_SEEK_STEP_SECONDS = 5;

function handleLessonNavigationKey(e) {
    if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    if (e.repeat || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey || e.isComposing) return;
    if (lightboxOpen.value || isModalVisible() || isEditableTarget(e.target)) return;

    // While narration audio is actively playing, arrow keys scrub the audio (like YouTube/VLC)
    // instead of switching lessons, so learners can skip back/forth without dragging the slider.
    if (lessonNarration.isPlaying.value) {
        e.preventDefault();
        const delta = e.key === 'ArrowLeft' ? -NARRATION_SEEK_STEP_SECONDS : NARRATION_SEEK_STEP_SECONDS;
        lessonNarration.seekTo(lessonNarration.currentTime.value + delta);
        return;
    }

    if (e.key === 'ArrowLeft' && prevLessonData.value) {
        e.preventDefault();
        visitPreviousLesson();
    }

    if (e.key === 'ArrowRight' && nextLessonData.value) {
        e.preventDefault();
        visitNextLesson();
    }
}

function handleNarrationShortcutKey(e) {
    const isCtrlP = e.key?.toLowerCase() === 'p' && (e.ctrlKey || e.metaKey) && !e.altKey;
    const isSpacebar = e.code === 'Space' && !e.ctrlKey && !e.metaKey && !e.altKey;
    if (!isCtrlP && !isSpacebar) return;
    if (e.repeat || e.shiftKey || e.isComposing) return;
    if (!shouldShowLessonNarration.value) return;
    if (isModalVisible() || isEditableTarget(e.target)) return;
    // Spacebar's default click on a focused button/link should win over the narration shortcut.
    if (isSpacebar && e.target instanceof Element && e.target.closest('button, a, [role="button"]')) return;

    // Hijack the browser's print shortcut / page-scroll spacebar so learners can toggle narration without leaving the keyboard.
    e.preventDefault();
    handleNarrationClick();
}

const localLessons = ref(props.allLessons.map((l) => ({ ...l })));
const localFolders = ref(props.allFolders.map((f) => ({ ...f, lessons: f.lessons.map((l) => ({ ...l })) })));

const isCompleted = ref(props.lesson.is_completed);
const completionCount = ref(props.lesson.completion_count ?? 0);
const completeForm = useForm({});

function submitToggleComplete() {
    if (!canComplete.value) {
        toast.error('লেসন সম্পূর্ণ চিহ্নিত করার জন্য অনুগ্রহ করে সাবস্ক্রিপশন প্ল্যান আপগ্রেড করুন।');
        return;
    }

    completeForm.post(route('lessons.complete', { vault: props.vault.slug, lesson: props.lesson.id }), {
        preserveScroll: true,
        onSuccess: () => {
            const nowComplete = !isCompleted.value;
            isCompleted.value = nowComplete;
            const flatLesson = localLessons.value.find((l) => l.id === props.lesson.id);
            if (flatLesson) flatLesson.is_completed = nowComplete;

            outer: for (const folder of localFolders.value) {
                for (const lesson of folder.lessons) {
                    if (lesson.id === props.lesson.id) {
                        lesson.is_completed = nowComplete;
                        break outer;
                    }
                }
            }

            if (nowComplete) {
                completionCount.value += 1;
                toast.success('লেসন পড়া সম্পূর্ণ চিহ্নিত হয়েছে!');
            } else {
                toast.info('লেসন পুনরায় পড়ার জন্য চিহ্নিত হয়েছে।');
            }
        },
        onError: () => toast.error('কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।'),
    });
}

const localBookmarked = ref(props.lesson.is_bookmarked);
const bookmarkForm = useForm({});

function toggleBookmark() {
    if (!canBookmark.value) {
        toast.error('বুকমার্ক করার জন্য অনুগ্রহ করে সাবস্ক্রিপশন প্ল্যান আপগ্রেড করুন।');
        return;
    }

    const next = !localBookmarked.value;
    localBookmarked.value = next;

    bookmarkForm.post(route('lessons.bookmark', { vault: props.vault.slug, lesson: props.lesson.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast[next ? 'success' : 'info'](next ? 'বুকমার্ক করা হয়েছে!' : 'বুকমার্ক সরানো হয়েছে।');
        },
        onError: () => {
            localBookmarked.value = !next;
            toast.error('বুকমার্ক আপডেট করা যায়নি।');
        },
    });
}

const imageInputRef = ref(null);
const contentRef = ref(null);
const noteModalRef = ref(null);
const interviewModalRef = ref(null);
const aiCompanionModalRef = ref(null);
let noteModalInstance = null;
let interviewModalInstance = null;
let aiCompanionModalInstance = null;

const aiActions = [
    { value: 'explain_lesson', label: 'সহজ ব্যাখ্যা', icon: 'bi-lightbulb' },
    { value: 'explain_selection', label: 'নির্বাচিত অংশ', icon: 'bi-cursor-text' },
    { value: 'explain_code', label: 'কোড ব্যাখ্যা', icon: 'bi-code-slash' },
    { value: 'practical_example', label: 'বাস্তব উদাহরণ', icon: 'bi-tools' },
    { value: 'revision_points', label: 'রিভিশন পয়েন্ট', icon: 'bi-list-check' },
    { value: 'summarize_note', label: 'নোট', icon: 'bi-journal-text' },
    { value: 'summarize_highlights', label: 'ইউজার হাইলাইট', icon: 'bi-highlighter' },
    { value: 'flashcards_from_note', label: 'নোট থেকে ফ্ল্যাশকার্ড', icon: 'bi-stack' },
    { value: 'flashcards_from_highlights', label: 'হাইলাইট থেকে ফ্ল্যাশকার্ড', icon: 'bi-layers' },
];
const aiAction = ref('explain_lesson');
const aiQuestion = ref('');
const aiSelectedText = ref('');
const aiResponseHtml = ref('');
const aiLoading = ref(false);
const aiSavingToNote = ref(false);
const aiResponseSaved = ref(false);
const aiNoteRefreshPending = ref(false);
const aiQuota = ref(props.aiCompanionQuota ? { ...props.aiCompanionQuota } : null);
const aiQuotaExhausted = computed(() => aiQuota.value?.is_limited && Number(aiQuota.value.remaining) <= 0);

const interviewQuestions = computed(() => props.interviewQuestions ?? []);
const hasInterviewQuestions = computed(() => props.lesson.can_access && interviewQuestions.value.length > 0);

function openInterviewModal() {
    if (!interviewModalInstance && interviewModalRef.value) {
        interviewModalInstance = new Modal(interviewModalRef.value, { backdrop: true, keyboard: true });
    }

    interviewModalInstance?.show();
}

function closeInterviewModal() {
    interviewModalInstance?.hide();
}

function selectedLessonText() {
    const selection = window.getSelection?.();
    const text = (selection?.toString() ?? '').replace(/\s+/g, ' ').trim();
    if (!text || !contentRef.value?.root || !selection?.rangeCount) {
        return '';
    }

    const range = selection.getRangeAt(0);
    const node = range.commonAncestorContainer.nodeType === Node.TEXT_NODE
        ? range.commonAncestorContainer.parentElement
        : range.commonAncestorContainer;

    return node instanceof Element && contentRef.value.root.contains(node)
        ? text.slice(0, 1500)
        : '';
}

function openAiCompanionModal(action = 'explain_lesson') {
    if (!canUseAiCompanion.value) {
        toast.error('AI Lesson Companion ব্যবহার করতে half-yearly subscription প্রয়োজন।');
        return;
    }

    aiAction.value = action;
    aiSelectedText.value = selectedLessonText();
    setTooltipSuppressed(true);
    aiResponseHtml.value = '';
    aiResponseSaved.value = false;

    if (!aiCompanionModalInstance && aiCompanionModalRef.value) {
        aiCompanionModalInstance = new Modal(aiCompanionModalRef.value, { backdrop: true, keyboard: true });
    }

    aiCompanionModalInstance?.show();
}

function closeAiCompanionModal() {
    aiCompanionModalInstance?.hide();
    setTooltipSuppressed(false);
}

function selectAiAction(action) {
    aiAction.value = action;
    hideTooltip();

    if (['explain_selection', 'explain_code'].includes(action)) {
        const currentSelection = selectedLessonText();
        if (currentSelection) {
            aiSelectedText.value = currentSelection;
        }

        if (!aiSelectedText.value) {
            toast.info('প্রথমে লেসনের কোনো অংশ বা কোড নির্বাচন করুন।');
        }
    }
}

function aiErrorMessage(payload) {
    const errors = payload?.errors ?? {};
    const firstError = Object.values(errors).flat()[0];

    return firstError || payload?.message || 'AI Companion এখন উত্তর দিতে পারছে না। একটু পরে চেষ্টা করুন।';
}

async function submitAiCompanion() {
    if (!canUseAiCompanion.value || aiLoading.value || aiQuotaExhausted.value) return;

    if (['explain_selection', 'explain_code'].includes(aiAction.value)) {
        aiSelectedText.value = selectedLessonText() || aiSelectedText.value;
        if (!aiSelectedText.value) {
            toast.warning('এই action-এর জন্য আগে লেসনের কোনো অংশ বা কোড নির্বাচন করুন।');
            return;
        }
    }

    aiLoading.value = true;

    try {
        const response = await fetch(route('lessons.ai-companion.store', { vault: props.vault.slug, lesson: props.lesson.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Inertia': 'false',
            },
            body: JSON.stringify({
                action: aiAction.value,
                selected_text: aiSelectedText.value,
                question: aiQuestion.value,
            }),
        });

        const payload = response.headers.get('content-type')?.includes('application/json')
            ? await response.json()
            : {};

        if (!response.ok) {
            throw new Error(aiErrorMessage(payload));
        }

        aiResponseHtml.value = payload.html ?? '';
        aiResponseSaved.value = false;
        if (payload.quota) aiQuota.value = payload.quota;
    } catch (error) {
        toast.error(error.message || 'AI Companion এখন উত্তর দিতে পারছে না।');
    } finally {
        aiLoading.value = false;
    }
}

async function saveAiResponseToNote() {
    if (!aiResponseHtml.value || aiSavingToNote.value || aiResponseSaved.value) return;

    aiSavingToNote.value = true;
    try {
        const response = await fetch(route('lessons.ai-companion.save-note', { vault: props.vault.slug, lesson: props.lesson.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ content: aiResponseHtml.value }),
        });
        const payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok) throw new Error(aiErrorMessage(payload));

        aiResponseSaved.value = true;
        toast.success(`AI উত্তর নোটে সংরক্ষণ হয়েছে · ${payload.saved_at ?? ''}`);
        aiNoteRefreshPending.value = true;
    } catch (error) {
        toast.error(error.message || 'AI উত্তর নোটে সংরক্ষণ করা যায়নি।');
    } finally {
        aiSavingToNote.value = false;
    }
}

function interviewDifficultyLabel(value) {
    return { easy: 'সহজ', medium: 'মাঝারি', hard: 'কঠিন' }[value] ?? 'মাঝারি';
}

function cleanInterviewQuestionText(text) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = text ?? '';
    const normalized = (wrapper.textContent || wrapper.innerText || '')
        .replace(/\s+/g, ' ')
        .trim();

    return normalized;
}

function cleanInterviewAnswerHtml(html) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html ?? '';

    wrapper.querySelectorAll('code').forEach((node) => {
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (!text) node.remove();
    });

    wrapper.querySelectorAll('p').forEach((node) => {
        const text = (node.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (!text) node.remove();
    });

    return wrapper.innerHTML;
}

const savedImages = ref([...(props.note?.images ?? [])]);
const pendingFiles = ref([]);
const noteLinks = ref(
    props.note?.links?.length
        ? props.note.links.map((l) => ({ url: l.url, description: l.description }))
        : [{ url: '', description: '' }],
);

const noteForm = useForm({ content: '', images: [], existing_image_ids: [], links: [] });

function hasMeaningfulNoteContent(html) {
    return (html ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim().length > 0;
}

watch(() => noteForm.content, (value) => {
    if (hasMeaningfulNoteContent(value) && noteForm.errors.content) {
        noteForm.clearErrors('content');
    }
});

const hasNoteContent = computed(() => {
    if (!props.note) return false;
    const hasText = !!props.note.content && props.note.content.trim() !== '' && props.note.content !== '<p></p>';
    const hasImages = !!props.note.images && props.note.images.length > 0;
    const hasLinks = !!props.note.links && props.note.links.some((l) => !!l.url);
    return hasText || hasImages || hasLinks;
});

function openNoteModal() {
    if (!noteModalInstance && noteModalRef.value) {
        noteModalInstance = new Modal(noteModalRef.value, { backdrop: 'static', keyboard: true });
    }

    noteForm.content = props.note?.content ?? '';
    noteForm.clearErrors();
    savedImages.value = [...(props.note?.images ?? [])];
    pendingFiles.value = [];
    noteLinks.value = props.note?.links?.length
        ? props.note.links.map((l) => ({ url: l.url, description: l.description }))
        : [{ url: '', description: '' }];

    noteModalInstance?.show();
}

function closeNoteModal() {
    noteModalInstance?.hide();
}

function addImages(event) {
    Array.from(event.target.files).forEach((file) => {
        pendingFiles.value.push({ file, url: URL.createObjectURL(file) });
    });
    event.target.value = '';
}

function removePendingFile(index) {
    URL.revokeObjectURL(pendingFiles.value[index].url);
    pendingFiles.value.splice(index, 1);
}

function removeSavedImage(id) {
    savedImages.value = savedImages.value.filter((img) => img.id !== id);
}

function addLinkRow() {
    noteLinks.value.push({ url: '', description: '' });
}

function removeLinkRow(index) {
    if (noteLinks.value.length > 1) noteLinks.value.splice(index, 1);
}

function submitNote() {
    if (!hasMeaningfulNoteContent(noteForm.content)) {
        noteForm.setError('content', 'নোটের টেক্সট লিখুন।');
        return;
    }

    noteForm.images = pendingFiles.value.map((p) => p.file);
    noteForm.existing_image_ids = savedImages.value.map((img) => img.id);
    noteForm.links = noteLinks.value;

    noteForm.post(route('lessons.notes', { vault: props.vault.slug, lesson: props.lesson.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('নোট সংরক্ষিত হয়েছে!');
            pendingFiles.value = [];
            savedImages.value = [...(props.note?.images ?? [])];
            noteLinks.value = props.note?.links?.length
                ? props.note.links.map((l) => ({ url: l.url, description: l.description }))
                : [{ url: '', description: '' }];
            closeNoteModal();
        },
        onError: () => toast.error('নোট সংরক্ষণ ব্যর্থ হয়েছে।'),
    });
}

async function clearNote() {
    const ok = await confirm('নোট মুছে ফেলবেন?', 'সব কন্টেন্ট, ছবি ও লিংক মুছে যাবে।', { icon: 'warning' });
    if (!ok) return;

    noteForm.content = '';
    noteForm.images = [];
    noteForm.existing_image_ids = [];
    noteForm.links = [];

    noteForm.post(route('lessons.notes', { vault: props.vault.slug, lesson: props.lesson.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('নোট মুছে ফেলা হয়েছে।');
            pendingFiles.value.forEach((p) => URL.revokeObjectURL(p.url));
            pendingFiles.value = [];
            savedImages.value = [];
            noteLinks.value = [{ url: '', description: '' }];
        },
        onError: () => toast.error('নোট মুছতে সমস্যা হয়েছে।'),
    });
}

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const lightboxImages = computed(() => [
    ...savedImages.value.map((img) => ({ src: img.path, alt: img.original_name ?? 'ছবি' })),
    ...pendingFiles.value.map((p) => ({ src: p.url, alt: 'নতুন ছবি' })),
]);

function openLightbox(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    lightboxOpen.value = false;
    document.body.style.overflow = '';
}

function prevImage() {
    lightboxIndex.value = (lightboxIndex.value - 1 + lightboxImages.value.length) % lightboxImages.value.length;
}

function nextImage() {
    lightboxIndex.value = (lightboxIndex.value + 1) % lightboxImages.value.length;
}

function handleLightboxKey(e) {
    if (!lightboxOpen.value) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') prevImage();
    if (e.key === 'ArrowRight') nextImage();
}

const codeCopyButtonHtml = `
    <button type="button" class="code-copy-btn" title="Copy code" aria-label="Copy code snippet">
        <i class="bi bi-clipboard"></i>
    </button>
`;

function getCodeBlockText(pre) {
    const code = pre.querySelector('code');
    if (code) return code.innerText ?? '';

    const clone = pre.cloneNode(true);
    clone.querySelector('.code-copy-btn')?.remove();
    return clone.innerText ?? '';
}

function fallbackCopyText(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0;';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    let copied = false;
    try {
        allowCodeCopy = true;
        copied = document.execCommand('copy');
    } finally {
        allowCodeCopy = false;
        textarea.remove();
    }

    if (!copied) throw new Error('Copy command failed');
}

function resetCodeCopyButton(btn) {
    btn.classList.remove('is-copied', 'is-error');
    btn.title = 'Copy code';
    btn.setAttribute('aria-label', 'Copy code snippet');
    btn.innerHTML = '<i class="bi bi-clipboard"></i>';
}

async function handleLessonContentClick(event) {
    const btn = event.target.closest('.code-copy-btn');
    if (!btn || !contentRef.value?.root?.contains(btn)) return;

    event.preventDefault();
    event.stopPropagation();

    const block = btn.closest('.copyable-code-block, pre, [data-type="codeBlock"]');
    if (!block) return;

    try {
        const codeText = getCodeBlockText(block).trimEnd();
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(codeText);
            } catch {
                fallbackCopyText(codeText);
            }
        } else {
            fallbackCopyText(codeText);
        }
        btn.classList.add('is-copied');
        btn.title = 'Copied';
        btn.setAttribute('aria-label', 'Code copied');
        btn.innerHTML = '<i class="bi bi-check2"></i>';
    } catch {
        btn.classList.add('is-error');
        btn.title = 'Copy failed';
        btn.setAttribute('aria-label', 'Copy failed');
        btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
    }

    setTimeout(() => resetCodeCopyButton(btn), 1600);
}

function onSegmentRevealed() {
    nextTick(() => applyAllHighlights());
}

const readProgress = ref(0);
let scrollTimer = null;
let maxDepth = 0;

function onScrollDepth() {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    readProgress.value = scrollable > 0 ? Math.min(100, Math.round((window.scrollY / scrollable) * 100)) : 0;

    if (!authUser.value || !props.lesson.can_access) return;
    const depth = Math.min(100, Math.round(((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight) * 100));
    if (depth <= maxDepth) return;

    maxDepth = depth;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
        fetch(route('lessons.scroll', { vault: props.vault.slug, lesson: props.lesson.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Inertia': 'false',
            },
            body: JSON.stringify({ depth: maxDepth }),
        });
    }, 2000);
}

let allowCodeCopy = false;
const { devToolsOpen, startContentProtection, stopContentProtection } = useContentProtection({
    enabled: props.contentProtection,
    isAuthenticated: !!page.props.auth?.user,
    allowCodeCopy: () => allowCodeCopy,
    contextMenuSelector: '.lesson-content',
    dragBlockSelector: '.lesson-content',
    allowedInteractionSelector: '#noteEditModal, #aiCompanionModal',
});

let hbInterval = null;
let hbLastVisible = null;
let hbAccruedSecs = 0;
const hbCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function hbAccrue() {
    if (hbLastVisible !== null) {
        hbAccruedSecs += (Date.now() - hbLastVisible) / 1000;
        hbLastVisible = Date.now();
    }
}

function hbSend(seconds) {
    const rounded = Math.round(seconds);
    if (!authUser.value || !props.lesson.can_access || rounded < 5) return;
    fetch(route('lessons.heartbeat', { vault: props.vault.slug, lesson: props.lesson.id }), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': hbCsrf(), 'X-Inertia': 'false' },
        body: JSON.stringify({ seconds: Math.min(rounded, 120) }),
        keepalive: true,
    });
}

function hbTick() {
    hbAccrue();
    hbSend(hbAccruedSecs);
    hbAccruedSecs = 0;
}

function hbFlush() {
    hbAccrue();
    hbSend(hbAccruedSecs);
    hbAccruedSecs = 0;
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        hbLastVisible = Date.now();
    } else {
        hbAccrue();
        hbLastVisible = null;
    }
}

function hbStart() {
    if (!authUser.value || !props.lesson.can_access) return;
    hbLastVisible = document.visibilityState === 'visible' ? Date.now() : null;
    hbInterval = setInterval(hbTick, 30000);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pagehide', hbFlush);
}

function hbStop() {
    hbFlush();
    clearInterval(hbInterval);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('pagehide', hbFlush);
}

watch(
    () => props.lesson.id,
    () => {
        savedImages.value = [...(props.note?.images ?? [])];
        pendingFiles.value = [];
        noteLinks.value = props.note?.links?.length
            ? props.note.links.map((l) => ({ url: l.url, description: l.description }))
            : [{ url: '', description: '' }];

        if (!isLoading.value) {
            nextTick(() => {
                loadHighlights();
            });
        }
    },
    { immediate: true },
);

watch(
    [contentRef, () => renderedSegments.value],
    () => {
        nextTick(() => {
            applyAllHighlights();
        });
    },
);

function onAiCompanionModalHidden() {
    setTooltipSuppressed(false);

    if (aiNoteRefreshPending.value) {
        aiNoteRefreshPending.value = false;
        router.reload({ only: ['note'], preserveScroll: true, preserveState: true });
    }
}

onUnmounted(() => {
    window.removeEventListener('keydown', handleLightboxKey);
    window.removeEventListener('keydown', handleLessonNavigationKey);
    window.removeEventListener('keydown', handleNarrationShortcutKey);
    window.removeEventListener('scroll', onScrollDepth);
    aiCompanionModalRef.value?.removeEventListener('hidden.bs.modal', onAiCompanionModalHidden);
    clearTimeout(scrollTimer);
    hbStop();
    lessonNarration.stopNarration();
    stopReadingSound();
    if (readingSoundAudio) {
        readingSoundAudio.removeEventListener('ended', onReadingSoundEnded);
        readingSoundAudio.removeEventListener('error', onReadingSoundError);
    }
    if (props.contentProtection) stopContentProtection();
    document.body.style.overflow = '';
    if (noteModalInstance) {
        noteModalInstance.dispose();
    }
    if (interviewModalInstance) {
        interviewModalInstance.dispose();
    }
    if (aiCompanionModalInstance) {
        aiCompanionModalInstance.dispose();
    }
});
onMounted(() => {
    window.addEventListener('keydown', handleLightboxKey);
    window.addEventListener('keydown', handleLessonNavigationKey);
    window.addEventListener('keydown', handleNarrationShortcutKey);
    window.addEventListener('scroll', onScrollDepth, { passive: true });
    aiCompanionModalRef.value?.addEventListener('hidden.bs.modal', onAiCompanionModalHidden);
    if (readingSoundAudio) {
        readingSoundAudio.preload = 'auto';
        readingSoundAudio.volume = 0.55;
        readingSoundAudio.loop = false;
        readingSoundAudio.addEventListener('ended', onReadingSoundEnded);
        readingSoundAudio.addEventListener('error', onReadingSoundError);
    }
    setTimeout(() => {
        isLoading.value = false;
        nextTick(() => {
            hbStart();
            if (props.contentProtection) startContentProtection();
            loadHighlights();
        });
    }, 600);
});
</script>

<style scoped>
/* ── Came-from banner ── */
.came-from-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    background: var(--primary-light);
    border: 1px solid rgba(45, 106, 79, 0.2);
    font-size: .85rem;
    color: var(--primary);
}
.came-from-banner a {
    color: var(--primary);
}

:root[data-theme="dark"] .came-from-banner {
    background: #17372b;
    border-color: #2b6049;
    color: #d7f3e2;
}
:root[data-theme="dark"] .came-from-banner a {
    color: #9ee8c0;
}

/* ── Narration seek slider ── */
.narration-seek { width: 100%; }
.narration-range { flex: 1 1 auto; }
.narration-time { min-width: 32px; text-align: center; flex-shrink: 0; }

.narration-range.form-range::-webkit-slider-thumb {
    background-color: var(--primary);
    box-shadow: 0 0 0 1px rgba(45, 106, 79, 0.15);
}
.narration-range.form-range::-webkit-slider-runnable-track {
    background-color: var(--primary-light);
}
.narration-range.form-range::-moz-range-thumb {
    background-color: var(--primary);
    box-shadow: 0 0 0 1px rgba(45, 106, 79, 0.15);
}
.narration-range.form-range::-moz-range-track {
    background-color: var(--primary-light);
}
.narration-range.form-range:focus::-webkit-slider-thumb {
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.25);
}
.narration-range.form-range:focus::-moz-range-thumb {
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.25);
}

/* ── Skeleton ── */
.sk-line {
    background: linear-gradient(90deg, var(--slate-100) 25%, var(--slate-50) 50%, var(--slate-100) 75%);
    background-size: 200% 100%;
    animation: sk-sweep 1.4s ease-in-out infinite;
    border-radius: 6px;
}
@keyframes sk-sweep {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Transition ── */
.sk-fade-enter-active, .sk-fade-leave-active { transition: opacity .3s; }
.sk-fade-enter-from,   .sk-fade-leave-to     { opacity: 0; }

/* ── Lesson rich content ── */
.lesson-content { line-height: 1.85; color: var(--text-primary); }
.lesson-content :deep(h3),
.lesson-content :deep(h4) { margin-top: 1.75rem; font-weight: 700; }
.lesson-content :deep(p)  { color: var(--text-secondary); margin-bottom: .9rem; }
.lesson-content :deep(ul),
.lesson-content :deep(ol) { padding-left: 1.5rem; color: var(--text-secondary); }
.lesson-content :deep(li) { margin-bottom: .4rem; }

/* ── Locked lesson preview teaser ── */
.locked-preview-wrap { position: relative; max-height: 340px; overflow: hidden; user-select: none; }
.locked-preview-fade {
    position: absolute;
    inset: auto 0 0 0;
    height: 140px;
    background: linear-gradient(180deg, transparent, var(--surface) 88%);
    pointer-events: none;
}
.lesson-content :deep(blockquote) {
    border-left: 4px solid var(--primary);
    padding-left: 1rem;
    color: var(--text-secondary);
    margin-left: 0;
}
.lesson-content :deep(pre) {
    background: #1e293b;
    border-radius: .5rem;
    padding: 1.25rem 3.35rem 1.25rem 1.25rem;
    overflow-x: auto;
    margin-bottom: 1.5rem;
    position: relative;
}
.lesson-content :deep(.copyable-code-block) {
    position: relative;
}
.lesson-content :deep(.code-copy-btn) {
    position: absolute;
    top: .65rem;
    right: .65rem;
    z-index: 2;
    width: 2rem;
    height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(226, 232, 240, .16);
    border-radius: .4rem;
    background: rgba(15, 23, 42, .78);
    color: #e2e8f0;
    line-height: 1;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
}
.lesson-content :deep(.code-copy-btn:hover),
.lesson-content :deep(.code-copy-btn:focus-visible) {
    background: rgba(45, 106, 79, .95);
    border-color: rgba(255, 255, 255, .28);
    color: #ffffff;
    outline: none;
}
.lesson-content :deep(.code-copy-btn:active) {
    transform: scale(.96);
}
.lesson-content :deep(.code-copy-btn.is-copied) {
    background: #2d6a4f;
    border-color: #2d6a4f;
    color: #ffffff;
}
.lesson-content :deep(.code-copy-btn.is-error) {
    background: #b42318;
    border-color: #b42318;
    color: #ffffff;
}
.lesson-content :deep(pre code) {
    font-family: 'Courier New', monospace;
    font-size: .83rem;
    line-height: 1.7;
    color: #e2e8f0;
    background: none;
    padding: 0;
}
.lesson-content :deep(table) {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 1.75rem 0;
    font-size: 0.95rem;
    color: var(--text-primary);
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    background: #ffffff;
}
.lesson-content :deep(th),
.lesson-content :deep(td) {
    padding: 0.9rem 1.1rem;
    vertical-align: top;
    border-bottom: 1px solid var(--slate-200);
    line-height: 1.65;
    transition: background-color 0.2s ease;
}
.lesson-content :deep(tbody tr:last-child td) {
    border-bottom: none;
}
.lesson-content :deep(th) {
    background: var(--primary-dark) !important;
    color: #ffffff !important;
    font-weight: 700;
    font-size: 0.95rem;
    border-bottom: 2px solid var(--primary-dark);
    text-transform: uppercase;
}
.lesson-content :deep(th p) {
    color: inherit !important;
    margin: 0;
}
.lesson-content :deep(tbody tr:nth-child(even)) {
    background-color: #fcfbf8;
}
.lesson-content :deep(tbody tr:hover) {
    background-color: #f2f7f4;
}
.lesson-content :deep(td code) {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.15rem 0.4rem;
    border-radius: var(--radius-sm);
    font-weight: 600;
    border: 1px solid rgba(45, 106, 79, 0.15);
    font-size: 0.85em;
}
.lesson-content :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    margin: 1.5rem auto;
    display: block;
}


.lesson-content :deep(p code) {
    background: var(--slate-100);
    color: var(--primary);
    padding: .1em .35em;
    border-radius: 4px;
    font-size: .875em;
}



/* ── Image upload zone ── */
.note-image-zone {
    border: 2px dashed var(--slate-300);
    border-radius: var(--radius);
    padding: 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    background: var(--surface);
}
.note-image-zone:hover { border-color: var(--primary); background: var(--primary-light); }

/* ── Image thumbnails ── */
.note-image-preview { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.note-image-thumb {
    position: relative;
    width: 72px; height: 72px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--slate-200);
}
.note-image-thumb img    { width: 100%; height: 100%; object-fit: cover; }
.note-image-thumb button {
    position: absolute; top: 2px; right: 2px;
    background: rgba(0,0,0,.55); color: white;
    border: none; border-radius: 50%;
    width: 18px; height: 18px; font-size: .6rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    line-height: 1;
}

/* ── Link rows ── */
.link-input-row { display: flex; gap: .5rem; align-items: center; }
.link-input-row input { flex: 1; }
.link-input-row input:last-of-type { max-width: 180px; }

/* ── Note View Container & Preview ── */
.note-view-container {
    background: #fafbfd;
    border: 1px solid #eef2f6;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,.015);
}
.note-content-preview {
    font-size: .95rem;
    line-height: 1.8;
    color: var(--text-primary);
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
    color: var(--text-secondary);
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
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
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
    color: var(--primary);
    font-size: .8rem;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 1px 2px rgba(0,0,0,.02);
}
.note-link-badge:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary-dark);
    transform: translateY(-1px);
}

/* ── Modal Close Button ── */
.btn-close-modal {
    background: var(--slate-100);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #6b7280;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.btn-close-modal:hover {
    background: var(--slate-200);
    color: var(--text-primary);
}

/* ── Note Modal Custom Styling ── */
.note-modal-content {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    background: #ffffff;
}

.interview-modal-content {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    background: #ffffff;
    color: #0f172a;
}
.interview-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.interview-modal-title {
    flex: 1 1 auto;
    min-width: 0;
}
.interview-close-btn {
    margin-left: auto;
    flex: 0 0 auto;
}
.interview-question-list {
    display: flex;
    flex-direction: column;
    gap: .85rem;
}
.interview-question-item {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    background: #f8fafc;
}
.interview-question-number {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--primary);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}
.interview-difficulty {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: .16rem .55rem;
    background: #e0f2fe;
    color: #0369a1;
    font-size: .72rem;
    font-weight: 700;
}
.interview-question-title {
    color: #0f172a;
    line-height: 1.65;
}
.interview-answer-hint {
    border-left: 3px solid var(--primary);
    border-radius: 8px;
    padding: .7rem .8rem;
    background: #eef7f1;
    color: #1f2937;
    font-size: .9rem;
    line-height: 1.7;
}
.interview-answer-hint :deep(p) {
    margin-bottom: .55rem;
}
.interview-answer-hint :deep(p:last-child) {
    margin-bottom: 0;
}
.interview-answer-hint :deep(ul),
.interview-answer-hint :deep(ol) {
    padding-left: 1.25rem;
    margin-bottom: .55rem;
}
.interview-answer-hint :deep(code) {
    background: rgba(45, 106, 79, .1);
    color: #134e4a;
    padding: .1rem .35rem;
    border-radius: 4px;
    font-size: .86em;
}
.interview-answer-hint :deep(code:empty) {
    display: none;
}
.interview-answer-hint :deep(pre) {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 8px;
    padding: .85rem 1rem;
    overflow-x: auto;
    margin: .75rem 0;
}
.interview-answer-hint :deep(pre code) {
    color: #f8fafc !important;
    background: transparent !important;
    display: block;
    font-family: 'Courier New', monospace;
    line-height: 1.7;
    white-space: pre-wrap;
    -webkit-text-fill-color: #f8fafc;
}
.interview-answer-hint :deep(pre code *) {
    color: #f8fafc !important;
    -webkit-text-fill-color: #f8fafc;
}

.ai-modal-content {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    background: #ffffff;
    color: #0f172a;
}
.ai-quota-status { display:grid;grid-template-columns:1fr auto;align-items:center;gap:.2rem .75rem;padding:.7rem .85rem;border:1px solid #c9ddd2;border-radius:10px;background:#f1f8f4;color:#24523d;font-size:.82rem; }
.ai-quota-status i { color:#2d6a4f; }
.ai-quota-status strong { font-size:.95rem; }
.ai-quota-status small { grid-column:1 / -1;color:#5d7468; }
.ai-action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: .6rem;
}
.ai-action-btn {
    min-height: 42px;
    border: 1px solid #d9e4dd;
    border-radius: 8px;
    background: #f8fbf9;
    color: #1f3d31;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: .45rem;
    padding: .55rem .7rem;
    font-size: .84rem;
    font-weight: 750;
    line-height: 1.25;
    transition: border-color .15s, background .15s, color .15s, box-shadow .15s;
}
.ai-action-btn:hover,
.ai-action-btn.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, .08);
}
.ai-action-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}
.ai-selected-context {
    border: 1px solid #d9e4dd;
    border-left: 3px solid var(--primary);
    border-radius: 8px;
    background: #f8fbf9;
    padding: .75rem .85rem;
    color: #1f2937;
    font-size: .88rem;
    line-height: 1.65;
}
.ai-context-label {
    color: var(--primary);
    font-size: .72rem;
    font-weight: 800;
    margin-bottom: .25rem;
    text-transform: uppercase;
}
.ai-question-input {
    border-color: #d9e4dd;
    border-radius: 8px;
    font-size: .92rem;
    line-height: 1.65;
}
.ai-question-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 .2rem rgba(45, 106, 79, .12);
}
.ai-response-panel {
    border: 1px solid #d9e4dd;
    border-radius: 10px;
    background: #fbfdfb;
    padding: 1rem;
}
.ai-response-tools { display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.8rem;padding-bottom:.65rem;border-bottom:1px solid #d9e4dd;font-size:.78rem;font-weight:800;color:#476156; }
.ai-save-note-btn { display:inline-flex;align-items:center;gap:.35rem;border:1px solid #9ec4ad;background:#fff;color:#24523d;font-weight:700; }
.ai-save-note-btn:hover:not(:disabled) { background:#eaf5ee;color:#1b4633; }
body:has(#aiCompanionModal.show) #text-highlight-tooltip { display:none !important; }
.ai-loading {
    display: flex;
    align-items: center;
    gap: .6rem;
    color: #476156;
    font-weight: 700;
}
.ai-response-content {
    color: #1f2937;
    line-height: 1.8;
    font-size: .94rem;
}
.ai-response-content :deep(h1),
.ai-response-content :deep(h2),
.ai-response-content :deep(h3),
.ai-response-content :deep(h4),
.ai-response-content :deep(h5),
.ai-response-content :deep(h6) {
    color: inherit;
    font-size: 1.08rem;
    font-weight: 800;
    line-height: 1.45;
    margin: 1rem 0 .45rem;
}
.ai-response-content :deep(p) { margin: 0 0 .7rem; }
.ai-response-content :deep(ul),
.ai-response-content :deep(ol) { margin: 0 0 .8rem; padding-left: 1.35rem; }
.ai-response-content :deep(li) { margin-bottom: .45rem; }
.ai-response-content :deep(p:last-child),
.ai-response-content :deep(ul:last-child),
.ai-response-content :deep(ol:last-child) {
    margin-bottom: 0;
}
.ai-response-content :deep(code) {
    background: rgba(45, 106, 79, .1);
    color: #134e4a;
    padding: .1rem .35rem;
    border-radius: 4px;
    font-size: .86em;
}
.ai-response-content :deep(pre) {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 8px;
    padding: .85rem 1rem;
    overflow-x: auto;
    margin: .75rem 0;
}
.ai-response-content :deep(pre code) {
    color: #f8fafc !important;
    background: transparent !important;
    display: block;
    white-space: pre-wrap;
}

:root[data-theme="dark"] #interviewQuestionsModal {
    background: rgba(4, 8, 12, 0.78);
}

:root[data-theme="dark"] #aiCompanionModal {
    background: rgba(4, 8, 12, 0.78);
}

:root[data-theme="dark"] #aiCompanionModal .ai-modal-content {
    background: linear-gradient(180deg, #111822 0%, #0d1219 100%);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.48);
}
:root[data-theme="dark"] #aiCompanionModal .ai-quota-status { background:#17372b;border-color:#2b6049;color:#d7f3e2; }
:root[data-theme="dark"] #aiCompanionModal .ai-quota-status small { color:#a9cdb5; }

:root[data-theme="dark"] #aiCompanionModal .interview-modal-header {
    background:
        radial-gradient(circle at top right, rgba(45, 106, 79, 0.14), transparent 32%),
        linear-gradient(135deg, #162131 0%, #0f1622 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

:root[data-theme="dark"] #aiCompanionModal .modal-title,
:root[data-theme="dark"] #aiCompanionModal .form-label {
    color: rgba(248, 250, 252, 0.96) !important;
}

:root[data-theme="dark"] #aiCompanionModal .text-muted {
    color: rgba(203, 213, 225, 0.78) !important;
}

:root[data-theme="dark"] #aiCompanionModal .text-primary {
    color: #67d39a !important;
}

:root[data-theme="dark"] #aiCompanionModal .interview-close-btn {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.92);
}

:root[data-theme="dark"] #aiCompanionModal .modal-body,
:root[data-theme="dark"] #aiCompanionModal .modal-footer {
    background: linear-gradient(180deg, #111822 0%, #0d1219 100%);
}

:root[data-theme="dark"] #aiCompanionModal .ai-action-btn,
:root[data-theme="dark"] #aiCompanionModal .ai-selected-context,
:root[data-theme="dark"] #aiCompanionModal .ai-question-input,
:root[data-theme="dark"] #aiCompanionModal .ai-response-panel {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(226, 232, 240, 0.94);
}

:root[data-theme="dark"] #aiCompanionModal .ai-action-btn:hover,
:root[data-theme="dark"] #aiCompanionModal .ai-action-btn.active {
    background: rgba(76, 199, 131, 0.12);
    border-color: rgba(103, 211, 154, 0.54);
    color: rgba(164, 243, 186, 0.98);
}

:root[data-theme="dark"] #aiCompanionModal .ai-context-label,
:root[data-theme="dark"] #aiCompanionModal .ai-loading {
    color: rgba(164, 243, 186, 0.98);
}

:root[data-theme="dark"] #aiCompanionModal .ai-response-content {
    color: rgba(226, 232, 240, 0.94);
}

:root[data-theme="dark"] #aiCompanionModal .ai-response-content :deep(code) {
    background: rgba(76, 199, 131, 0.14);
    color: rgba(164, 243, 186, 0.98);
}

:root[data-theme="dark"] #interviewQuestionsModal .modal-dialog {
    filter: none;
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-modal-content {
    background: linear-gradient(180deg, #111822 0%, #0d1219 100%);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.48);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-modal-header {
    background:
        radial-gradient(circle at top right, rgba(45, 106, 79, 0.14), transparent 32%),
        linear-gradient(135deg, #162131 0%, #0f1622 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-modal-header .modal-title {
    color: rgba(248, 250, 252, 0.96) !important;
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-modal-header .text-muted {
    color: rgba(203, 213, 225, 0.78) !important;
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-modal-header .text-primary {
    color: #67d39a !important;
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-close-btn {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.92);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-close-btn:hover {
    background: rgba(255, 255, 255, 0.14);
}

:root[data-theme="dark"] #interviewQuestionsModal .modal-body,
:root[data-theme="dark"] #interviewQuestionsModal .modal-footer {
    background: linear-gradient(180deg, #111822 0%, #0d1219 100%);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-question-item {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-question-number {
    background: rgba(76, 199, 131, 0.16);
    color: rgba(164, 243, 186, 0.98);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-difficulty {
    background: rgba(76, 199, 131, 0.11);
    color: rgba(164, 243, 186, 0.98);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-question-title {
    color: rgba(248, 250, 252, 0.96);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-answer-hint {
    background: linear-gradient(135deg, rgba(16, 24, 34, 0.98), rgba(14, 19, 27, 0.94));
    color: rgba(226, 232, 240, 0.92);
    border-left-color: #67d39a;
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-answer-hint :deep(code) {
    background: rgba(76, 199, 131, 0.12);
    color: rgba(225, 255, 236, 0.98);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-answer-hint :deep(pre) {
    background: #070b11;
    color: rgba(248, 250, 252, 0.98);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

:root[data-theme="dark"] #interviewQuestionsModal .interview-answer-hint :deep(pre code),
:root[data-theme="dark"] #interviewQuestionsModal .interview-answer-hint :deep(pre code *) {
    color: rgba(248, 250, 252, 0.98) !important;
    -webkit-text-fill-color: rgba(248, 250, 252, 0.98);
    text-shadow: none !important;
    opacity: 1 !important;
}

:root[data-theme="dark"] #interviewQuestionsModal .modal-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

:root[data-theme="dark"] #interviewQuestionsModal .btn-primary {
    box-shadow: 0 10px 24px rgba(76, 199, 131, 0.18);
}

:root[data-theme="dark"] .modal-backdrop.show {
    background-color: #05080d !important;
    opacity: 0.82;
}

.animate-fade-in {
    animation: fadeIn .3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Content protection ── */
.content-protected {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
}
.devtools-blank {
    position: fixed;
    inset: 0;
    background: #fff;
    z-index: 99999;
}

/* ── Reading progress bar ── */
.read-progress-track {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 3px;
    z-index: 9998;
    background: rgba(0,0,0,.06);
}
.read-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary, #2d6a4f), #4ade80);
    transition: width .15s ease-out;
    max-width: 100%;
}

.reading-sound-control {
    position: fixed;
    right: 5.75rem;
    bottom: 1.75rem;
    z-index: 9997;
    display: flex;
    flex-direction: column-reverse;
    align-items: flex-end;
    gap: .45rem;
}
.reading-sound-button {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 42px;
    border: 1px solid rgba(45, 106, 79, .22);
    border-radius: 999px;
    padding: .55rem .9rem .55rem .7rem;
    background: rgba(255, 255, 255, .94);
    color: var(--primary-dark);
    box-shadow: 0 10px 28px rgba(15, 23, 42, .16);
    font-size: .82rem;
    font-weight: 700;
    backdrop-filter: blur(12px);
    transition: transform .15s ease, background .15s ease, color .15s ease, border-color .15s ease;
}
.reading-sound-button i {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(45, 106, 79, .12);
}
.reading-sound-button:hover {
    transform: translateY(-1px);
    border-color: rgba(45, 106, 79, .45);
}
.reading-sound-button.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}
.reading-sound-button.active i {
    background: rgba(255, 255, 255, .18);
}
@media (max-width: 767.98px) {
    .reading-sound-control {
        right: 4.15rem;
        bottom: .85rem;
    }
    .reading-sound-button span {
        display: none;
    }
    .reading-sound-button {
        width: 44px;
        height: 44px;
        min-height: 44px;
        justify-content: center;
        padding: 0;
        border-radius: 50%;
    }
    .reading-sound-button i {
        width: 100%;
        height: 100%;
        background: transparent;
        font-size: 1.15rem;
    }
}

/* ── Mobile Floating Audio Narration Bar ── */
.mobile-floating-audio-bar {
    display: none;
}

@media (max-width: 767.98px) {
    .mobile-floating-audio-bar {
        display: block;
        position: fixed;
        left: 0.75rem;
        right: 0.75rem;
        bottom: 6.85rem;
        z-index: 9996;
        pointer-events: auto;
    }

    .floating-audio-inner {
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(45, 106, 79, 0.25);
        border-radius: 18px;
        padding: 0.55rem 0.75rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18), 0 2px 8px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }

    :root[data-theme="dark"] .floating-audio-inner {
        background: rgba(18, 28, 22, 0.94);
        border-color: rgba(120, 255, 180, 0.2);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    }

    .floating-audio-left {
        flex-shrink: 0;
    }

    .floating-audio-play-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: none;
        background: var(--primary, #2d6a4f);
        color: #ffffff;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(45, 106, 79, 0.35);
        transition: transform 0.15s ease, background-color 0.15s ease;
    }

    .floating-audio-play-btn:active {
        transform: scale(0.94);
    }

    .floating-audio-play-btn.is-playing {
        background: #16a34a;
        box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
    }

    .floating-audio-track {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .floating-audio-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.72rem;
        line-height: 1;
    }

    .floating-audio-title {
        font-weight: 700;
        color: var(--primary-dark, #1b4332);
        display: flex;
        align-items: center;
    }

    :root[data-theme="dark"] .floating-audio-title {
        color: #78ffb4;
    }

    .floating-audio-time {
        font-weight: 600;
        color: var(--text-secondary, #64748b);
        font-variant-numeric: tabular-nums;
        font-size: 0.72rem;
    }

    .floating-audio-slider-wrap {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .floating-audio-skip-btn {
        border: none;
        background: transparent;
        color: var(--text-secondary, #64748b);
        padding: 0 2px;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        cursor: pointer;
        flex-shrink: 0;
        line-height: 1;
    }

    .floating-audio-skip-btn:active {
        transform: scale(0.9);
    }

    .floating-audio-skip-btn .skip-sec {
        font-size: 0.52rem;
        font-weight: 800;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        line-height: 1;
        pointer-events: none;
    }

    .floating-narration-range {
        flex: 1 1 auto;
        height: 6px;
        margin: 0;
        cursor: pointer;
    }

    .floating-narration-range::-webkit-slider-runnable-track {
        height: 6px;
        border-radius: 3px;
        background: rgba(45, 106, 79, 0.18);
    }

    :root[data-theme="dark"] .floating-narration-range::-webkit-slider-runnable-track {
        background: rgba(255, 255, 255, 0.15);
    }

    .floating-narration-range::-webkit-slider-thumb {
        height: 15px;
        width: 15px;
        margin-top: -4.5px;
        background-color: var(--primary, #2d6a4f);
        border-radius: 50%;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    }

    .floating-narration-range::-moz-range-track {
        height: 6px;
        border-radius: 3px;
        background: rgba(45, 106, 79, 0.18);
    }

    .floating-narration-range::-moz-range-thumb {
        height: 15px;
        width: 15px;
        background-color: var(--primary, #2d6a4f);
        border-radius: 50%;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    }
}

/* Floating Audio Transitions */
.floating-audio-enter-active,
.floating-audio-leave-active {
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
}

.floating-audio-enter-from,
.floating-audio-leave-to {
    transform: translateY(20px);
    opacity: 0;
}

/* Keep the global bottom-right utilities from overlapping on lesson pages */
:global(.stt-btn) {
    right: 1.75rem !important;
    bottom: 7.75rem !important;
}

:global(.lcv-writer-container) {
    right: 1.75rem !important;
    bottom: 1.75rem !important;
}

@media (max-width: 767.98px) {
    :global(.stt-btn) {
        right: .85rem !important;
        bottom: 6.35rem !important;
    }

    :global(.lcv-writer-container) {
        right: .85rem !important;
        bottom: .85rem !important;
    }
}

/* ── Thumbnail — clickable ── */
.note-image-thumb { cursor: zoom-in; }
.note-image-thumb:hover img { opacity: .85; transition: opacity .15s; }

/* ── Lightbox overlay ── */
.lb-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.92);
    display: flex; align-items: center; justify-content: center;
}
.lb-img {
    max-width: 92vw; max-height: 84vh;
    border-radius: 8px;
    object-fit: contain;
    box-shadow: 0 8px 48px rgba(0,0,0,.6);
    user-select: none;
}
.lb-close {
    position: absolute; top: 1rem; right: 1rem;
    background: rgba(255,255,255,.12); border: none; border-radius: 50%;
    width: 40px; height: 40px; color: #fff; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .15s;
}
.lb-close:hover { background: rgba(255,255,255,.25); }
.lb-counter {
    position: absolute; top: 1.1rem; left: 50%; transform: translateX(-50%);
    color: rgba(255,255,255,.7); font-size: .82rem; font-weight: 600;
    background: rgba(0,0,0,.4); padding: 2px 10px; border-radius: 20px;
    pointer-events: none;
}
.lb-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,.12); border: none; border-radius: 50%;
    width: 46px; height: 46px; color: #fff; font-size: 1.2rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .15s;
}
.lb-arrow:hover { background: rgba(255,255,255,.28); }
.lb-prev { left: 1rem; }
.lb-next { right: 1rem; }
.lb-dots {
    position: absolute; bottom: 1.25rem; left: 50%; transform: translateX(-50%);
    display: flex; gap: .4rem;
}
.lb-dot {
    width: 8px; height: 8px; border-radius: 50%;
    border: none; background: rgba(255,255,255,.35); cursor: pointer;
    transition: background .15s, transform .15s;
    padding: 0;
}
.lb-dot.active { background: #fff; transform: scale(1.3); }

/* ── Lightbox transitions ── */
.lb-enter-active, .lb-leave-active  { transition: opacity .2s; }
.lb-enter-from,   .lb-leave-to      { opacity: 0; }
.lb-img-enter-active, .lb-img-leave-active { transition: opacity .15s, transform .15s; }
.lb-img-enter-from { opacity: 0; transform: scale(.96); }
.lb-img-leave-to   { opacity: 0; transform: scale(1.04); }
</style>

<style scoped>
.hover-primary-link:hover {
    color: var(--primary) !important;
}

@media print {
    /* Hide the entire website layout except for the main lesson content section */
    body * {
        visibility: hidden !important;
    }
    .content-section, .content-section * {
        visibility: visible !important;
    }
    
    /* Position content section perfectly for printing */
    body, .main-content, .content-section {
        background: transparent !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        position: absolute;
        left: 0;
        top: 0;
    }
    
    /* Hide UI controls and pagination from printed sheet */
    .breadcrumb,
    .came-from-banner,
    .btn,
    .lesson-narration-wrap,
    .code-copy-btn,
    .border-top.pt-4.mt-4, /* Complete & Pagination buttons */
    .lb-overlay,
    .devtools-blank,
    .d-flex.justify-content-between.align-items-center.mt-3.text-muted,
    .d-flex.align-items-center.justify-content-between.mt-3.text-muted {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Publication quality printed header banner */
    .content-section::before {
        content: "লারাকোডভল্ট — লার্নিং ভল্ট (laracodevault.test)";
        display: block;
        text-align: center;
        font-size: 1.1rem;
        font-weight: 700;
        color: #2d6a4f;
        border-bottom: 2px solid #2d6a4f;
        padding-bottom: 8px;
        margin-bottom: 24px;
    }
    
    /* Customize default print sheet size and boundaries */
    @page {
        size: A4;
        margin: 20mm;
    }
    
    /* Perfect typographic formatting for print */
    .lesson-content {
        font-size: 11pt !important;
        line-height: 1.65 !important;
        color: #000000 !important;
    }
    .lesson-content h2, .lesson-content h3, .lesson-content h4 {
        color: #1a2e22 !important;
        page-break-after: avoid;
        page-break-inside: avoid;
    }

    /* Ink-saving high-contrast print styles for code blocks */
    .lesson-content pre {
        background: #f8fafc !important;
        color: #1e293b !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        padding: 12px !important;
        white-space: pre-wrap !important;
        word-break: break-all !important;
        page-break-inside: avoid;
    }
    .lesson-content pre code {
        color: #1e293b !important;
        background: none !important;
    }
    .lesson-content td code, .lesson-content p code {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #e2e8f0 !important;
    }
    
    /* Avoid pagination awkward breaks */
    p, li, blockquote, figure, img, table {
        page-break-inside: avoid;
    }
    img {
        max-width: 100% !important;
        height: auto !important;
        box-shadow: none !important;
    }
}
</style>
