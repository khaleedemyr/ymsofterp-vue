<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <i class="fa-solid fa-list-alt"></i> Activity Log Report
        </h1>
        <div class="flex gap-2 items-center">
          <button 
            @click="resetFilters" 
            class="bg-gray-500 text-white px-4 py-2 rounded-lg shadow hover:bg-gray-600 transition-all font-semibold"
          >
            <i class="fa fa-refresh mr-2"></i> Reset
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
          <i class="fa fa-filter mr-2"></i> Filters
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Search (Description, Module, User, IP)
            </label>
            <input 
              type="text" 
              v-model="filters.search" 
              @keyup.enter="applyFilters"
              placeholder="Search..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <!-- User -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
            <select 
              v-model="filters.user_id" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">All Users</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.nama_lengkap }}
              </option>
            </select>
          </div>

          <!-- Activity Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Type</label>
            <select 
              v-model="filters.activity_type" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">All Types</option>
              <option v-for="type in activityTypes" :key="type" :value="type">
                {{ type }}
              </option>
            </select>
          </div>

          <!-- Module -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Module</label>
            <select 
              v-model="filters.module" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">All Modules</option>
              <option v-for="module in modules" :key="module" :value="module">
                {{ module }}
              </option>
            </select>
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Per Page</label>
            <select 
              v-model="filters.per_page" 
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <!-- Apply Button -->
          <div class="flex items-end">
            <button 
              @click="applyFilters" 
              class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-600 transition-all font-semibold"
            >
              <i class="fa fa-search mr-2"></i> Apply Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Logs</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ logs.total || 0 }}</p>
            </div>
            <i class="fa fa-list text-blue-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Showing</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ logs.from || 0 }} - {{ logs.to || 0 }}
              </p>
            </div>
            <i class="fa fa-eye text-green-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unique Users</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ users.length }}</p>
            </div>
            <i class="fa fa-users text-yellow-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Modules</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ modules.length }}</p>
            </div>
            <i class="fa fa-cubes text-purple-500 text-2xl"></i>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Date & Time
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  User
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Activity Type
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Module
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Description
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  IP Address
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="logs.data && logs.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                  <i class="fa fa-inbox text-4xl mb-2"></i>
                  <p>No activity logs found</p>
                </td>
              </tr>
              <tr 
                v-for="log in logs.data" 
                :key="log.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ formatDateTime(log.created_at) }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  <div class="flex items-center">
                    <i class="fa fa-user mr-2 text-gray-400"></i>
                    {{ log.user_name || 'Unknown' }}
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span 
                    :class="getActivityTypeBadgeClass(log.activity_type)"
                    class="px-2 py-1 text-xs font-semibold rounded-full"
                  >
                    {{ log.activity_type }}
                  </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ log.module || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white max-w-md">
                  <div class="truncate" :title="log.description">
                    {{ log.description || '-' }}
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                  {{ log.ip_address || '-' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm">
                  <button 
                    @click="showDetail(log)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold"
                  >
                    <i class="fa fa-eye mr-1"></i> View
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.data && logs.data.length > 0" class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-gray-300">
              Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} results
            </div>
            <div class="flex gap-2">
              <Link 
                v-if="logs.prev_page_url"
                :href="logs.prev_page_url"
                class="px-3 py-1 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-500"
              >
                Previous
              </Link>
              <Link 
                v-if="logs.next_page_url"
                :href="logs.next_page_url"
                class="px-3 py-1 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-500"
              >
                Next
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Detail Modal -->
      <div 
        v-if="selectedLog"
        class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4"
        @click.self="closeDetail"
      >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">
              <i class="fa fa-info-circle mr-2"></i> Activity Log Detail
            </h2>
            <button 
              @click="closeDetail"
              class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            >
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>
          
          <div class="p-6 space-y-4">
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date & Time</label>
                <p class="text-gray-900 dark:text-white">{{ formatDateTime(selectedLog.created_at) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">User</label>
                <p class="text-gray-900 dark:text-white">{{ selectedLog.user_name || 'Unknown' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Activity Type</label>
                <p class="text-gray-900 dark:text-white">
                  <span :class="getActivityTypeBadgeClass(selectedLog.activity_type)" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ selectedLog.activity_type }}
                  </span>
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Module</label>
                <p class="text-gray-900 dark:text-white">{{ selectedLog.module || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">IP Address</label>
                <p class="text-gray-900 dark:text-white">{{ selectedLog.ip_address || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">User Agent</label>
                <p class="text-gray-900 dark:text-white text-xs break-all">{{ selectedLog.user_agent || '-' }}</p>
              </div>
            </div>

            <!-- Description -->
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</label>
              <p class="text-gray-900 dark:text-white mt-1">{{ selectedLog.description || '-' }}</p>
            </div>

            <!-- Old Data -->
            <div v-if="selectedLog.old_data">
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Old Data</label>
              <pre class="mt-1 p-3 bg-gray-100 dark:bg-gray-900 rounded text-xs text-gray-900 dark:text-white overflow-x-auto">{{ JSON.stringify(selectedLog.old_data, null, 2) }}</pre>
            </div>

            <!-- New Data -->
            <div v-if="selectedLog.new_data">
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">New Data</label>
              <pre class="mt-1 p-3 bg-gray-100 dark:bg-gray-900 rounded text-xs text-gray-900 dark:text-white overflow-x-auto">{{ JSON.stringify(selectedLog.new_data, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  logs: Object,
  users: Array,
  activityTypes: Array,
  modules: Array,
  filters: Object,
});

const filters = reactive({
  user_id: props.filters?.user_id || '',
  activity_type: props.filters?.activity_type || '',
  module: props.filters?.module || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || 25,
});

const selectedLog = ref(null);

function applyFilters() {
  router.get('/report/activity-log', filters, {
    preserveState: true,
    preserveScroll: true,
  });
}

function resetFilters() {
  filters.user_id = '';
  filters.activity_type = '';
  filters.module = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.search = '';
  filters.per_page = 25;
  applyFilters();
}

function showDetail(log) {
  selectedLog.value = log;
}

function closeDetail() {
  selectedLog.value = null;
}

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  const date = new Date(dateTime);
  return date.toLocaleString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function getActivityTypeBadgeClass(type) {
  const classes = {
    'create': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'update': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'delete': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'approve': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'reject': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    'login': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    'logout': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
  };
  return classes[type?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}
</script>

