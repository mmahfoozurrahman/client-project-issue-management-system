import { computed, onBeforeUnmount, ref } from 'vue';

function readCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function emptyState() {
    return {
        provider: 'gemini',
        locale: 'bn-IN',
        voice_name: 'Kore',
        voice_label: 'Kore',
        status: 'idle',
        is_available: false,
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

export function useIssueNarration({ pollInterval = 3000 } = {}) {
    const audio = typeof Audio !== 'undefined' ? new Audio() : null;
    const narration = ref(normalizeState());
    const isPlaying = ref(false);
    const isPaused = ref(false);
    const isLoadingAudio = ref(false);
    const isLoadingStatus = ref(false);
    const isGeneratingRequest = ref(false);
    const isPolling = ref(false);
    const currentTrackIndex = ref(0);
    const lastError = ref('');
    const pollTimer = ref(null);
    const currentTime = ref(0);
    const duration = ref(0);

    const status = computed(() => narration.value.status);
    const audioUrls = computed(() => narration.value.audio_urls);
    const isReady = computed(() => status.value === 'ready' && audioUrls.value.length > 0 && narration.value.is_available);
    const isGenerating = computed(() => ['queued', 'processing'].includes(status.value) || isGeneratingRequest.value);
    const progressPercent = computed(() => (duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0));

    function stopPolling() {
        if (pollTimer.value) {
            clearInterval(pollTimer.value);
            pollTimer.value = null;
        }
        isPolling.value = false;
    }

    function resetPlayback() {
        if (audio) {
            audio.pause();
            audio.removeAttribute('src');
            audio.load();
        }
        isPlaying.value = false;
        isPaused.value = false;
        isLoadingAudio.value = false;
        currentTrackIndex.value = 0;
        currentTime.value = 0;
        duration.value = 0;
        lastError.value = '';
    }

    async function fetchStatus(issueId) {
        isLoadingStatus.value = true;

        try {
            const response = await fetch(`/issues/${issueId}/narration`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to load narration status.');
            }

            narration.value = normalizeState(await response.json());
            return narration.value;
        } catch (error) {
            lastError.value = error?.message || 'Unable to load narration status.';
            return narration.value;
        } finally {
            isLoadingStatus.value = false;
        }
    }

    function startPolling(issueId) {
        if (pollTimer.value) {
            return;
        }

        isPolling.value = true;
        pollTimer.value = setInterval(async () => {
            await fetchStatus(issueId);

            if (['ready', 'failed'].includes(narration.value.status)) {
                stopPolling();
            }
        }, pollInterval);
    }

    async function generateNarration(issueId, { force = false } = {}) {
        isGeneratingRequest.value = true;
        lastError.value = '';

        try {
            const response = await fetch(`/issues/${issueId}/narration`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ force }),
            });

            const data = await response.json();
            narration.value = normalizeState(data);

            if (!response.ok) {
                lastError.value = data?.error_message || 'Narration generation failed.';
            } else if (['queued', 'processing'].includes(narration.value.status)) {
                startPolling(issueId);
            }

            return response.ok;
        } catch (error) {
            lastError.value = error?.message || 'Narration generation failed.';
            return false;
        } finally {
            isGeneratingRequest.value = false;
        }
    }

    function loadTrack(index) {
        if (!audio) {
            return;
        }

        const url = audioUrls.value[index];
        if (!url) {
            return;
        }

        audio.src = url;
        audio.load();
        currentTime.value = 0;
        duration.value = 0;
    }

    async function playTrack(index = currentTrackIndex.value, { resume = false } = {}) {
        if (!audio || !audioUrls.value.length) {
            return false;
        }

        const isSameTrack = resume && currentTrackIndex.value === index && audio.src;
        currentTrackIndex.value = index;

        if (!isSameTrack) {
            loadTrack(index);
        }

        try {
            await audio.play();
            isPlaying.value = true;
            isPaused.value = false;
            lastError.value = '';
            return true;
        } catch (error) {
            isPlaying.value = false;
            lastError.value = error?.name === 'NotAllowedError' ? 'Tap once more to start playback.' : 'Playback could not start.';
            return false;
        }
    }

    function seekTo(time) {
        if (!audio || !Number.isFinite(time)) {
            return;
        }

        if (!audio.src && audioUrls.value[currentTrackIndex.value]) {
            audio.src = audioUrls.value[currentTrackIndex.value];
            audio.load();
        }

        const validDuration = Number.isFinite(audio.duration) && audio.duration > 0
            ? audio.duration
            : (Number.isFinite(duration.value) && duration.value > 0 ? duration.value : null);

        const clamped = validDuration !== null
            ? Math.min(Math.max(time, 0), validDuration)
            : Math.max(time, 0);

        try {
            audio.currentTime = clamped;
        } catch (e) {
            console.warn('Seek error:', e);
        }
        currentTime.value = clamped;
    }

    function skipTime(seconds) {
        if (!audio) return;
        const current = Number.isFinite(currentTime.value) && currentTime.value > 0
            ? currentTime.value
            : (Number.isFinite(audio.currentTime) ? audio.currentTime : 0);
        seekTo(current + seconds);
    }

    function pauseNarration() {
        if (!audio) {
            return;
        }

        audio.pause();
        isPlaying.value = false;
        isPaused.value = true;
    }

    async function toggleNarration(issueId) {
        if (isPlaying.value) {
            pauseNarration();
            return;
        }

        isLoadingAudio.value = true;

        if (isPaused.value && audio && audio.src) {
            try {
                await audio.play();
                isPlaying.value = true;
                isPaused.value = false;
            } catch (error) {
                lastError.value = 'Tap once more to resume playback.';
            }
            isLoadingAudio.value = false;
            return;
        }

        if (!isReady.value) {
            await fetchStatus(issueId);
        }

        if (isReady.value) {
            await playTrack(currentTrackIndex.value);
        } else {
            lastError.value = 'Narration is not available for this issue yet.';
        }

        isLoadingAudio.value = false;
    }

    function onAudioEnded() {
        if (currentTrackIndex.value + 1 < audioUrls.value.length) {
            currentTrackIndex.value += 1;
            loadTrack(currentTrackIndex.value);
            audio?.play().catch(() => {
                isPlaying.value = false;
            });
            return;
        }

        isPlaying.value = false;
        isPaused.value = false;
        currentTrackIndex.value = 0;
        currentTime.value = 0;
    }

    function onAudioTimeUpdate() {
        if (!audio) return;
        currentTime.value = audio.currentTime || 0;
    }

    function onAudioLoadedMetadata() {
        if (!audio) return;
        duration.value = Number.isFinite(audio.duration) ? audio.duration : 0;
    }

    if (audio) {
        audio.addEventListener('timeupdate', onAudioTimeUpdate);
        audio.addEventListener('loadedmetadata', onAudioLoadedMetadata);
        audio.addEventListener('durationchange', onAudioLoadedMetadata);
        audio.addEventListener('seeked', onAudioTimeUpdate);
        audio.addEventListener('playing', () => {
            isLoadingAudio.value = false;
            isPlaying.value = true;
            isPaused.value = false;
        });
        audio.addEventListener('ended', onAudioEnded);
        audio.addEventListener('pause', () => {
            if (!audio.ended) {
                isPlaying.value = false;
                isPaused.value = true;
            }
        });
    }

    onBeforeUnmount(() => {
        stopPolling();
        resetPlayback();
    });

    return {
        narration,
        status,
        isReady,
        isGenerating,
        isPlaying,
        isPaused,
        isLoadingAudio,
        isLoadingStatus,
        isGeneratingRequest,
        currentTrackIndex,
        currentTime,
        duration,
        progressPercent,
        lastError,
        fetchStatus,
        generateNarration,
        toggleNarration,
        pauseNarration,
        resetPlayback,
        seekTo,
        skipTime,
        stopPolling,
    };
}
