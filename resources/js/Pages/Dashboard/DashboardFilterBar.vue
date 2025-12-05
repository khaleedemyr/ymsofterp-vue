<template>
  <div class="flex flex-wrap gap-4 items-center bg-white p-4 rounded-xl shadow mb-6">
    <div>
      <label class="block text-xs font-semibold mb-1">Periode</label>
      <input type="date" v-model="startDate" class="border rounded px-2 py-1" />
      <span class="mx-1">-</span>
      <input type="date" v-model="endDate" class="border rounded px-2 py-1" />
    </div>
    <button @click="loadData" class="ml-auto px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-xs text-white font-semibold">Load Data</button>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
const startDate = ref('');
const endDate = ref('');
const emit = defineEmits(['filter-change']);
function loadData() {
  emit('filter-change', {
    startDate: startDate.value,
    endDate: endDate.value,
  });
}
onMounted(() => {
  const today = new Date();
  const prior = new Date();
  prior.setDate(today.getDate() - 29); // 30 hari termasuk hari ini
  function toInputDate(d) {
    return d.toISOString().slice(0, 10);
  }
  startDate.value = toInputDate(prior);
  endDate.value = toInputDate(today);
  emit('filter-change', {
    startDate: startDate.value,
    endDate: endDate.value,
  });
});
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 