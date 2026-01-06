<template>
  <AppLayout>
    <div class="max-w-6xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-blue-500"></i> Asset Depreciation
          </h1>
          <p class="text-gray-600 mt-1">{{ asset.asset_code }} - {{ asset.name }}</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="calculateDepreciation"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-calculator"></i> Calculate Depreciation
          </button>
          <Link
            :href="`/asset-management/assets/${asset.id}`"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Depreciation Summary -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Asset Information -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Asset Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Purchase Price</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(asset.purchase_price) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Purchase Date</label>
                <p class="text-sm text-gray-900 mt-1">{{ formatDate(asset.purchase_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Useful Life</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.useful_life ? asset.useful_life + ' tahun' : '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Depreciation Method</label>
                <p class="text-sm text-gray-900 mt-1">{{ depreciation?.depreciation_method || 'Straight-Line' }}</p>
              </div>
            </div>
          </div>

          <!-- Depreciation Details -->
          <div v-if="depreciation" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Depreciation Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Annual Depreciation</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(depreciation.annual_depreciation) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Monthly Depreciation</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(depreciation.monthly_depreciation) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Accumulated Depreciation</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(depreciation.accumulated_depreciation) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Current Value</label>
                <p class="text-sm font-semibold text-blue-600 mt-1">{{ formatCurrency(depreciation.current_value) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Last Calculated</label>
                <p class="text-sm text-gray-900 mt-1">{{ formatDate(depreciation.last_calculated_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Depreciation History -->
          <div v-if="history && history.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Depreciation History</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depreciation</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Accumulated</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Value</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in history" :key="item.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatDate(item.period_date) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatCurrency(item.depreciation_amount) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatCurrency(item.accumulated_depreciation) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-blue-600">{{ formatCurrency(item.current_value) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-center text-gray-500">Belum ada history depreciation</p>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Quick Stats -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Quick Stats</h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Years Depreciated</label>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ depreciation ? calculateYearsDepreciated() : '0' }}
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Remaining Years</label>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ asset.useful_life ? (asset.useful_life - calculateYearsDepreciated()).toFixed(1) : '-' }}
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Depreciation Rate</label>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ asset.useful_life ? ((1 / asset.useful_life) * 100).toFixed(2) : '-' }}%
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  asset: Object,
  depreciation: Object,
  history: Array,
});

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

function calculateYearsDepreciated() {
  if (!props.asset.purchase_date || !props.depreciation) return 0;
  const purchaseDate = new Date(props.asset.purchase_date);
  const today = new Date();
  const years = (today - purchaseDate) / (1000 * 60 * 60 * 24 * 365);
  return Math.min(years, props.asset.useful_life || 0);
}

async function calculateDepreciation() {
  const result = await Swal.fire({
    title: 'Calculate Depreciation?',
    text: `Hitung depreciation untuk asset "${props.asset.name}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Calculate',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/depreciations/${props.asset.id}/calculate`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Depreciation berhasil dihitung', 'success');
        router.reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal calculate depreciation', 'error');
    }
  }
}
</script>

