import { ref, provide, inject } from 'vue';

// Singleton global — dipakai LoadingSpinner & semua halaman (hindari inject mismatch)
const globalLoading = ref(false);
const globalMessage = ref('Memuat Data...');
const globalSubMessage = ref('');
const globalProgress = ref(null);

let progressTimer = null;
let progressStartTime = 0;
let progressEstimatedMs = 45000;

function clearProgressTimer() {
  if (progressTimer) {
    clearInterval(progressTimer);
    progressTimer = null;
  }
}

function createLoadingApi(isLoading, message, subMessage, progress) {
  const setLoading = (value, msg = 'Memuat Data...', subMsg = '', pct = null) => {
    isLoading.value = value;
    message.value = msg;
    subMessage.value = subMsg;
    progress.value = pct;
  };

  const setProgress = (pct, subMsg = null) => {
    progress.value = Math.max(0, Math.min(100, Math.round(pct)));
    if (subMsg !== null) {
      subMessage.value = subMsg;
    }
  };

  const showLoading = (msg = 'Memuat Data...', subMsg = '', pct = null) => {
    setLoading(true, msg, subMsg, pct);
  };

  const hideLoading = () => {
    clearProgressTimer();
    setLoading(false, 'Memuat Data...', '', null);
  };

  const startProgressSimulation = (msg = 'Memuat Data...', options = {}) => {
    const {
      subMsg = '',
      estimatedMs = 45000,
      steps = [],
    } = options;

    clearProgressTimer();
    progressEstimatedMs = estimatedMs;
    progressStartTime = Date.now();

    showLoading(msg, subMsg, 0);

    progressTimer = setInterval(() => {
      const elapsed = Date.now() - progressStartTime;
      const ratio = elapsed / progressEstimatedMs;
      let pct;

      if (ratio >= 1) {
        // Setelah estimasi habis, naik perlahan agar user tahu proses masih jalan.
        const overtimeSec = (elapsed - progressEstimatedMs) / 1000;
        pct = Math.min(98, 92 + Math.floor(overtimeSec / 8));
      } else {
        const eased = ratio < 0.5
          ? ratio * 1.4
          : 0.7 + (ratio - 0.5) * 0.44;
        pct = Math.min(92, Math.round(eased * 92));
      }

      setProgress(pct);

      const activeStep = [...steps].reverse().find((step) => pct >= step.at);
      if (activeStep) {
        subMessage.value = activeStep.message;
      }
    }, 180);
  };

  const finishProgress = async (msg = 'Selesai!', delayMs = 450) => {
    clearProgressTimer();
    setProgress(100, msg);
    await new Promise((resolve) => setTimeout(resolve, delayMs));
    hideLoading();
  };

  const failProgress = (msg = 'Gagal memuat data.') => {
    clearProgressTimer();
    setProgress(0, msg);
    setTimeout(() => hideLoading(), 1200);
  };

  const runWithProgress = async (task, options = {}) => {
    startProgressSimulation(options.message || 'Memuat Data...', options);
    try {
      const result = await task();
      await finishProgress(options.doneMessage || 'Selesai!');
      return result;
    } catch (error) {
      failProgress(options.errorMessage || 'Gagal memuat data.');
      throw error;
    }
  };

  return {
    isLoading,
    message,
    subMessage,
    progress,
    setLoading,
    setProgress,
    showLoading,
    hideLoading,
    startProgressSimulation,
    finishProgress,
    failProgress,
    runWithProgress,
  };
}

const globalApi = createLoadingApi(globalLoading, globalMessage, globalSubMessage, globalProgress);

export function useLoading() {
  return globalApi;
}

export function provideLoading() {
  provide('loading', globalLoading);
  provide('loadingMessage', globalMessage);
  provide('loadingSubMessage', globalSubMessage);
  provide('loadingProgress', globalProgress);

  return globalApi;
}
