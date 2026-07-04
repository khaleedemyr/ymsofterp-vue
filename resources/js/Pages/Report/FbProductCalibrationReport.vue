<template>
  <AppLayout>
    <div class="max-w-[1600px] mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-solid fa-clipboard-list text-violet-600"></i>
        F&B Product Calibration Report
      </h1>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal From</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal To</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="filters.outlet_id"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee Name</label>
            <input
              v-model="filters.employee_search"
              type="text"
              placeholder="Cari nama karyawan..."
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
            <select
              v-model="filters.mode"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            >
              <option v-for="opt in modeOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-2 md:col-span-2">
            <button
              type="button"
              @click="fetchReport"
              :disabled="loading"
              class="px-6 py-2.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
            >
              {{ loading ? 'Memuat...' : 'Tampilkan' }}
            </button>
            <button
              type="button"
              @click="exportExcel"
              :disabled="loading || !filters.date_from || !filters.date_to"
              class="px-6 py-2.5 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
            >
              Export Excel
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-16 text-gray-500">Memuat data...</div>

      <div v-else-if="!showReport" class="text-center py-16 text-gray-400 bg-white rounded-xl shadow">
        Pilih rentang tanggal lalu klik <strong>Tampilkan</strong> untuk melihat report calibration.
      </div>

      <div v-else-if="report.rows.length === 0" class="text-center py-16 text-gray-500 bg-white rounded-xl shadow">
        Tidak ada data calibration completed pada filter yang dipilih.
      </div>

      <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
        <div class="px-6 py-3 border-b text-sm text-gray-600">
          Total baris: <strong>{{ report.total }}</strong>
        </div>
        <table class="fbc-report-table min-w-[1500px] w-full text-xs border-collapse">
          <thead>
            <tr>
              <th rowspan="3" class="fbc-th-fixed">NO</th>
              <th rowspan="3" class="fbc-th-fixed fbc-th-product-name">PRODUCT NAME</th>
              <th rowspan="3" class="fbc-th-fixed">CATEGORY</th>
              <th rowspan="3" class="fbc-th-fixed">CALIBRATION DATE</th>
              <th rowspan="3" class="fbc-th-fixed">EMPLOYEE NAME</th>
              <th rowspan="3" class="fbc-th-fixed">OUTLET</th>
              <th rowspan="3" class="fbc-th-fixed">CONDUCTED BY</th>
              <th v-if="showModeColumn" rowspan="3" class="fbc-th-fixed">MODE</th>
              <th :colspan="activeParameterOptions.length * 2" class="fbc-th-group">
                CALIBRATION PARAMETER
              </th>
            </tr>
            <tr>
              <th
                v-for="param in activeParameterOptions"
                :key="param.code"
                colspan="2"
                class="fbc-th-param"
              >
                {{ param.label.toUpperCase() }}
              </th>
            </tr>
            <tr>
              <template v-for="param in activeParameterOptions" :key="`${param.code}-sub`">
                <th class="fbc-th-choice">C</th>
                <th class="fbc-th-choice">NC</th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.rows" :key="`${row.no}-${row.employee_name}-${row.product_name}`">
              <td class="fbc-td-fixed text-center">{{ row.no }}</td>
              <td class="fbc-td-fixed">
                <div class="font-semibold text-gray-900">{{ row.product_name }}</div>
              </td>
              <td class="fbc-td-fixed">{{ row.category }}</td>
              <td class="fbc-td-fixed whitespace-nowrap">{{ formatDate(row.calibration_date) }}</td>
              <td class="fbc-td-fixed">{{ row.employee_name }}</td>
              <td class="fbc-td-fixed">{{ row.outlet }}</td>
              <td class="fbc-td-fixed">{{ row.conducted_by }}</td>
              <td v-if="showModeColumn" class="fbc-td-fixed">{{ row.mode_label || row.mode }}</td>
              <template v-for="param in activeParameterOptions" :key="`${row.no}-${param.code}`">
                <td class="fbc-td-choice">
                  <span v-if="row.parameters[param.code] === 'C'" class="font-bold text-green-700 text-sm">✓</span>
                </td>
                <td class="fbc-td-choice">
                  <span v-if="row.parameters[param.code] === 'NC'" class="font-bold text-red-700 text-sm">✓</span>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  parameterOptions: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  modeOptions: { type: Array, default: () => [] },
});

const filters = reactive({
  date_from: '',
  date_to: '',
  outlet_id: '',
  employee_search: '',
  mode: '',
});

const loading = ref(false);
const showReport = ref(false);
const report = ref({ rows: [], total: 0, parameter_options: [], parameter_codes: [] });

const activeParameterOptions = computed(() => {
  if (report.value.parameter_options?.length) {
    return report.value.parameter_options;
  }
  return props.parameterOptions;
});

const showModeColumn = computed(() => !filters.mode);

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function fetchReport() {
  if (!filters.date_from || !filters.date_to) {
    Swal.fire({ icon: 'warning', title: 'Tanggal wajib diisi', text: 'Pilih Tanggal From dan Tanggal To.' });
    return;
  }

  loading.value = true;
  showReport.value = false;
  try {
    const params = {
      date_from: filters.date_from,
      date_to: filters.date_to,
    };
    if (filters.outlet_id) params.outlet_id = filters.outlet_id;
    if (filters.employee_search.trim()) params.employee_search = filters.employee_search.trim();
    if (filters.mode) params.mode = filters.mode;

    const res = await axios.get('/api/report/fb-product-calibration', { params });
    report.value = res.data;
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memuat report calibration.';
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  } finally {
    loading.value = false;
  }
}

function exportExcel() {
  if (!filters.date_from || !filters.date_to) {
    Swal.fire({ icon: 'warning', title: 'Tanggal wajib diisi', text: 'Pilih Tanggal From dan Tanggal To.' });
    return;
  }

  const query = new URLSearchParams({
    date_from: filters.date_from,
    date_to: filters.date_to,
  });
  if (filters.outlet_id) query.set('outlet_id', filters.outlet_id);
  if (filters.employee_search.trim()) query.set('employee_search', filters.employee_search.trim());
  if (filters.mode) query.set('mode', filters.mode);

  window.open(`/report/fb-product-calibration/export?${query.toString()}`, '_blank');
}
</script>

<style scoped>
.fbc-report-table {
  border: 1px solid #e5e7eb;
}

.fbc-th-fixed,
.fbc-th-group,
.fbc-th-param,
.fbc-th-choice {
  background: #1f2937;
  color: #fff;
  font-weight: 700;
  text-align: center;
  border: 1px solid #374151;
  padding: 8px 10px;
  vertical-align: middle;
}

.fbc-th-product-name {
  min-width: 180px;
}

.fbc-th-group {
  letter-spacing: 0.04em;
}

.fbc-th-param {
  font-size: 10px;
  line-height: 1.2;
}

.fbc-th-choice {
  width: 36px;
  min-width: 36px;
}

.fbc-td-fixed,
.fbc-td-choice {
  border: 1px solid #e5e7eb;
  padding: 8px 10px;
  vertical-align: middle;
}

.fbc-td-choice {
  text-align: center;
  width: 36px;
}

tbody tr:nth-child(even) {
  background: #f9fafb;
}
</style>
