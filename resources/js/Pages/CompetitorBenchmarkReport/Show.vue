<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-teal-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">{{ formatMonth(record.report_month) }} · {{ record.outlet_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('competitor-benchmark-report.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
          <Link v-if="canEdit" :href="route('competitor-benchmark-report.edit', record.id)" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition">
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <button v-if="canDelete" type="button" @click="deleteReport" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Report</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><div class="text-gray-500 text-xs uppercase tracking-wide">Nomor</div><div class="font-semibold text-gray-900 mt-1">{{ record.number }}</div></div>
          <div><div class="text-gray-500 text-xs uppercase tracking-wide">Bulan Report</div><div class="font-semibold text-gray-900 mt-1">{{ formatMonth(record.report_month) }}</div></div>
          <div><div class="text-gray-500 text-xs uppercase tracking-wide">Outlet</div><div class="font-semibold text-gray-900 mt-1">{{ record.outlet_name }}</div></div>
          <div><div class="text-gray-500 text-xs uppercase tracking-wide">PIC</div><div class="font-semibold text-gray-900 mt-1">{{ formatPics(record.pics) }}</div></div>
          <div><div class="text-gray-500 text-xs uppercase tracking-wide">Dibuat Oleh</div><div class="font-semibold text-gray-900 mt-1">{{ record.creator?.nama_lengkap || '-' }}</div></div>
          <div v-if="record.notes" class="sm:col-span-2"><div class="text-gray-500 text-xs uppercase tracking-wide">Catatan</div><div class="text-gray-900 mt-1">{{ record.notes }}</div></div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-teal-50 to-white">
          <h2 class="text-lg font-semibold text-gray-800">Daftar Benchmark ({{ record.items?.length || 0 }})</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-[1400px] w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">No</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Brand / Restaurant</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Location</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Visit Date</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Product Benchmark</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Service Benchmark</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Pricing Benchmark</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Operational Benchmark</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Market & Positioning</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Summary Report</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-700">Development & Action Plan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in record.items" :key="item.id" class="border-b align-top hover:bg-teal-50/30">
                <td class="px-3 py-3 text-gray-500">{{ index + 1 }}</td>
                <td class="px-3 py-3 font-medium text-gray-900">{{ item.brand_restaurant_visited }}</td>
                <td class="px-3 py-3">{{ item.location || '-' }}</td>
                <td class="px-3 py-3 whitespace-nowrap">{{ formatDate(item.visit_date) }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.product_benchmark || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.service_benchmark || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.pricing_benchmark || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.operational_benchmark || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.market_positioning_benchmark || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.summary_report || '-' }}</td>
                <td class="px-3 py-3 whitespace-pre-wrap">{{ item.development_action_plan || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

function formatMonth(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID');
}

function formatPics(value) {
  if (Array.isArray(value) && value.length) {
    return value.map((entry) => entry?.name || entry?.nama_lengkap || `#${entry?.id || ''}`).join(', ');
  }
  return '-';
}

function deleteReport() {
  Swal.fire({
    title: 'Hapus Report?',
    text: `Report ${props.record.number} akan dihapus permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('competitor-benchmark-report.destroy', props.record.id));
    }
  });
}
</script>
