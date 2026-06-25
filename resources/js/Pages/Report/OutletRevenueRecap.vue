<template>
  <AppLayout>
    <div class="max-w-[1400px] mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-solid fa-store text-blue-600"></i>
        Rekap Revenue Outlet
      </h1>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal From</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal To</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div class="md:col-span-2 flex justify-end">
            <button
              type="button"
              @click="fetchReport"
              :disabled="loading"
              class="px-6 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loading ? 'Memuat...' : 'Tampilkan' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-16 text-gray-500">Memuat data...</div>

      <div v-else-if="!showReport" class="text-center py-16 text-gray-400 bg-white rounded-xl shadow">
        Pilih rentang tanggal lalu klik <strong>Tampilkan</strong> untuk melihat rekap revenue outlet.
      </div>

      <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-900 text-white">
              <th class="px-4 py-3 text-left min-w-[220px] sticky left-0 bg-gray-900 z-10">Outlet</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Total Sales</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Discount</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Service Charge</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">PB 1</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Commfee</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Total Pax</th>
              <th class="px-4 py-3 text-right whitespace-nowrap">Average Check</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in report.groups" :key="group.region_id ?? group.region_name">
              <tr class="bg-indigo-50 border-t-2 border-indigo-200">
                <td colspan="8" class="px-4 py-2 font-bold text-indigo-900 uppercase tracking-wide text-xs">
                  <i class="fa-solid fa-map-location-dot mr-2"></i>{{ group.region_name }}
                </td>
              </tr>
              <tr
                v-for="row in group.rows"
                :key="row.outlet_id"
                class="border-b border-gray-100 hover:bg-gray-50"
              >
                <td class="px-4 py-2.5 font-medium text-gray-800 sticky left-0 bg-white">{{ row.outlet_name }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(row.total_sales) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(row.discount) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(row.service_charge) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(row.pb1) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(row.commfee) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatNumber(row.total_pax) }}</td>
                <td class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(row.avg_check) }}</td>
              </tr>
              <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                <td class="px-4 py-2.5 text-right text-gray-700 sticky left-0 bg-gray-100">Subtotal {{ group.region_name }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.total_sales) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.discount) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.service_charge) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.pb1) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.commfee) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatNumber(group.subtotal.total_pax) }}</td>
                <td class="px-4 py-2.5 text-right">{{ formatCurrency(group.subtotal.avg_check) }}</td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr class="bg-blue-900 text-white font-bold">
              <td class="px-4 py-3 sticky left-0 bg-blue-900">GRAND TOTAL</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.total_sales) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.discount) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.service_charge) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.pb1) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.commfee) }}</td>
              <td class="px-4 py-3 text-right">{{ formatNumber(report.totals.total_pax) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(report.totals.avg_check) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const filters = reactive({
  date_from: '',
  date_to: '',
});

const loading = ref(false);
const showReport = ref(false);
const report = ref({ groups: [], totals: {} });

function formatCurrency(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
}

function formatNumber(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

async function fetchReport() {
  if (!filters.date_from || !filters.date_to) {
    Swal.fire({ icon: 'warning', title: 'Tanggal wajib diisi', text: 'Pilih Tanggal From dan Tanggal To.' });
    return;
  }

  loading.value = true;
  showReport.value = false;
  try {
    const res = await axios.get('/api/report/outlet-revenue-recap', { params: { ...filters } });
    report.value = res.data;
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memuat rekap revenue outlet.';
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  } finally {
    loading.value = false;
  }
}
</script>
