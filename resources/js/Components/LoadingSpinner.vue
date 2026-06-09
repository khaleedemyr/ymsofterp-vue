<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="safeIsLoading" class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 shadow-2xl flex flex-col items-center justify-center min-w-[280px] max-w-sm w-full">
          <img
            src="/images/logo-icon.png"
            alt="Loading"
            class="w-16 h-16 animate-spin mb-4"
          />
          <p class="text-gray-800 text-lg font-semibold text-center">{{ safeMessage }}</p>
          <p v-if="safeSubMessage" class="text-gray-500 text-sm mt-2 text-center">{{ safeSubMessage }}</p>

          <div v-if="hasProgress" class="w-full mt-5">
            <div class="flex justify-between text-xs text-gray-500 mb-1.5">
              <span>Progress</span>
              <span class="font-semibold tabular-nums">{{ safeProgress }}%</span>
            </div>
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-300 ease-out bg-gradient-to-r from-rose-500 to-indigo-600"
                :style="{ width: `${safeProgress}%` }"
              />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useLoading } from '@/Composables/useLoading';

const { isLoading, message, subMessage, progress } = useLoading();
const isMounted = ref(false);

onMounted(() => {
  isMounted.value = true;
});

const safeIsLoading = computed(() => {
  if (!isMounted.value || !isLoading || typeof isLoading.value === 'undefined') {
    return false;
  }
  return isLoading.value;
});

const safeMessage = computed(() => {
  if (!message || typeof message.value === 'undefined') {
    return 'Memuat Data...';
  }
  return message.value || 'Memuat Data...';
});

const safeSubMessage = computed(() => {
  if (!subMessage || typeof subMessage.value === 'undefined') {
    return '';
  }
  return subMessage.value || '';
});

const hasProgress = computed(() => {
  if (!progress || progress.value === null || progress.value === undefined) {
    return false;
  }
  return true;
});

const safeProgress = computed(() => {
  if (!hasProgress.value) {
    return 0;
  }
  return Math.max(0, Math.min(100, Math.round(progress.value)));
});
</script>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.animate-spin {
  animation: spin 2s linear infinite;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
