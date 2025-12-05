<template>
  <div class="bg-white rounded-xl p-4 shadow mb-6">
    <div class="flex justify-between items-center mb-2">
      <h2 class="text-lg font-semibold">Task Overdue</h2>
      <button class="text-xs text-blue-500 font-semibold" @click="showAllOverdue = true">Lihat Semua</button>
    </div>
    <div class="overflow-x-auto min-h-[120px]">
      <table class="min-w-full text-sm rounded-xl overflow-hidden">
        <thead class="bg-gray-100 sticky top-0 z-10">
          <tr>
            <th class="py-2 px-3 text-left font-bold">Task Number</th>
            <th class="py-2 px-3 text-left font-bold">Title</th>
            <th class="py-2 px-3 text-left font-bold">Outlet</th>
            <th class="py-2 px-3 text-left font-bold">Assigned To</th>
            <th class="py-2 px-3 text-left font-bold">Due Date</th>
            <th class="py-2 px-3 text-left font-bold">Finish Date</th>
            <th class="py-2 px-3 text-left font-bold">Hari Telat</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in tasks.slice(0, 5)" :key="task.id" class="hover:bg-blue-50 transition">
            <td class="py-2 px-3 font-semibold text-blue-700">{{ task.task_number || '-' }}</td>
            <td class="py-2 px-3">{{ task.title }}</td>
            <td class="py-2 px-3">{{ task.outlet }}</td>
            <td class="py-2 px-3">
              <span v-for="member in task.assigned_to" :key="member" class="inline-block bg-indigo-100 text-indigo-700 rounded px-2 py-0.5 mr-1 text-xs font-semibold">{{ member }}</span>
            </td>
            <td class="py-2 px-3">{{ task.due_date }}</td>
            <td class="py-2 px-3">{{ task.completed_at }}</td>
            <td class="py-2 px-3 text-red-500 font-bold">{{ task.late_days }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <AllOverdueTasksModal :show="showAllOverdue" @close="showAllOverdue = false" />
  </div>
</template>
<script setup>
import { ref } from 'vue';
import AllOverdueTasksModal from './AllOverdueTasksModal.vue';
const props = defineProps({ tasks: Array });
const showAllOverdue = ref(false);
</script>
<style scoped>
.badge {
  border-radius: 0.75rem;
  padding: 0.25rem 0.75rem;
  font-size: 0.85rem;
  font-weight: 700;
  display: inline-block;
}
</style> 