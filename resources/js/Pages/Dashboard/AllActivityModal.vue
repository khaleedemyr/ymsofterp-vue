<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
  show: Boolean
});

const emit = defineEmits(['close']);

const activities = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const search = ref('');
const loading = ref(false);

async function fetchActivities() {
  loading.value = true;
  try {
    const res = await axios.get('/api/activity-latest', {
      params: {
        page: currentPage.value,
        perPage: 10,
        search: search.value
      }
    });
    activities.value = res.data.data;
    lastPage.value = res.data.last_page;
  } catch (error) {
    console.error('Error fetching activities:', error);
  } finally {
    loading.value = false;
  }
}

const debouncedSearch = debounce(() => {
  currentPage.value = 1;
  fetchActivities();
}, 300);

onMounted(() => {
  fetchActivities();
});

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity" aria-hidden="true">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
      </div>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Semua Aktivitas</h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              
              <!-- Search -->
              <div class="mb-4">
                <input
                  type="text"
                  v-model="search"
                  @input="debouncedSearch"
                  placeholder="Cari aktivitas..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Loading State -->
              <div v-if="loading" class="flex justify-center items-center h-48">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
              </div>

              <!-- Activity List -->
              <div v-else-if="activities.length > 0" class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                <div v-for="a in activities" :key="a.id" class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50">
                  <div class="w-10 h-10 rounded-full bg-indigo-200 flex items-center justify-center font-bold text-white text-lg">
                    {{ a.user_initials }}
                  </div>
                  <div class="flex-1">
                    <div class="font-semibold text-indigo-800">
                      {{ a.user_name }} 
                      <span class="text-xs text-gray-400 font-normal">{{ a.type }}</span>
                    </div>
                    <div class="text-sm text-gray-600">{{ a.description }}</div>
                    <div class="text-xs text-gray-400">{{ a.time_ago }}</div>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="h-48 flex items-center justify-center text-gray-400">
                Tidak ada aktivitas
              </div>

              <!-- Pagination -->
              <div v-if="!loading && activities.length > 0" class="mt-4 flex justify-center gap-2">
                <button
                  @click="currentPage--; fetchActivities()"
                  :disabled="currentPage === 1"
                  class="px-3 py-1 rounded bg-gray-100 text-gray-700 disabled:opacity-50"
                >
                  Previous
                </button>
                <span class="px-3 py-1">{{ currentPage }} / {{ lastPage }}</span>
                <button
                  @click="currentPage++; fetchActivities()"
                  :disabled="currentPage === lastPage"
                  class="px-3 py-1 rounded bg-gray-100 text-gray-700 disabled:opacity-50"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template> 