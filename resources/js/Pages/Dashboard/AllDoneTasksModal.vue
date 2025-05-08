<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-2">
          <h2 class="text-xl font-bold">Semua Tasks Selesai</h2>
          <input v-model="search" @input="onSearch" type="text" placeholder="Cari task..." class="border rounded px-3 py-1 text-sm w-full md:w-64" />
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm rounded-xl overflow-hidden">
            <thead class="bg-gray-100 sticky top-0 z-10">
              <tr>
                <th class="py-2 px-3 text-left font-bold">Task Number</th>
                <th class="py-2 px-3 text-left font-bold">Title</th>
                <th class="py-2 px-3 text-left font-bold">Outlet</th>
                <th class="py-2 px-3 text-left font-bold">Assigned To</th>
                <th class="py-2 px-3 text-left font-bold">Due Date</th>
                <th class="py-2 px-3 text-left font-bold">Status</th>
                <th class="py-2 px-3 text-center font-bold">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="task in tasks" :key="task.id" class="hover:bg-blue-50 transition">
                <td class="py-2 px-3 font-semibold text-blue-700 cursor-pointer underline" @click="openDetail(task)">{{ task.task_number }}</td>
                <td class="py-2 px-3">{{ task.title }}</td>
                <td class="py-2 px-3">{{ task.outlet }}</td>
                <td class="py-2 px-3">
                  <span v-for="member in task.assigned_to" :key="member" class="inline-block bg-indigo-100 text-indigo-700 rounded px-2 py-0.5 mr-1 text-xs font-semibold">{{ member }}</span>
                </td>
                <td class="py-2 px-3">{{ task.due_date }}</td>
                <td class="py-2 px-3">
                  <span :class="['badge', statusColor(task.status)]">{{ task.status }}</span>
                </td>
                <td class="py-2 px-3 text-center">
                  <button class="p-2 rounded hover:bg-blue-100 transition" @click="openDetail(task)">
                    <i class="fas fa-eye text-blue-600"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-between items-center mt-2" v-if="lastPage > 1">
          <button :disabled="page === 1" @click="changePage(page-1)" class="px-2 py-1 rounded bg-gray-100 text-gray-600 disabled:opacity-50">Prev</button>
          <span class="text-xs">Page {{ page }} of {{ lastPage }}</span>
          <button :disabled="page === lastPage" @click="changePage(page+1)" class="px-2 py-1 rounded bg-gray-100 text-gray-600 disabled:opacity-50">Next</button>
        </div>
        <TaskDetailModal :task="selectedTask" :show="showDetail" @close="showDetail = false" />
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import TaskDetailModal from './TaskDetailModal.vue';
const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const tasks = ref([]);
const page = ref(1);
const perPage = 10;
const lastPage = ref(1);
const search = ref('');
const searchTimeout = ref(null);
const showDetail = ref(false);
const selectedTask = ref({});
async function fetchTasks() {
  const res = await axios.get('/api/maintenance-tasks/done', { params: { page: page.value, perPage, search: search.value } });
  tasks.value = res.data.data;
  lastPage.value = res.data.lastPage;
}
function changePage(p) {
  if (p >= 1 && p <= lastPage.value) {
    page.value = p;
    fetchTasks();
  }
}
function openDetail(task) {
  selectedTask.value = task;
  showDetail.value = true;
}
function statusColor(status) {
  switch (status) {
    case 'Done': return 'bg-green-100 text-green-700';
    case 'Overdue': return 'bg-red-100 text-red-700';
    case 'PO': return 'bg-blue-100 text-blue-700';
    case 'PR': return 'bg-yellow-100 text-yellow-700';
    default: return 'bg-gray-100 text-gray-700';
  }
}
function onSearch() {
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => {
    page.value = 1;
    fetchTasks();
  }, 400);
}
watch(() => props.show, (val) => { if (val) fetchTasks(); });
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