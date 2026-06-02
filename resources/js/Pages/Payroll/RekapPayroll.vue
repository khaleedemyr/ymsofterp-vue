<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  months: Array,
  years: Array,
  filter: Object,
  rows: Array,
  summary: Object,
});

const month = ref(props.filter?.month || String(new Date().getMonth() + 1).padStart(2, '0'));
const year = ref(props.filter?.year || new Date().getFullYear());
const loading = ref(false);

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function loadData() {
  if (!month.value || !year.value) return;
  loading.value = true;
  router.get('/payroll/rekap', {
    month: month.value,
    year: year.value,
  }, {
    preserveState: true,
    onFinish: () => {
      loading.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Rekap Payroll">
    <div class="w-full min-h-[60vh] py-4">
      <div v-if="loading" class="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-table-list text-blue-600"></i>
          Rekap Payroll
        </h1>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="flex flex-wrap gap-4 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Bulan</label>
            <select v-model="month" class="form-input rounded-xl shadow-lg w-48">
              <option value="">Pilih Bulan</option>
              <option v-for="m in months" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Tahun</label>
            <select v-model="year" class="form-input rounded-xl shadow-lg w-32">
              <option value="">Pilih Tahun</option>
              <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
            </select>
          </div>

          <button
            type="button"
            class="bg-gradient-to-br from-blue-500 to-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg hover:opacity-90 disabled:opacity-50"
            :disabled="!month || !year || loading"
            @click="loadData"
          >
            <i class="fa fa-search mr-2"></i> Lihat Data
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-800 text-white">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Outlet</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Gaji 1</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Gaji 2</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase">Grand Total Gaji</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase">BPJS Perusahaan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="!rows || !rows.length">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  Tidak ada data payroll untuk periode ini.
                </td>
              </tr>
              <tr v-for="(row, idx) in rows" :key="`${row.outlet}-${idx}`" class="hover:bg-blue-50">
                <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ row.outlet }}</td>
                <td class="px-4 py-3 text-sm text-right text-blue-700 font-semibold">{{ formatCurrency(row.total_gaji_1) }}</td>
                <td class="px-4 py-3 text-sm text-right text-indigo-700 font-semibold">{{ formatCurrency(row.total_gaji_2) }}</td>
                <td class="px-4 py-3 text-sm text-right text-green-700 font-bold">{{ formatCurrency(row.grand_total_gaji) }}</td>
                <td class="px-4 py-3 text-sm text-right text-teal-700 font-semibold">{{ formatCurrency(row.bpjs_perusahaan) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-900 text-white">
              <tr>
                <td class="px-4 py-3 text-sm font-bold">TOTAL</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-blue-300">{{ formatCurrency(summary?.total_gaji_1) }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-indigo-300">{{ formatCurrency(summary?.total_gaji_2) }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-amber-300">{{ formatCurrency(summary?.grand_total_gaji) }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-teal-300">{{ formatCurrency(summary?.bpjs_perusahaan) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

