<script setup>
import { ref, computed, watch } from 'vue';
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
const expandedRows = ref(new Set());
const highlightedRowUserId = ref(null);

const paymentTableColCount = 11;

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

function formatDate(dateString) {
  if (!dateString) {
    return '';
  }

  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
}

function formatPaymentMethod(value) {
  return (value || 'transfer') === 'cash' ? 'Cash' : 'Transfer';
}

function isCashPayment(value) {
  return (value || 'transfer') === 'cash';
}

function toggleExpand(userId) {
  const next = new Set(expandedRows.value);
  if (next.has(userId)) {
    next.delete(userId);
  } else {
    next.add(userId);
  }
  expandedRows.value = next;
}

function isRowExpanded(userId) {
  return expandedRows.value.has(userId);
}

function handleRowClick(userId, event) {
  if (event.target.closest('button, select, a, textarea, input, label')) {
    return;
  }
  highlightedRowUserId.value = highlightedRowUserId.value === userId ? null : userId;
}

function financeRowClasses(userId, index) {
  const selected = highlightedRowUserId.value === userId;
  return [
    'finance-data-row cursor-pointer transition-[background-color,box-shadow] duration-150',
    selected ? 'finance-row-selected' : (index % 2 === 0 ? 'bg-white' : 'bg-gray-50'),
  ];
}

const slipSections = [
  { key: 'gajian_akhir_bulan', accent: 'blue' },
  { key: 'gajian_tanggal_8', accent: 'indigo' },
];

const monthName = computed(() => {
  const found = props.months?.find((m) => m.id === month.value);
  return found?.name || month.value;
});

const canShowData = computed(() => props.meta?.has_generated && props.paymentRows?.length > 0);

watch(activeTab, () => {
  highlightedRowUserId.value = null;
});

