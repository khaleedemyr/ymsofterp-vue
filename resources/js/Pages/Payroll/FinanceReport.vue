<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  outlets: Array,
  months: Array,
  years: Array,
  paymentRows: Array,
  bpjsRows: Array,
  summary: Object,
  filter: Object,
  meta: Object,
});

const activeTab = ref('payment');
const loading = ref(false);
const exporting = ref(false);

const outletId = ref(props.filter?.outlet_id || '');
const month = ref(formatMonth(props.filter?.month) || formatMonth(new Date().getMonth() + 1));
const year = ref(props.filter?.year || new Date().getFullYear());

function formatMonth(value) {
  if (value === null || value === undefined || value === '') {
    return '';
  }
  return String(value).padStart(2, '0');
}

function formatCurrency(value) {
  const num = Number(value || 0);
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num);
}

const monthName = computed(() => {
  const found = props.months?.find((m) => m.id === month.value);
  return found?.name || month.value;
});

const canShowData = computed(() => props.meta?.has_generated && props.paymentRows?.length > 0);

function loadReport() {
  if (!outletId.value || !month.value || !year.value) {
    return;
  }

  loading.value = true;
  router.get('/payroll/finance-report', {
    outlet_id: outletId.value,
    month: month.value,
    year: year.value,
  }, {
    preserveState: true,
    onFinish: () => {
      loading.value = false;
    },
  });
}

function exportReport() {
  if (!outletId.value || !month.value || !year.value) {
    return;
  }

  exporting.value = true;
  const url = `/payroll/finance-report/export?outlet_id=${outletId.value}&month=${month.value}&year=${year.value}`;
  window.location.href = url;
  setTimeout(() => {
    exporting.value = false;
  }, 1500);
}
</script>

