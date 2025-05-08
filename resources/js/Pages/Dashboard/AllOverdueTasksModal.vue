<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity" aria-hidden="true">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
      </div>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Semua Task Overdue</h3>
                <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="mb-4 flex justify-between items-center">
                <input
                  type="text"
                  v-model="search"
                  @input="debouncedSearch"
                  placeholder="Cari task..."
                  class="w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div v-if="loading" class="flex justify-center items-center h-48">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
              </div>
              <div v-else>
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm rounded-xl overflow-hidden">
                    <thead class="bg-gray-100">
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
                      <tr v-for="task in tasks" :key="task.id" class="hover:bg-blue-50 transition">
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
                <div class="mt-4 flex justify-between items-center">
                  <div class="text-xs text-gray-500">Total: {{ total }}</div>
                  <div class="flex gap-2 items-center">
                    <button @click="changePage(page-1)" :disabled="page === 1" class="px-3 py-1 rounded bg-gray-100 text-gray-700 disabled:opacity-50">Prev</button>
                    <span class="px-3 py-1">{{ page }} / {{ lastPage }}</span>
                    <button @click="changePage(page+1)" :disabled="page === lastPage" class="px-3 py-1 rounded bg-gray-100 text-gray-700 disabled:opacity-50">Next</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';
const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const tasks = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const lastPage = ref(1);
const search = ref('');
const loading = ref(false);

async function fetchTasks() {
  loading.value = true;
  try {
    const res = await axios.get('/api/overdue-tasks/all', {
      params: { page: page.value, perPage: perPage.value, search: search.value }
    });
    tasks.value = res.data.data;
    total.value = res.data.total;
    lastPage.value = res.data.lastPage;
  } finally {
    loading.value = false;
  }
}

const debouncedSearch = debounce(() => {
  page.value = 1;
  fetchTasks();
}, 300);

watch(() => props.show, (val) => {
  if (val) {
    page.value = 1;
    search.value = '';
    fetchTasks();
  }
});

function changePage(p) {
  if (p >= 1 && p <= lastPage.value) {
    page.value = p;
    fetchTasks();
  }
}
</script> 