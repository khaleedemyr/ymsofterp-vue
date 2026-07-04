<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-amber-500"></i>
            NPD Plan & Report — Laporan
          </h1>
          <p class="text-sm text-gray-500 mt-1">Rekap produk NPD per periode report</p>
        </div>
        <Link
          :href="route('npd-plan-report.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan From *</label>
            <input
              v-model="filters.month_from"
              type="month"
              class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan To *</label>
            <input
              v-model="filters.month_to"
              type="month"
              class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select v-model="filters.status" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
            <select v-model="filters.purpose" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
              <option value="">Semua Purpose</option>
              <option v-for="opt in purposeOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nomor report / produk..."
              class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
            />
          </div>
          <div class="md:col-span-3 lg:col-span-6 flex justify-end gap-2">
            <button
              type="button"
              @click="fetchReport"
              :disabled="loading"
              class="px-6 py-2.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
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
        Pilih rentang bulan lalu klik <strong>Tampilkan</strong> untuk melihat laporan NPD.
      </div>

      <div v-else-if="report.rows.length === 0" class="text-center py-16 text-gray-500 bg-white rounded-xl shadow">
        Tidak ada data pada filter yang dipilih.
      </div>

      <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
        <div class="px-6 py-3 border-b text-sm text-gray-600">
          Total baris: <strong>{{ report.total }}</strong>
        </div>
        <table class="min-w-[1600px] w-full text-xs border-collapse">
          <thead class="bg-gray-800 text-white">
            <tr>
              <th class="px-3 py-2 text-left">NO</th>
              <th class="px-3 py-2 text-left">REPORT NUMBER</th>
              <th class="px-3 py-2 text-left">REPORT MONTH</th>
              <th class="px-3 py-2 text-left">OUTLET</th>
              <th class="px-3 py-2 text-left">STATUS</th>
              <th class="px-3 py-2 text-left">CREATED BY</th>
              <th class="px-3 py-2 text-left">PRODUCT NAME</th>
              <th class="px-3 py-2 text-left">CATEGORY</th>
              <th class="px-3 py-2 text-left">PIC</th>
              <th class="px-3 py-2 text-left">DEV. DATE</th>
              <th class="px-3 py-2 text-left">PURPOSE</th>
              <th class="px-3 py-2 text-left">LAUNCH DATE</th>
              <th class="px-3 py-2 text-left">AREA / OUTLET</th>
              <th class="px-3 py-2 text-right">F&B COST</th>
              <th class="px-3 py-2 text-right">SELLING PRICE</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.rows" :key="`${row.no}-${row.report_number}-${row.product_name}`" class="border-b hover:bg-amber-50/40">
              <td class="px-3 py-2">{{ row.no }}</td>
              <td class="px-3 py-2 font-semibold">{{ row.report_number }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ formatMonth(row.report_month) }}</td>
              <td class="px-3 py-2">{{ row.outlet }}</td>
              <td class="px-3 py-2">{{ row.status_label || row.status }}</td>
              <td class="px-3 py-2">{{ row.created_by }}</td>
              <td class="px-3 py-2 font-medium">{{ row.product_name }}</td>
              <td class="px-3 py-2">{{ row.category }}</td>
              <td class="px-3 py-2">{{ row.pics }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.development_date) }}</td>
              <td class="px-3 py-2">{{ row.purpose_label || row.purpose }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.proposed_launch_date) }}</td>
              <td class="px-3 py-2">{{ row.launch_outlets }}</td>
              <td class="px-3 py-2 text-right">{{ formatCurrency(row.fb_cost) }}</td>
              <td class="px-3 py-2 text-right">{{ formatCurrency(row.selling_price) }}</td>
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

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  purposeOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

const now = new Date();
const filters = reactive({
  month_from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
  month_to: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
  outlet_id: '',
  status: '',
  purpose: '',
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

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value || 0));
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
    if (filters.outlet_id) params.outlet_id = filters.outlet_id;
    if (filters.status) params.status = filters.status;
    if (filters.purpose) params.purpose = filters.purpose;
    if (filters.search.trim()) params.search = filters.search.trim();

    const res = await axios.get('/api/report/npd-plan-report', { params });
    report.value = res.data;
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memuat laporan NPD.';
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
  if (filters.outlet_id) query.set('outlet_id', filters.outlet_id);
  if (filters.status) query.set('status', filters.status);
  if (filters.purpose) query.set('purpose', filters.purpose);
  if (filters.search.trim()) query.set('search', filters.search.trim());

  window.open(`/report/npd-plan-report/export?${query.toString()}`, '_blank');
}
</script>