<template>
  <AppLayout title="Laporan Finance Payroll">
    <div class="w-full min-h-[60vh] flex flex-col py-4">
      <div v-if="loading" class="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-coins text-amber-500"></i>
          Laporan Finance Payroll
        </h1>
        <p class="text-sm text-gray-500">
          Data dari payroll yang sudah di-generate
        </p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="flex flex-wrap gap-4 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Outlet</label>
            <select v-model="outletId" class="form-input rounded-xl shadow-lg w-72">
              <option value="">Pilih Outlet</option>
              <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Bulan</label>
            <select v-model="month" class="form-input rounded-xl shadow-lg w-48">
              <option value="">Pilih Bulan</option>
              <option v-for="m in props.months" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Tahun</label>
            <select v-model="year" class="form-input rounded-xl shadow-lg w-32">
              <option value="">Pilih Tahun</option>
              <option v-for="y in props.years" :key="y.id" :value="y.id">{{ y.name }}</option>
            </select>
          </div>

          <button
            type="button"
            class="bg-gradient-to-br from-blue-500 to-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg hover:opacity-90 disabled:opacity-50"
            :disabled="!outletId || !month || !year || loading"
            @click="loadReport"
          >
            <i class="fa fa-search mr-2"></i> Lihat Data
          </button>

          <button
            type="button"
            class="bg-gradient-to-br from-green-500 to-green-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg hover:opacity-90 disabled:opacity-50"
            :disabled="!canShowData || exporting"
            @click="exportReport"
          >
            <i class="fa fa-file-excel mr-2"></i>
            {{ exporting ? 'Export...' : 'Export Excel' }}
          </button>
        </div>

        <div v-if="meta?.outlet_name && meta?.periode" class="mt-4 text-sm text-gray-600">
          <span class="font-semibold">{{ meta.outlet_name }}</span>
          <span class="mx-2">•</span>
          <span>Periode {{ meta.periode }}</span>
          <span class="mx-2">•</span>
          <span>{{ monthName }} {{ year }}</span>
        </div>
      </div>

      <div v-if="outletId && month && year && !meta?.has_generated" class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center text-amber-800">
        <i class="fa-solid fa-circle-info text-3xl mb-3"></i>
        <h3 class="text-lg font-semibold mb-2">Payroll belum di-generate</h3>
        <p class="text-sm">Generate payroll terlebih dahulu di menu Payroll Report untuk periode outlet dan bulan ini.</p>
      </div>

      <template v-else-if="canShowData">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Total Gaji Akhir Bulan</div>
            <div class="text-xl font-bold text-blue-700 mt-1">{{ formatCurrency(summary?.total_gaji_akhir_bulan) }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-indigo-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Total Gaji Tanggal 8</div>
            <div class="text-xl font-bold text-indigo-700 mt-1">{{ formatCurrency(summary?.total_gaji_tanggal_8) }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Total Gaji</div>
            <div class="text-xl font-bold text-green-700 mt-1">{{ formatCurrency(summary?.total_gaji) }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-teal-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Total BPJS Perusahaan</div>
            <div class="text-xl font-bold text-teal-700 mt-1">{{ formatCurrency(summary?.total_bpjs_perusahaan) }}</div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
          <div class="flex border-b border-gray-200">
            <button
              type="button"
              class="px-6 py-3 text-sm font-semibold transition-colors"
              :class="activeTab === 'payment' ? 'text-blue-700 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-gray-700'"
              @click="activeTab = 'payment'"
            >
              Pembayaran Gaji ({{ summary?.employee_count || 0 }})
            </button>
            <button
              type="button"
              class="px-6 py-3 text-sm font-semibold transition-colors"
              :class="activeTab === 'bpjs' ? 'text-teal-700 border-b-2 border-teal-600 bg-teal-50' : 'text-gray-500 hover:text-gray-700'"
              @click="activeTab = 'bpjs'"
            >
              BPJS Perusahaan ({{ summary?.bpjs_employee_count || 0 }})
            </button>
          </div>

          <div v-if="activeTab === 'payment'" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-slate-800 text-white">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">No</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">No. Rekening</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">Gaji Akhir Bulan</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">Gaji Tanggal 8</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Gaji</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <tr v-for="(row, index) in paymentRows" :key="row.user_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm text-gray-600">{{ index + 1 }}</td>
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ row.nama_lengkap }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ row.no_rekening }}</td>
                  <td class="px-4 py-3 text-sm text-right font-semibold text-blue-700">{{ formatCurrency(row.total_gaji_akhir_bulan) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-semibold text-indigo-700">{{ formatCurrency(row.total_gaji_tanggal_8) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ formatCurrency(row.total_gaji) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-slate-900 text-white">
                <tr>
                  <td colspan="3" class="px-4 py-3 text-sm font-bold text-right">TOTAL</td>
                  <td class="px-4 py-3 text-sm text-right font-bold">{{ formatCurrency(summary?.total_gaji_akhir_bulan) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold">{{ formatCurrency(summary?.total_gaji_tanggal_8) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold text-amber-300">{{ formatCurrency(summary?.total_gaji) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-teal-800 text-white">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">No</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase">NIK</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">Kesehatan</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">JHT</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">JP</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">JKK</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">JKM</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Perusahaan</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <tr v-if="!bpjsRows?.length">
                  <td colspan="9" class="px-4 py-8 text-center text-gray-500">Tidak ada data BPJS perusahaan untuk periode ini.</td>
                </tr>
                <tr v-for="(row, index) in bpjsRows" :key="row.user_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm text-gray-600">{{ index + 1 }}</td>
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ row.nama_lengkap }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ row.nik }}</td>
                  <td class="px-4 py-3 text-sm text-right text-teal-700">{{ formatCurrency(row.kes_perusahaan) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-teal-700">{{ formatCurrency(row.jht_perusahaan) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-teal-700">{{ formatCurrency(row.jp_perusahaan) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-teal-700">{{ formatCurrency(row.jkk_perusahaan) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-teal-700">{{ formatCurrency(row.jkm_perusahaan) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold text-teal-800">{{ formatCurrency(row.total_bpjs_perusahaan) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="bpjsRows?.length" class="bg-teal-900 text-white">
                <tr>
                  <td colspan="8" class="px-4 py-3 text-sm font-bold text-right">TOTAL BPJS PERUSAHAAN</td>
                  <td class="px-4 py-3 text-sm text-right font-bold text-amber-300">{{ formatCurrency(summary?.total_bpjs_perusahaan) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>

      <div v-else-if="outletId && month && year" class="bg-gray-50 border border-gray-200 rounded-2xl p-8 text-center text-gray-500">
        <i class="fa-solid fa-table text-4xl mb-3"></i>
        <p>Tidak ada data payroll untuk filter yang dipilih.</p>
      </div>

      <div v-else class="bg-gray-50 border border-gray-200 rounded-2xl p-8 text-center text-gray-500">
        <i class="fa-solid fa-filter text-4xl mb-3"></i>
        <p>Pilih outlet, bulan, dan tahun lalu klik Lihat Data.</p>
      </div>
    </div>
  </AppLayout>
</template>
