<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-wrench text-blue-500"></i> Maintenance History Report
          </h1>
          <p class="text-gray-600 mt-1">Riwayat maintenance semua asset</p>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input
              type="date"
              v-model="dateFrom"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input
              type="date"
              v-model="dateTo"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="maintenances.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="maintenance in maintenances" :key="maintenance.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(maintenance.maintenance_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ maintenance.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ maintenance.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ maintenance.maintenance_type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                  {{ formatCurrency(maintenance.cost) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ maintenance.vendor || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(maintenance.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ maintenance.status }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="maintenances.length > 0" class="bg-gray-50">
              <tr>
                <td colspan="3" class="px-6 py-3 text-sm font-bold text-gray-900">Total Cost:</td>
                <td class="px-6 py-3 text-sm font-bold text-gray-900">
                  {{ formatCurrency(totalCost) }}
                </td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  maintenances: Array,
  assets: Array,
  filters: Object,
});

const assetId = ref(props.filters?.asset_id || '');
const maintenanceType = ref(props.filters?.maintenance_type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const totalCost = computed(() => {
  return props.maintenances.reduce((sum, m) => sum + (parseFloat(m.cost) || 0), 0);
});

function applyFilters() {
  router.get('/asset-management/reports/maintenance-history', {
    asset_id: assetId.value,
    maintenance_type: maintenanceType.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, { preserveState: true, replace: true });
}

function exportReport() {
  const params = new URLSearchParams({
    asset_id: assetId.value || '',
    maintenance_type: maintenanceType.value || '',
    date_from: dateFrom.value || '',
    date_to: dateTo.value || '',
    export: '1',
  });
  window.open(`/asset-management/reports/maintenance-history?${params.toString()}`, '_blank');
}

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function getStatusBadgeClass(status) {
  const classes = {
    'Scheduled': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-yellow-100 text-yellow-800',
    'Completed': 'bg-green-100 text-green-800',
    'Cancelled': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}
</script>

