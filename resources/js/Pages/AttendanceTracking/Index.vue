<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  employee: Object,
  summary: Object,
  outletStats: { type: Array, default: () => [] },
  outlets: Array,
  divisions: Array,
  filters: Object,
})

const outletId = ref('')
const divisionId = ref('')
const selectedEmployee = ref(null)
const employees = ref([])
const loadingEmployees = ref(false)
const isLoading = ref(false)

const bulan = ref(props.filters?.bulan || new Date().getMonth() + 1)
const tahun = ref(props.filters?.tahun || new Date().getFullYear())

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`
})

const hasData = computed(() => props.employee && props.summary)

const leaveSummaryItems = computed(() => {
  if (!props.summary) return []
  const skip = new Set([
    'hari_kerja', 'present_days', 'off_days', 'alpa_days', 'ph_days', 'ph_bonus',
    'extra_off_days', 'total_telat', 'total_lembur', 'total_lembur_regular',
    'extra_off_overtime_hours', 'percentage', 'total_shift_days',
  ])
  return Object.entries(props.summary)
    .filter(([key, val]) => key.endsWith('_days') && !skip.has(key) && val > 0)
    .map(([key, val]) => ({
      label: key.replace(/_days$/, '').replace(/_/g, ' '),
      value: val,
    }))
})

const pieSeries = computed(() => props.outletStats.map((o) => o.scan_in))
const pieLabels = computed(() => props.outletStats.map((o) => o.nama_outlet))

const pieOptions = computed(() => ({
  chart: { type: 'pie', toolbar: { show: false } },
  labels: pieLabels.value,
  legend: { position: 'bottom', fontSize: '12px' },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${Math.round(val)}%`,
  },
  tooltip: {
    y: {
      formatter: (val, opts) => {
        const item = props.outletStats[opts.seriesIndex]
        return `${val} scan IN (${item?.scan_in_percentage ?? 0}%)`
      },
    },
  },
  colors: ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'],
}))

const hoursBarSeries = computed(() => [{
  name: 'Total Jam',
  data: props.outletStats.map((o) => o.total_hours),
}])

const hoursBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true } },
  xaxis: {
    categories: props.outletStats.map((o) => o.nama_outlet),
    labels: { style: { fontSize: '11px' } },
  },
  dataLabels: { enabled: true, formatter: (v) => `${v} jam` },
  colors: ['#6366f1'],
  grid: { borderColor: '#e2e8f0' },
}))

async function fetchEmployees() {
  loadingEmployees.value = true
  try {
    const params = {}
    if (outletId.value) params.outlet_id = outletId.value
    if (divisionId.value) params.division_id = divisionId.value
    const res = await axios.get('/api/attendance-report/employees', { params })
    employees.value = res.data
    if (props.filters?.user_id) {
      const found = employees.value.find((e) => e.id == props.filters.user_id)
      if (found) selectedEmployee.value = found
    }
  } catch (e) {
    console.error(e)
    employees.value = []
  } finally {
    loadingEmployees.value = false
  }
}

