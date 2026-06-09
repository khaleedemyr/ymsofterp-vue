import { ref, provide, inject } from 'vue';

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
    if (isLoading && typeof isLoading.value !== 'undefined') {
      isLoading.value = value;
    }
    if (message && typeof message.value !== 'undefined') {
      message.value = msg;
    }
    if (subMessage && typeof subMessage.value !== 'undefined') {
      subMessage.value = subMsg;
    }
    if (progress && typeof progress.value !== 'undefined') {
      progress.value = pct;
    }
  };

  const setProgress = (pct, subMsg = null) => {
    if (progress && typeof progress.value !== 'undefined') {
      progress.value = Math.max(0, Math.min(100, Math.round(pct)));
    }
    if (subMsg !== null && subMessage && typeof subMessage.value !== 'undefined') {
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

  /**
   * Progress simulasi untuk operasi async tanpa streaming server.
   * Naik perlahan sampai ~92%, lalu finishProgress() ke 100%.
   */
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
      const ratio = Math.min(1, elapsed / progressEstimatedMs);
      const eased = ratio < 0.5
        ? ratio * 1.4
        : 0.7 + (ratio - 0.5) * 0.44;
      const pct = Math.min(92, Math.round(eased * 92));

      setProgress(pct);

      const activeStep = [...steps].reverse().find((step) => pct >= step.at);
      if (activeStep && subMessage && typeof subMessage.value !== 'undefined') {
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

export function useLoading() {
  const injectedLoading = inject('loading', null);
  const injectedMessage = inject('loadingMessage', null);
  const injectedSubMessage = inject('loadingSubMessage', null);
  const injectedProgress = inject('loadingProgress', null);

  const isLoading = injectedLoading !== null ? injectedLoading : globalLoading;
  const message = injectedMessage !== null ? injectedMessage : globalMessage;
  const subMessage = injectedSubMessage !== null ? injectedSubMessage : globalSubMessage;
  const progress = injectedProgress !== null ? injectedProgress : globalProgress;

  return createLoadingApi(isLoading, message, subMessage, progress);
}

export function provideLoading() {
  const loading = ref(false);
  const message = ref('Memuat Data...');
  const subMessage = ref('');
  const progress = ref(null);

  provide('loading', loading);
  provide('loadingMessage', message);
  provide('loadingSubMessage', subMessage);
  provide('loadingProgress', progress);

  return createLoadingApi(loading, message, subMessage, progress);
}
