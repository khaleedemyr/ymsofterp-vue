<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative"
           style="max-height: 70vh; display: flex; flex-direction: column; justify-content: flex-start;">
        <button @click="onClose" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <h2 class="text-xl font-bold mb-4 text-center">Timeline</h2>
        <div v-if="loading" class="flex justify-center items-center py-8 flex-1">
          <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="logs.length === 0" class="text-center text-gray-400 py-8 flex-1">
          Tidak ada aktivitas.
        </div>
        <div v-else class="relative flex flex-col items-center flex-1 overflow-y-auto" style="max-height: 50vh;">
          <div class="absolute left-1/2 -translate-x-1/2 w-1 bg-blue-100 h-full z-0"></div>
          <div v-for="(log, idx) in logs" :key="log.id" class="w-full flex items-center mb-8 last:mb-0 relative z-10">
            <div class="w-1/2 text-right pr-4">
              <div class="text-xs text-gray-500">{{ formatDate(log.created_at) }}</div>
              <div class="text-sm font-semibold text-blue-700">{{ log.user_name || 'Unknown User' }}</div>
            </div>
            <div class="flex flex-col items-center">
              <div class="w-4 h-4 rounded-full bg-blue-500 border-2 border-white z-10"></div>
              <div v-if="idx < logs.length - 1" class="w-1 h-8 bg-blue-200"></div>
            </div>
            <div class="w-1/2 pl-4">
              <div class="text-sm font-bold">{{ log.activity_type }}</div>
              <div class="text-xs text-gray-600 mb-1">{{ log.description }}</div>
              <div v-if="log.old_value || log.new_value" class="text-xs text-gray-400">
                <span v-if="log.old_value">Old: {{ log.old_value }}</span>
                <span v-if="log.old_value && log.new_value"> → </span>
                <span v-if="log.new_value">New: {{ log.new_value }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
  onClose: Function
});

const logs = ref([]);
const loading = ref(false);

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
}

async function fetchTimeline() {
  if (!props.taskId) return;
  loading.value = true;
  try {
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/timeline`);
    logs.value = res.data;
  } catch (e) {
    logs.value = [];
  } finally {
    loading.value = false;
  }
}

watch(() => props.show, (val) => {
  if (val) fetchTimeline();
});

onMounted(() => {
  if (props.show) fetchTimeline();
});
</script>

<style scoped>
.bg-blue-100 { background-color: #dbeafe; }
.bg-blue-200 { background-color: #bfdbfe; }
.bg-blue-500 { background-color: #3b82f6; }
.border-white { border-color: #fff; }
</style> 