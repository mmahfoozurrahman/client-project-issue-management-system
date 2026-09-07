import { computed, onMounted, onUnmounted, ref, unref, watch } from 'vue';

function readCsrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function emptyState() {
    return {
        can_use: true,
        provider: 'gemini',
        locale: 'bn-IN',
        voice_name: 'Kore',
        voice_label: 'Kore (বাংলা ভয়েস)',
        status: 'idle',
        source_hash: '',
        record_source_hash: null,
        needs_refresh: false,
        audio_urls: [],
        track_count: 0,
        requested_at: null,
        generated_at: null,
        error_message: null,
    };
}

function normalizeState(payload) {
    const state = { ...emptyState(), ...(payload ?? {}) };
    state.audio_urls = Array.isArray(state.audio_urls) ? state.audio_urls.filter(Boolean) : [];
    state.track_count = Number(state.track_count ?? state.audio_urls.length ?? 0);
    return state;
}

export function useLessonNarration({
    canUse,
    statusUrl,
    initialState = null,
    lessonKey = null,
    pollInterval = 3000,
} = {}) {
    const audio = typeof Audio !== 'undefined' ? new Audio() : null;
    const narration = ref(normalizeState(unref(initialState)));
    const isPlaying = ref(false);
    const isPaused = ref(false);
    const isLoadingAudio = ref(false);
    const isPreparing = ref(false);
    const isPolling = ref(false);
    const isLoadingStatus = ref(false);
    const pendingAutoPlay = ref(false);
    const currentTrackIndex = ref(0);
    const lastError = ref('');
    const pollTimer = ref(null);
    const currentTime = ref(0);
    const duration = ref(0);

    const canUseNarration = computed(() => !!unref(canUse));
    const supported = computed(() => !!audio);
    const status = computed(() => narration.value.status ?? 'idle');
    const audioUrls = computed(() => narration.value.audio_urls ?? []);
    const voiceLabel = computed(() => narration.value.voice_label || (narration.value.voice_name ? `${narration.value.voice_name} (Gemini)` : 'Gemini বাংলা ভয়েস'));
    const locale = computed(() => narration.value.locale || 'bn-IN');
    const trackCount = computed(() => audioUrls.value.length);
    const isReady = computed(() => status.value === 'ready' && audioUrls.value.length > 0 && !narration.value.needs_refresh);
    const isGenerating = computed(() => ['queued', 'processing'].includes(status.value));
    const isStale = computed(() => status.value === 'stale' || !!narration.value.needs_refresh);
    const isVisible = computed(() => canUseNarration.value && isReady.value);
    const progressPercent = computed(() => (duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0));

    const buttonLabel = computed(() => {
        if (!canUseNarration.value) {
            return 'অডিও লকড';
        }

        if (isPlaying.value) {
            return 'রিয়া পড়ছি';
        }

        if (isPaused.value) {
            return 'রিয়া থেমে আছি';
        }

        return 'রিয়া পড়ে শোনাও';
    });

    const helperText = computed(() => {
        if (!supported.value) {
            return 'এই ব্রাউজারে অডিও প্লেব্যাক সাপোর্ট করে না।';
        }

        if (!canUseNarration.value) {
            return '৬ মাসের সাবস্ক্রাইবারদের জন্য অডিও ন্যারেশন সুবিধা উপলব্ধ।';
        }

        if (lastError.value) {
            return lastError.value;
        }

        if (isPolling.value) {
            return 'Gemini অডিও ট্র্যাক তৈরি হচ্ছে...';
        }

        if (status.value === 'failed') {
            return narration.value.error_message || 'অডিও ন্যারেশন উপলব্ধ নয়।';
        }

        if (isStale.value) {
            return 'লেসনের কন্টেন্ট পরিবর্তনের কারণে অডিও রিফ্রেশ প্রয়োজন।';
        }

        if (isPlaying.value) {
            return `${voiceLabel.value}-এ অডিও বাজছে।`;
        }

        if (isPaused.value) {
            return `অডিও পজ করা আছে (${voiceLabel.value})।`;
        }

        if (isReady.value) {
            return `${trackCount.value}টি অডিও ট্র্যাক প্রস্তুত (${voiceLabel.value})।`;
        }

        return 'অ্যাডমিন অডিও তৈরি করলে এখানে শুনুন বাটন দৃশ্যমান হবে।';
    });

    function applyState(payload) {
        narration.value = normalizeState(payload);
    }

    function stopPolling() {
        if (pollTimer.value) {
            clearInterval(pollTimer.value);
            pollTimer.value = null;
        }

        isPolling.value = false;
    }

    function resetPlayback({ keepStatus = true } = {}) {
        if (audio) {
            audio.pause();
            audio.removeAttribute('src');
            audio.load();
        }

        isPlaying.value = false;
        isPaused.value = false;
        isLoadingAudio.value = false;
        pendingAutoPlay.value = false;
        currentTrackIndex.value = 0;
        currentTime.value = 0;
        duration.value = 0;
        lastError.value = '';

        if (!keepStatus) {
            narration.value = normalizeState();
        }
    }

    function loadTrack(index) {
        if (!audio) {
            return;
        }

        const nextUrl = audioUrls.value[index];
        if (!nextUrl) {
            return;
        }

        audio.src = nextUrl;
        audio.load();
        currentTime.value = 0;
        duration.value = 0;
    }

    async function playTrack(index = currentTrackIndex.value, { showLoader = false, resume = false } = {}) {
        if (!audio || !audioUrls.value.length) {
            return false;
        }

        if (showLoader) {
            isLoadingAudio.value = true;
        }

        // Only (re)load the track source when switching tracks or starting fresh.
        // Reloading on resume would reset playback position to the beginning.
        const isSameTrackLoaded = resume && currentTrackIndex.value === index && audio.src;
        currentTrackIndex.value = index;
        if (!isSameTrackLoaded) {
            loadTrack(index);
        }

        try {
            await audio.play();
            isPlaying.value = true;
            isPaused.value = false;
            isLoadingAudio.value = false;
            pendingAutoPlay.value = false;
            lastError.value = '';
            return true;
        } catch (error) {
            isLoadingAudio.value = false;
            isPlaying.value = false;
            isPaused.value = false;
            lastError.value = error?.name === 'NotAllowedError'
                ? 'Tap once more to start narration playback.'
                : 'Narration playback could not start.';
            return false;
        }
    }

    async function playCurrentNarration({ showLoader = false, resume = false } = {}) {
        if (!isReady.value) {
            return false;
        }

        if (!audioUrls.value.length) {
            return false;
        }

        return playTrack(currentTrackIndex.value, { showLoader, resume });
    }

    function seekTo(time) {
        if (!audio || !Number.isFinite(time)) {
            return;
        }

        const clamped = Math.min(Math.max(time, 0), audio.duration || duration.value || time);
        audio.currentTime = clamped;
        currentTime.value = clamped;
    }

    function pauseNarration() {
        if (!audio) {
            return;
        }

        audio.pause();
        isPlaying.value = false;
        isPaused.value = true;
        isLoadingAudio.value = false;
    }

    function stopNarration() {
        resetPlayback({ keepStatus: true });
        stopPolling();
    }

    async function refreshNarrationStatus() {
        if (!canUseNarration.value || !statusUrl) {
            return narration.value;
        }

        isLoadingStatus.value = true;

        try {
            const response = await fetch(unref(statusUrl), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to load narration status.');
            }

            const data = await response.json();
            applyState(data);
            lastError.value = '';
            return narration.value;
        } catch (error) {
            if (!isReady.value) {
                lastError.value = error?.message || 'Unable to load narration status.';
            }
            return narration.value;
        } finally {
            isLoadingStatus.value = false;
        }
    }

    function startPolling() {
        if (pollTimer.value) {
            return;
        }

        isPolling.value = true;
        pollTimer.value = setInterval(async () => {
            await refreshNarrationStatus();

            if (narration.value.status === 'ready' && narration.value.audio_urls.length) {
                stopPolling();

                if (pendingAutoPlay.value) {
                    await playCurrentNarration();
                }
                return;
            }

            if (narration.value.status === 'failed') {
                stopPolling();
                pendingAutoPlay.value = false;
            }
        }, pollInterval);
    }

    async function toggleNarration() {
        if (!canUseNarration.value) {
            return;
        }

        if (isPlaying.value) {
            pauseNarration();
            return;
        }

        // Show loader on all user-initiated play actions
        isLoadingAudio.value = true;

        if (isPaused.value) {
            if (await playCurrentNarration({ showLoader: true, resume: true })) {
                return;
            }
            isLoadingAudio.value = false;
            lastError.value = 'Tap once to resume narration.';
            return;
        }

        if (isReady.value) {
            if (await playCurrentNarration({ showLoader: true })) {
                return;
            }
            isLoadingAudio.value = false;
            return;
        }

        if (isGenerating.value) {
            pendingAutoPlay.value = true;
            startPolling();
            isLoadingAudio.value = false;
            return;
        }

        await refreshNarrationStatus();

        if (isReady.value) {
            await playCurrentNarration({ showLoader: true });
            return;
        }

        isLoadingAudio.value = false;

        if (isGenerating.value) {
            pendingAutoPlay.value = true;
            startPolling();
            return;
        }

        lastError.value = 'Narration is not available for this lesson yet.';
    }

    function onAudioPlaying() {
        // Always clear loading when audio actually starts
        isLoadingAudio.value = false;
        isPlaying.value = true;
        isPaused.value = false;
    }

    function onAudioTimeUpdate() {
        if (!audio) {
            return;
        }

        currentTime.value = audio.currentTime || 0;
    }

    function onAudioLoadedMetadata() {
        if (!audio) {
            return;
        }

        duration.value = Number.isFinite(audio.duration) ? audio.duration : 0;
    }

    function onAudioEnded() {
        isLoadingAudio.value = false;
        if (currentTrackIndex.value + 1 < audioUrls.value.length) {
            currentTrackIndex.value += 1;
            loadTrack(currentTrackIndex.value);

            if (audio) {
                // No showLoader for auto-advancing tracks
                audio.play().catch(() => {
                    isLoadingAudio.value = false;
                    isPlaying.value = false;
                    isPaused.value = false;
                    pendingAutoPlay.value = false;
                    lastError.value = 'Tap once to continue narration.';
                });
            }

            return;
        }

        isPlaying.value = false;
        isPaused.value = false;
        pendingAutoPlay.value = false;
        currentTrackIndex.value = 0;
    }

    function onAudioError() {
        isLoadingAudio.value = false;
        if (currentTrackIndex.value + 1 < audioUrls.value.length) {
            currentTrackIndex.value += 1;
            loadTrack(currentTrackIndex.value);

            if (audio) {
                audio.play().catch(() => {
                    isLoadingAudio.value = false;
                    lastError.value = 'Tap once to continue narration.';
                });
            }

            return;
        }

        isPlaying.value = false;
        isPaused.value = false;
        pendingAutoPlay.value = false;
        lastError.value = 'Narration playback stopped unexpectedly.';
    }

    watch(
        [() => unref(lessonKey), () => unref(initialState)],
        () => {
            stopPolling();
            resetPlayback();
            isLoadingAudio.value = false;
            applyState(initialState ? unref(initialState) : null);

            const shouldRefresh = narration.value.needs_refresh
                || !narration.value.audio_urls.length
                || ['idle', 'queued', 'processing', 'failed', 'stale'].includes(narration.value.status);

            if (canUseNarration.value && shouldRefresh) {
                refreshNarrationStatus().then(() => {
                    if (['queued', 'processing'].includes(narration.value.status)) {
                        startPolling();
                    }
                });
            }
        },
        { immediate: true, deep: true },
    );

    onMounted(() => {
        isLoadingAudio.value = false;
        if (!audio) {
            return;
        }

        audio.preload = 'auto';
        audio.volume = 0.85;
        audio.addEventListener('playing', onAudioPlaying);
        audio.addEventListener('ended', onAudioEnded);
        audio.addEventListener('error', onAudioError);
        audio.addEventListener('timeupdate', onAudioTimeUpdate);
        audio.addEventListener('loadedmetadata', onAudioLoadedMetadata);
    });

    onUnmounted(() => {
        stopPolling();

        if (audio) {
            audio.pause();
            audio.removeEventListener('playing', onAudioPlaying);
            audio.removeEventListener('ended', onAudioEnded);
            audio.removeEventListener('error', onAudioError);
            audio.removeEventListener('timeupdate', onAudioTimeUpdate);
            audio.removeEventListener('loadedmetadata', onAudioLoadedMetadata);
            audio.src = '';
            audio.load();
        }
    });

    return {
        supported,
        canUseNarration,
        status,
        audioUrls,
        voiceLabel,
        locale,
        trackCount,
        isReady,
        isVisible,
        isGenerating,
        isStale,
        isPlaying,
        isPaused,
        isLoadingAudio,
        isPreparing,
        isPolling,
        isLoadingStatus,
        currentTime,
        duration,
        progressPercent,
        currentTrackIndex,
        buttonLabel,
        helperText,
        toggleNarration,
        pauseNarration,
        stopNarration,
        seekTo,
        refreshNarrationStatus,
    };
}
