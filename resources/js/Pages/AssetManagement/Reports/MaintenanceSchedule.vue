<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-calendar-check text-blue-500"></i> Maintenance Schedule Report
          </h1>
          <p class="text-gray-600 mt-1">Jadwal maintenance asset</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="exportReport"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-download"></i> Export
          </button>
          <Link
            href="/asset-management/reports"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Asset</label>
            <select
              v-model="assetId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Asset</option>
              <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                {{ asset.asset_code }} - {{ asset.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select
              v-model="maintenanceType"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Tipe</option>
              <option value="Cleaning">Cleaning</option>
              <option value="Service">Service</option>
              <option value="Repair">Repair</option>
              <option value="Inspection">Inspection</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="isActive"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Due Status</label>
            <select
              v-model="dueStatus"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua</option>
              <option value="due">Due</option>
              <option value="overdue">Overdue</option>
              <option value="upcoming">Upcoming</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
            <button
              @click="applyFilters"
              class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg"
            >
              Apply Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequency</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Maintenance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Maintenance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="schedules.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="schedule in schedules" :key="schedule.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ schedule.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ schedule.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ schedule.maintenance_type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ schedule.frequency }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(schedule.last_maintenance_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm" :class="isDue(schedule.next_maintenance_date) ? 'font-semibold text-red-600' : 'text-gray-500'">
                  {{ formatDate(schedule.next_maintenance_date) }}
                  <span v-if="isOverdue(schedule.next_maintenance_date)" class="ml-2 text-xs text-red-600">(Overdue)</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="schedule.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ schedule.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  schedules: Array,
  assets: Array,
  filters: Object,
});

const assetId = ref(props.filters?.asset_id || '');
const maintenanceType = ref(props.filters?.maintenance_type || '');
const isActive = ref(props.filters?.is_active || 'all');
const dueStatus = ref(props.filters?.due_status || 'all');

function applyFilters() {
  router.get('/asset-management/reports/maintenance-schedule', {
    asset_id: assetId.value,
    maintenance_type: maintenanceType.value,
    is_active: isActive.value,
    due_status: dueStatus.value,
  }, { preserveState: true, replace: true });
}

function exportReport() {
  const params = new URLSearchParams({
    asset_id: assetId.value || '',
    maintenance_type: maintenanceType.value || '',
    is_active: isActive.value || 'all',
    due_status: dueStatus.value || 'all',
    export: '1',
  });
  window.open(`/asset-management/reports/maintenance-schedule?${params.toString()}`, '_blank');
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function isDue(date) {
  if (!date) return false;
  const dueDate = new Date(date);
  const today = new Date();
  return dueDate <= today;
}

function isOverdue(date) {
  if (!date) return false;
  const dueDate = new Date(date);
  const today = new Date();
  return dueDate < today;
}
</script>