function loadReport() {
  if (!outletId.value || !month.value || !year.value) {
    return;
  }

  loading.value = true;
  expandedRows.value = new Set();
  highlightedRowUserId.value = null;
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
  <AppLayout title="Laporan Payroll">
    <div class="w-full min-h-[60vh] flex flex-col py-4">
      <div v-if="loading" class="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-coins text-amber-500"></i>
          Laporan Payroll
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

          <p class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 bg-gray-50">
            <i class="fa-solid fa-hand-pointer mr-1"></i>
            Hover baris untuk highlight sementara. Klik baris untuk pin highlight (klik lagi baris yang sama untuk lepas, atau klik baris lain).
          </p>

          <div v-if="activeTab === 'payment'" class="finance-table-scroll">
            <table class="min-w-full divide-y divide-gray-200 finance-sticky-table">
              <thead class="finance-sticky-head bg-slate-800 text-white">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase w-12 finance-sticky-th">No</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase w-12 finance-sticky-th"></th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Jabatan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Divisi</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Level</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Join Date</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">Nama Rekening</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th">No. Rekening</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase finance-sticky-th">Metode Bayar</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th">Total Gaji</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <template v-for="(row, index) in paymentRows" :key="row.user_id">
                  <tr
                    :class="financeRowClasses(row.user_id, index)"
                    @click="handleRowClick(row.user_id, $event)"
                  >
                    <td class="px-4 py-3 text-sm text-gray-600">{{ index + 1 }}</td>
                    <td class="px-2 py-3 text-center">
                      <button
                        type="button"
                        class="w-7 h-7 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 flex items-center justify-center transition-all duration-200 mx-auto"
                        :title="isRowExpanded(row.user_id) ? 'Sembunyikan rincian slip gaji' : 'Lihat rincian slip gaji'"
                        @click.stop="toggleExpand(row.user_id)"
                      >
                        <i :class="isRowExpanded(row.user_id) ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
                      </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium">{{ row.nama_lengkap }}</span>
                        <span
                          v-if="row.is_mutated_employee"
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800"
                        >
                          Mutasi
                        </span>
                        <span
                          v-if="row.resignation_date"
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                        >
                          Resign
                        </span>
                      </div>
                      <div
                        v-if="row.is_mutated_employee && row.mutation_effective_date"
                        class="text-xs text-purple-600 mt-1 font-medium"
                      >
                        Mutasi: {{ formatDate(row.mutation_effective_date) }} dari {{ row.mutation_outlet_from }} → {{ row.mutation_outlet_to }}
                      </div>
                      <div v-if="row.resignation_date" class="text-xs text-red-600 mt-1 font-medium">
                        Resign: {{ formatDate(row.resignation_date) }}
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.jabatan }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.divisi }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.level }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(row.tanggal_masuk) || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.nama_rekening }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ row.no_rekening }}</td>
                    <td class="px-4 py-3 text-sm text-center">
                      <span
                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border"
                        :class="isCashPayment(row.payment_method)
                          ? 'bg-purple-100 text-purple-800 border-purple-300'
                          : 'bg-blue-100 text-blue-800 border-blue-300'"
                      >
                        {{ formatPaymentMethod(row.payment_method) }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ formatCurrency(row.total_gaji) }}</td>
                  </tr>
                  <tr
                    v-if="isRowExpanded(row.user_id)"
                    :class="highlightedRowUserId === row.user_id ? 'finance-row-selected-detail border-l-4 border-amber-500' : 'bg-slate-50 border-l-4 border-blue-400'"
                  >
                    <td :colspan="paymentTableColCount" class="px-4 py-4">
                      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div
                          v-for="sectionMeta in slipSections"
                          :key="sectionMeta.key"
                          class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm"
                        >
                          <div
                            class="px-4 py-2.5 text-sm font-bold text-white"
                            :class="sectionMeta.accent === 'blue' ? 'bg-blue-700' : 'bg-indigo-700'"
                          >
                            {{ row.slip_breakdown?.[sectionMeta.key]?.title }}
                          </div>

                          <div class="p-4 space-y-4">
                            <div>
                              <div class="text-xs font-bold uppercase tracking-wide text-green-700 bg-green-50 px-2 py-1 rounded mb-2">
                                Pendapatan
                              </div>
                              <table class="min-w-full text-sm">
                                <tbody>
                                  <tr
                                    v-for="(line, lineIndex) in row.slip_breakdown?.[sectionMeta.key]?.earnings || []"
                                    :key="`${sectionMeta.key}-earn-${lineIndex}`"
                                    class="border-b border-gray-100 last:border-0"
                                  >
                                    <td class="py-2 pr-3 text-gray-800">{{ line.label }}</td>
                                    <td class="py-2 pr-3 text-gray-500 text-xs whitespace-nowrap">{{ line.qty || '-' }}</td>
                                    <td class="py-2 text-right font-semibold text-green-700 whitespace-nowrap">{{ formatCurrency(line.amount) }}</td>
                                  </tr>
                                  <tr v-if="!(row.slip_breakdown?.[sectionMeta.key]?.earnings || []).length">
                                    <td colspan="3" class="py-2 text-gray-400 italic">Tidak ada pendapatan</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>

                            <div>
                              <div class="text-xs font-bold uppercase tracking-wide text-red-700 bg-red-50 px-2 py-1 rounded mb-2">
                                Potongan
                              </div>
                              <table class="min-w-full text-sm">
                                <tbody>
                                  <tr
                                    v-for="(line, lineIndex) in row.slip_breakdown?.[sectionMeta.key]?.deductions || []"
                                    :key="`${sectionMeta.key}-ded-${lineIndex}`"
                                    class="border-b border-gray-100 last:border-0"
                                  >
                                    <td class="py-2 pr-3 text-gray-800">
                                      <div>{{ line.label }}</div>
                                      <div v-if="line.note" class="text-xs text-gray-500 italic mt-0.5">{{ line.note }}</div>
                                    </td>
                                    <td class="py-2 pr-3 text-gray-500 text-xs whitespace-nowrap">{{ line.qty || '-' }}</td>
                                    <td class="py-2 text-right font-semibold text-red-700 whitespace-nowrap">{{ formatCurrency(line.amount) }}</td>
                                  </tr>
                                  <tr v-if="!(row.slip_breakdown?.[sectionMeta.key]?.deductions || []).length">
                                    <td colspan="3" class="py-2 text-gray-400 italic">Tidak ada potongan</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>

                            <div
                              class="flex items-center justify-between rounded-lg px-4 py-3 font-bold"
                              :class="sectionMeta.accent === 'blue' ? 'bg-blue-50 text-blue-800' : 'bg-indigo-50 text-indigo-800'"
                            >
                              <span>Total Gaji Bersih</span>
                              <span>{{ formatCurrency(row.slip_breakdown?.[sectionMeta.key]?.total) }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot class="bg-slate-900 text-white">
                <tr>
                  <td :colspan="paymentTableColCount - 1" class="px-4 py-3 text-sm font-bold text-right">TOTAL</td>
                  <td class="px-4 py-3 text-sm text-right font-bold text-amber-300">{{ formatCurrency(summary?.total_gaji) }}</td>
                </tr>
                <tr class="bg-slate-800">
                  <td :colspan="paymentTableColCount" class="px-4 py-3">
                    <div class="flex flex-wrap justify-end gap-6 text-sm">
                      <span>
                        <span class="text-slate-400">Gaji Akhir Bulan:</span>
                        <span class="font-bold text-blue-300 ml-2">{{ formatCurrency(summary?.total_gaji_akhir_bulan) }}</span>
                      </span>
                      <span>
                        <span class="text-slate-400">Gaji Tanggal 8:</span>
                        <span class="font-bold text-indigo-300 ml-2">{{ formatCurrency(summary?.total_gaji_tanggal_8) }}</span>
                      </span>
                      <span>
                        <span class="text-slate-400">Total Gaji:</span>
                        <span class="font-bold text-amber-300 ml-2">{{ formatCurrency(summary?.total_gaji) }}</span>
                      </span>
                    </div>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div v-else class="finance-table-scroll">
            <table class="min-w-full divide-y divide-gray-200 finance-sticky-table">
              <thead class="finance-sticky-head bg-teal-800 text-white">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">No</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">NIK</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Jabatan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Divisi</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Level</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Join Date</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal whitespace-nowrap">No. BPJS Kesehatan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal whitespace-nowrap">No. BPJS Ketenagakerjaan</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Kesehatan</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">JHT</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">JP</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">JKK</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">JKM</th>
                  <th class="px-4 py-3 text-right text-xs font-bold uppercase finance-sticky-th finance-sticky-th-teal">Total Perusahaan</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <tr v-if="!bpjsRows?.length">
                  <td colspan="15" class="px-4 py-8 text-center text-gray-500">Tidak ada data BPJS perusahaan untuk periode ini.</td>
                </tr>
                <tr
                  v-for="(row, index) in bpjsRows"
                  :key="row.user_id"
                  :class="financeRowClasses(row.user_id, index)"
                  @click="handleRowClick(row.user_id, $event)"
                >
                  <td class="px-4 py-3 text-sm text-gray-600">{{ index + 1 }}</td>
                  <td class="px-4 py-3 text-sm text-gray-900">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-medium">{{ row.nama_lengkap }}</span>
                      <span
                        v-if="row.is_mutated_employee"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800"
                      >
                        Mutasi
                      </span>
                      <span
                        v-if="row.resignation_date"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                      >
                        Resign
                      </span>
                    </div>
                    <div
                      v-if="row.is_mutated_employee && row.mutation_effective_date"
                      class="text-xs text-purple-600 mt-1 font-medium"
                    >
                      Mutasi: {{ formatDate(row.mutation_effective_date) }} dari {{ row.mutation_outlet_from }} → {{ row.mutation_outlet_to }}
                    </div>
                    <div v-if="row.resignation_date" class="text-xs text-red-600 mt-1 font-medium">
                      Resign: {{ formatDate(row.resignation_date) }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ row.nik }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ row.jabatan }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ row.divisi }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ row.level }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(row.tanggal_masuk) || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700 font-mono whitespace-nowrap">{{ row.bpjs_health_number || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700 font-mono whitespace-nowrap">{{ row.bpjs_employment_number || '-' }}</td>
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
                  <td colspan="14" class="px-4 py-3 text-sm font-bold text-right">TOTAL BPJS PERUSAHAAN</td>
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

<style scoped>
.finance-table-scroll {
  max-height: calc(100vh - 320px);
  overflow: auto;
  scroll-behavior: smooth;
}

.finance-sticky-table {
  border-collapse: separate;
  border-spacing: 0;
}

.finance-sticky-th {
  position: sticky;
  top: 0;
  z-index: 20;
  background-color: #1e293b;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.12);
}

.finance-sticky-th-teal {
  background-color: #115e59;
}

.finance-data-row:hover:not(.finance-row-selected) {
  background-color: #dbeafe !important;
  box-shadow: inset 4px 0 0 0 #3b82f6;
}

.finance-row-selected {
  background-color: #fef3c7 !important;
  box-shadow: inset 4px 0 0 0 #f59e0b;
}

.finance-row-selected:hover {
  background-color: #fde68a !important;
}

.finance-row-selected-detail {
  background-color: #fffbeb !important;
}

.finance-table-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.finance-table-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
</style>
