<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-teal-600"></i>
            Competitor Benchmark Report — Laporan
          </h1>
          <p class="text-sm text-gray-500 mt-1">Rekap benchmark kompetitor per periode report</p>
        </div>
        <Link
          :href="route('competitor-benchmark-report.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan From *</label>
            <input
              v-model="filters.month_from"
              type="month"
              class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan To *</label>
            <input
              v-model="filters.month_to"
              type="month"
              class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nomor report / brand..."
              class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500"
            />
          </div>
          <div class="md:col-span-3 lg:col-span-4 flex justify-end gap-2">
            <button
              type="button"
              @click="fetchReport"
              :disabled="loading"
              class="px-6 py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50"
            >
              {{ loading ? 'Memuat...' : 'Tampilkan' }}
            </button>
            <button
              type="button"
              @click="exportExcel"
              :disabled="loading || !filters.month_from || !filters.month_to"
              class="px-6 py-2.5 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
            >
              Export Excel
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-16 text-gray-500">Memuat data...</div>

      <div v-else-if="!showReport" class="text-center py-16 text-gray-400 bg-white rounded-xl shadow">
        Pilih rentang bulan lalu klik <strong>Tampilkan</strong> untuk melihat laporan benchmark.
      </div>

      <div v-else-if="report.rows.length === 0" class="text-center py-16 text-gray-500 bg-white rounded-xl shadow">
        Tidak ada data pada filter yang dipilih.
      </div>

      <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
        <div class="px-6 py-3 border-b text-sm text-gray-600">
          Total baris: <strong>{{ report.total }}</strong>
        </div>
        <table class="min-w-[1800px] w-full text-xs border-collapse">
          <thead class="bg-gray-800 text-white">
            <tr>
              <th class="px-3 py-2 text-left">NO</th>
              <th class="px-3 py-2 text-left">REPORT NUMBER</th>
              <th class="px-3 py-2 text-left">REPORT MONTH</th>
              <th class="px-3 py-2 text-left">PIC</th>
              <th class="px-3 py-2 text-left">CREATED BY</th>
              <th class="px-3 py-2 text-left">BRAND / RESTAURANT</th>
              <th class="px-3 py-2 text-left">LOCATION</th>
              <th class="px-3 py-2 text-left">VISIT DATE</th>
              <th class="px-3 py-2 text-left">PRODUCT BENCHMARK</th>
              <th class="px-3 py-2 text-left">SERVICE BENCHMARK</th>
              <th class="px-3 py-2 text-left">PRICING BENCHMARK</th>
              <th class="px-3 py-2 text-left">OPERATIONAL BENCHMARK</th>
              <th class="px-3 py-2 text-left">MARKET & POSITIONING</th>
              <th class="px-3 py-2 text-left">SUMMARY REPORT</th>
              <th class="px-3 py-2 text-left">DEVELOPMENT & ACTION PLAN</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in report.rows"
              :key="`${row.no}-${row.report_number}-${row.brand_restaurant_visited}`"
              class="border-b hover:bg-teal-50/40 align-top"
            >
              <td class="px-3 py-2">{{ row.no }}</td>
              <td class="px-3 py-2 font-semibold">{{ row.report_number }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ formatMonth(row.report_month) }}</td>
              <td class="px-3 py-2">{{ row.pics }}</td>
              <td class="px-3 py-2">{{ row.created_by }}</td>
              <td class="px-3 py-2 font-medium">{{ row.brand_restaurant_visited }}</td>
              <td class="px-3 py-2">{{ row.location }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.visit_date) }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.product_benchmark }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.service_benchmark }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.pricing_benchmark }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.operational_benchmark }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.market_positioning_benchmark }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.summary_report }}</td>
              <td class="px-3 py-2 whitespace-pre-wrap max-w-[180px]">{{ row.development_action_plan }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const now = new Date();
const filters = reactive({
  month_from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
  month_to: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
  search: '',
});

const loading = ref(false);
const showReport = ref(false);
const report = ref({ rows: [], total: 0 });

function formatMonth(value) {
  if (!value) return '-';
  const d = new Date(`${value}-01`);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID');
}

async function fetchReport() {
  if (!filters.month_from || !filters.month_to) {
    Swal.fire({ icon: 'warning', title: 'Bulan wajib diisi', text: 'Pilih Bulan From dan Bulan To.' });
    return;
  }

  loading.value = true;
  showReport.value = false;
  try {
    const params = {
      month_from: filters.month_from,
      month_to: filters.month_to,
    };
    if (filters.search.trim()) params.search = filters.search.trim();

    const res = await axios.get('/api/report/competitor-benchmark-report', { params });
    report.value = res.data;
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memuat laporan benchmark.';
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  } finally {
    loading.value = false;
  }
}

function exportExcel() {
  if (!filters.month_from || !filters.month_to) {
    Swal.fire({ icon: 'warning', title: 'Bulan wajib diisi', text: 'Pilih Bulan From dan Bulan To.' });
    return;
  }

  const query = new URLSearchParams({
    month_from: filters.month_from,
    month_to: filters.month_to,
  });
  if (filters.search.trim()) query.set('search', filters.search.trim());

  window.open(`/report/competitor-benchmark-report/export?${query.toString()}`, '_blank');
}
</script>
