<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="safeIsLoading" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 shadow-2xl flex flex-col items-center justify-center min-w-[200px]">
          <img 
            src="/images/logo-icon.png" 
            alt="Loading" 
            class="w-16 h-16 animate-spin mb-4"
          />
          <p class="text-gray-700 text-lg font-semibold">{{ safeMessage }}</p>
          <p v-if="safeSubMessage" class="text-gray-500 text-sm mt-2">{{ safeSubMessage }}</p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useLoading } from '@/Composables/useLoading';

const { isLoading, message, subMessage } = useLoading();
const isMounted = ref(false);

onMounted(() => {
  isMounted.value = true;
});

// Safety check untuk memastikan isLoading adalah reactive ref
const safeIsLoading = computed(() => {
  if (!isMounted.value) {
    return false;
  }
  if (!isLoading || typeof isLoading === 'undefined') {
    return false;
  }
  if (typeof isLoading.value === 'undefined') {
    return false;
  }
  return isLoading.value;
});

const safeMessage = computed(() => {
  if (!message || typeof message === 'undefined') {
    return 'Memuat Data...';
  }
  if (typeof message.value === 'undefined') {
    return 'Memuat Data...';
  }
  return message.value || 'Memuat Data...';
});

const safeSubMessage = computed(() => {
  if (!subMessage || typeof subMessage === 'undefined') {
    return '';
  }
  if (typeof subMessage.value === 'undefined') {
    return '';
  }
  return subMessage.value || '';
});
</script>

<style scoped>
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
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
