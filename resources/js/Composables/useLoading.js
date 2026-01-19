import { ref, provide, inject } from 'vue';

// Global loading state (fallback jika tidak ada provide)
const globalLoading = ref(false);
const globalMessage = ref('Memuat Data...');
const globalSubMessage = ref('');

export function useLoading() {
  // Try to inject if already provided (in child components)
  const injectedLoading = inject('loading', null);
  const injectedMessage = inject('loadingMessage', null);
  const injectedSubMessage = inject('loadingSubMessage', null);

  // Use injected if available, otherwise use global
  // Ensure we always have a valid ref
  const isLoading = injectedLoading !== null ? injectedLoading : globalLoading;
  const message = injectedMessage !== null ? injectedMessage : globalMessage;
  const subMessage = injectedSubMessage !== null ? injectedSubMessage : globalSubMessage;

  const setLoading = (value, msg = 'Memuat Data...', subMsg = '') => {
    if (isLoading && typeof isLoading.value !== 'undefined') {
      isLoading.value = value;
    }
    if (message && typeof message.value !== 'undefined') {
      message.value = msg;
    }
    if (subMessage && typeof subMessage.value !== 'undefined') {
      subMessage.value = subMsg;
    }
  };

  const showLoading = (msg = 'Memuat Data...', subMsg = '') => {
    setLoading(true, msg, subMsg);
  };

  const hideLoading = () => {
    setLoading(false);
  };

  return {
    isLoading,
    message,
    subMessage,
    setLoading,
    showLoading,
    hideLoading
  };
}

// Provide loading state for child components (call di AppLayout)
export function provideLoading() {
  const loading = ref(false);
  const message = ref('Memuat Data...');
  const subMessage = ref('');

  provide('loading', loading);
  provide('loadingMessage', message);
  provide('loadingSubMessage', subMessage);

  return {
    loading,
    message,
    subMessage
  };
}
