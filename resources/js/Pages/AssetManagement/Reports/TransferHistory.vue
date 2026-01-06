<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-exchange-alt text-blue-500"></i> Transfer History Report
          </h1>
          <p class="text-gray-600 mt-1">Riwayat transfer asset</p>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">From Outlet</label>
            <select
              v-model="fromOutletId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="transfers.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="transfer in transfers" :key="transfer.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(transfer.transfer_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ transfer.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ transfer.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ transfer.from_outlet?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ transfer.to_outlet?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ transfer.requester?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(transfer.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ transfer.status }}
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
  transfers: Array,
  assets: Array,
  outlets: Array,
  filters: Object,
});

const assetId = ref(props.filters?.asset_id || '');
const fromOutletId = ref(props.filters?.from_outlet_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

function applyFilters() {
  router.get('/asset-management/reports/transfer-history', {
    asset_id: assetId.value,
    from_outlet_id: fromOutletId.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, { preserveState: true, replace: true });
}

function exportReport() {
  const params = new URLSearchParams({
    asset_id: assetId.value || '',
    from_outlet_id: fromOutletId.value || '',
    date_from: dateFrom.value || '',
    date_to: dateTo.value || '',
    export: '1',
  });
  window.open(`/asset-management/reports/transfer-history?${params.toString()}`, '_blank');
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
    'Pending': 'bg-yellow-100 text-yellow-800',
    'Approved': 'bg-green-100 text-green-800',
    'Completed': 'bg-blue-100 text-blue-800',
    'Rejected': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}
</script>