function applyFilter() {
  if (!selectedEmployee.value) return
  isLoading.value = true
  router.get(route('attendance-tracking.index'), {
    user_id: selectedEmployee.value.id,
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
}

watch([outletId, divisionId], async () => {
  await fetchEmployees()
  selectedEmployee.value = null
})

onMounted(async () => {
  if (props.filters?.outlet_id) outletId.value = props.filters.outlet_id
  if (props.filters?.division_id) divisionId.value = props.filters.division_id
  await fetchEmployees()
})
</script>

<template>
  <AppLayout title="Attendance Tracking">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Tracking Absensi Karyawan
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Filter -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Filter</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Outlet</label>
              <select v-model="outletId" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Outlet</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Divisi</label>
              <select v-model="divisionId" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Divisi</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
              </select>
            </div>
            <div class="lg:col-span-2">
              <label class="block text-xs text-gray-500 mb-1">Karyawan</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employees"
                :loading="loadingEmployees"
                label="name"
                track-by="id"
                placeholder="Pilih karyawan..."
                :disabled="loadingEmployees"
              />
            </div>
            <div class="flex gap-2">
              <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">Bulan</label>
                <select v-model="bulan" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                  <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">Tahun</label>
                <select v-model="tahun" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                  <option v-for="y in tahunOptions" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="mt-4 flex items-center gap-3">
            <button
              type="button"
              :disabled="!selectedEmployee || isLoading"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50"
              @click="applyFilter"
            >
              <i v-if="isLoading" class="fas fa-spinner fa-spin mr-1"></i>
              Tampilkan
            </button>
            <span v-if="periodLabel" class="text-xs text-gray-500">
              Periode payroll: {{ periodLabel }}
            </span>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!hasData" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center text-gray-500">
          <i class="fas fa-user-clock text-4xl mb-3 text-gray-300"></i>
          <p>Pilih karyawan dan periode, lalu klik <strong>Tampilkan</strong>.</p>
        </div>

        <template v-else>
          <!-- Employee info -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <div class="flex flex-wrap items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                <i class="fas fa-user text-indigo-600 text-xl"></i>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ employee.nama_lengkap }}</h3>
                <p class="text-sm text-gray-500">
                  {{ employee.nik || '-' }} · {{ employee.nama_jabatan || '-' }} · {{ employee.nama_outlet || '-' }}
                  <span v-if="employee.nama_divisi"> · {{ employee.nama_divisi }}</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Summary cards -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Hadir</p>
              <p class="text-2xl font-bold text-green-600">{{ summary.present_days }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Terlambat</p>
              <p class="text-2xl font-bold text-yellow-600">{{ summary.total_telat }} <span class="text-sm font-normal text-gray-400">menit</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Alpha</p>
              <p class="text-2xl font-bold text-red-600">{{ summary.alpa_days }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">OFF</p>
              <p class="text-2xl font-bold text-gray-600">{{ summary.off_days }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Lembur</p>
              <p class="text-2xl font-bold text-orange-600">{{ summary.total_lembur }} <span class="text-sm font-normal text-gray-400">jam</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">PH</p>
              <p class="text-2xl font-bold text-blue-600">{{ summary.ph_days }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Extra Off (cuti)</p>
              <p class="text-2xl font-bold text-purple-600">{{ summary.extra_off_days }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Hari Kerja</p>
              <p class="text-2xl font-bold text-indigo-600">{{ summary.hari_kerja }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <p class="text-xs text-gray-500">Kehadiran</p>
              <p class="text-2xl font-bold text-teal-600">{{ summary.percentage }}%</p>
            </div>
            <div
              v-for="item in leaveSummaryItems"
              :key="item.label"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4"
            >
              <p class="text-xs text-gray-500 capitalize">{{ item.label }}</p>
              <p class="text-2xl font-bold text-slate-600">{{ item.value }} <span class="text-sm font-normal text-gray-400">hari</span></p>
            </div>
          </div>

          <!-- Charts -->
          <div v-if="outletStats.length" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Distribusi Scan IN per Outlet</h3>
              <p class="text-xs text-gray-500 mt-1">Persentase kehadiran (scan masuk) di masing-masing outlet</p>
              <div class="mt-4">
                <apexchart type="pie" height="320" :options="pieOptions" :series="pieSeries" />
              </div>
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Total Jam per Outlet</h3>
              <p class="text-xs text-gray-500 mt-1">Durasi kerja (IN→OUT, termasuk cross-day) per outlet</p>
              <div class="mt-4">
                <apexchart type="bar" height="320" :options="hoursBarOptions" :series="hoursBarSeries" />
              </div>
            </section>
          </div>

          <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-gray-500 text-sm">
            Tidak ada data scan absensi per outlet pada periode ini.
          </div>

          <!-- Outlet detail table -->
          <div v-if="outletStats.length" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Detail per Outlet</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Outlet</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Scan IN</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Scan OUT</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">% IN</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Total Jam</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Sesi</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Tanpa Checkout</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                  <tr v-for="row in outletStats" :key="row.id_outlet" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ row.nama_outlet }}</td>
                    <td class="px-4 py-3 text-right">{{ row.scan_in }}</td>
                    <td class="px-4 py-3 text-right">{{ row.scan_out }}</td>
                    <td class="px-4 py-3 text-right text-indigo-600 font-medium">{{ row.scan_in_percentage }}%</td>
                    <td class="px-4 py-3 text-right">{{ row.total_hours }} jam</td>
                    <td class="px-4 py-3 text-right">{{ row.sessions }}</td>
                    <td class="px-4 py-3 text-right" :class="row.no_checkout_sessions > 0 ? 'text-red-600 font-medium' : ''">
                      {{ row.no_checkout_sessions }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
