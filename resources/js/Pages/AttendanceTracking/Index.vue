<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import jsPDF from 'jspdf'
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
const exportingPdf = ref(false)

const bulan = ref(props.filters?.bulan || new Date().getMonth() + 1)
const tahun = ref(props.filters?.tahun || new Date().getFullYear())

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`
})

const hasData = computed(() => props.employee && props.summary)

const summaryCards = computed(() => {
  if (!props.summary) return []
  const s = props.summary
  return [
    { key: 'hadir', label: 'Hadir', value: s.present_days, unit: 'hari', icon: 'fa-check-circle', tone: 'emerald' },
    { key: 'telat', label: 'Terlambat', value: s.total_telat, unit: 'menit', icon: 'fa-clock', tone: 'amber' },
    { key: 'alpha', label: 'Alpha', value: s.alpa_days, unit: 'hari', icon: 'fa-times-circle', tone: 'rose' },
    { key: 'off', label: 'OFF', value: s.off_days, unit: 'hari', icon: 'fa-calendar-minus', tone: 'slate' },
    { key: 'lembur', label: 'Lembur', value: s.total_lembur, unit: 'jam', icon: 'fa-hourglass-half', tone: 'orange' },
    { key: 'ph', label: 'PH', value: s.ph_days, unit: 'hari', icon: 'fa-star', tone: 'sky' },
    { key: 'extra', label: 'Extra Off', value: s.extra_off_days, unit: 'hari', icon: 'fa-calendar-plus', tone: 'violet' },
    { key: 'kerja', label: 'Hari Kerja', value: s.hari_kerja, unit: 'hari', icon: 'fa-briefcase', tone: 'indigo' },
    { key: 'pct', label: 'Kehadiran', value: s.percentage, unit: '%', icon: 'fa-percentage', tone: 'teal' },
  ]
})

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

const chartBase = {
  theme: { mode: 'light' },
  chart: {
    toolbar: { show: false },
    fontFamily: 'inherit',
    foreColor: '#475569',
  },
}

const pieSeries = computed(() => props.outletStats.map((o) => o.scan_in))
const pieLabels = computed(() => props.outletStats.map((o) => o.nama_outlet))

const pieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: pieLabels.value,
  legend: { position: 'bottom', fontSize: '13px', labels: { colors: '#334155' } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '12px', fontWeight: 600, colors: ['#fff'] },
    formatter: (val) => `${Math.round(val)}%`,
  },
  tooltip: {
    theme: 'light',
    y: {
      formatter: (val, opts) => {
        const item = props.outletStats[opts.seriesIndex]
        return `${val} scan IN (${item?.scan_in_percentage ?? 0}%)`
      },
    },
  },
  colors: ['#4f46e5', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'],
}))

const hoursBarSeries = computed(() => [{
  name: 'Total Jam',
  data: props.outletStats.map((o) => o.total_hours),
}])

const hoursBarOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'bar' },
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: props.outletStats.map((o) => o.nama_outlet),
    labels: { style: { fontSize: '12px', colors: '#475569' } },
  },
  yaxis: { labels: { style: { colors: '#64748b' } } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
    formatter: (v) => `${v} jam`,
  },
  colors: ['#4f46e5'],
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: { theme: 'light' },
}))

const toneClasses = {
  emerald: 'bg-emerald-50 border-emerald-200 text-emerald-700',
  amber: 'bg-amber-50 border-amber-200 text-amber-700',
  rose: 'bg-rose-50 border-rose-200 text-rose-700',
  slate: 'bg-slate-50 border-slate-200 text-slate-700',
  orange: 'bg-orange-50 border-orange-200 text-orange-700',
  sky: 'bg-sky-50 border-sky-200 text-sky-700',
  violet: 'bg-violet-50 border-violet-200 text-violet-700',
  indigo: 'bg-indigo-50 border-indigo-200 text-indigo-700',
  teal: 'bg-teal-50 border-teal-200 text-teal-700',
}

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

function exportPdf() {
  if (!hasData.value) return
  exportingPdf.value = true

  try {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const margin = 14
    const pageW = doc.internal.pageSize.getWidth()
    const pageH = doc.internal.pageSize.getHeight()
    let y = 18

    const ensureSpace = (need = 10) => {
      if (y + need > pageH - 15) {
        doc.addPage()
        y = 18
      }
    }

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(16)
    doc.setTextColor(30, 41, 59)
    doc.text('Laporan Tracking Absensi', margin, y)
    y += 9

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(10)
    doc.setTextColor(71, 85, 105)
    doc.text(`Karyawan: ${props.employee.nama_lengkap}`, margin, y)
    y += 5
    doc.text(`NIK: ${props.employee.nik || '-'}`, margin, y)
    y += 5
    doc.text(`Jabatan: ${props.employee.nama_jabatan || '-'}`, margin, y)
    y += 5
    doc.text(`Outlet: ${props.employee.nama_outlet || '-'}`, margin, y)
    y += 5
    doc.text(`Periode: ${periodLabel.value}`, margin, y)
    y += 5
    doc.text(`Dicetak: ${new Date().toLocaleString('id-ID')}`, margin, y)
    y += 10

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(12)
    doc.setTextColor(30, 41, 59)
    doc.text('Ringkasan Kehadiran', margin, y)
    y += 7

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(10)
    const summaryLines = [
      ['Hadir', `${props.summary.present_days} hari`],
      ['Terlambat', `${props.summary.total_telat} menit`],
      ['Alpha', `${props.summary.alpa_days} hari`],
      ['OFF', `${props.summary.off_days} hari`],
      ['Lembur', `${props.summary.total_lembur} jam`],
      ['PH', `${props.summary.ph_days} hari`],
      ['Extra Off', `${props.summary.extra_off_days} hari`],
      ['Hari Kerja', `${props.summary.hari_kerja} hari`],
      ['Kehadiran', `${props.summary.percentage}%`],
      ...leaveSummaryItems.value.map((i) => [i.label, `${i.value} hari`]),
    ]

    summaryLines.forEach(([label, val]) => {
      ensureSpace(6)
      doc.setTextColor(71, 85, 105)
      doc.text(`${label}`, margin, y)
      doc.setTextColor(30, 41, 59)
      doc.text(val, margin + 55, y)
      y += 6
    })

    if (props.outletStats.length) {
      y += 6
      ensureSpace(14)
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(12)
      doc.setTextColor(30, 41, 59)
      doc.text('Detail per Outlet', margin, y)
      y += 8

      const colX = [margin, margin + 52, margin + 68, margin + 84, margin + 100, margin + 118, margin + 136]
      const headers = ['Outlet', 'IN', 'OUT', '%', 'Jam', 'Sesi', 'No CO']

      doc.setFillColor(241, 245, 249)
      doc.rect(margin, y - 4, pageW - margin * 2, 7, 'F')
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(8)
      doc.setTextColor(51, 65, 85)
      headers.forEach((h, i) => doc.text(h, colX[i], y))
      y += 7

      doc.setFont('helvetica', 'normal')
      props.outletStats.forEach((row, idx) => {
        ensureSpace(7)
        if (idx % 2 === 0) {
          doc.setFillColor(248, 250, 252)
          doc.rect(margin, y - 4, pageW - margin * 2, 6, 'F')
        }
        doc.setTextColor(51, 65, 85)
        const outletName = row.nama_outlet.length > 22 ? `${row.nama_outlet.slice(0, 22)}…` : row.nama_outlet
        doc.text(outletName, colX[0], y)
        doc.text(String(row.scan_in), colX[1], y)
        doc.text(String(row.scan_out), colX[2], y)
        doc.text(`${row.scan_in_percentage}%`, colX[3], y)
        doc.text(String(row.total_hours), colX[4], y)
        doc.text(String(row.sessions), colX[5], y)
        doc.text(String(row.no_checkout_sessions), colX[6], y)
        y += 6
      })
    }

    const safeName = (props.employee.nama_lengkap || 'karyawan').replace(/[^\w\s-]/g, '').replace(/\s+/g, '_')
    doc.save(`tracking-absensi_${safeName}_${bulan.value}-${tahun.value}.pdf`)
  } catch (e) {
    console.error(e)
    alert('Gagal membuat PDF. Silakan coba lagi.')
  } finally {
    exportingPdf.value = false
  }
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
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
          Tracking Absensi Karyawan
        </h2>
        <button
          v-if="hasData"
          type="button"
          :disabled="exportingPdf"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-lg shadow-sm hover:bg-rose-700 transition-colors disabled:opacity-60"
          @click="exportPdf"
        >
          <i :class="exportingPdf ? 'fas fa-spinner fa-spin' : 'fas fa-file-pdf'"></i>
          Export PDF
        </button>
      </div>
    </template>

    <div class="py-5 bg-slate-50 min-h-screen">
      <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
        <!-- Filter -->
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-filter text-indigo-500"></i>
            <h3 class="text-sm font-semibold text-slate-800">Filter Data</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 items-end">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Outlet</label>
              <select v-model="outletId" class="at-input">
                <option value="">Semua Outlet</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Divisi</label>
              <select v-model="divisionId" class="at-input">
                <option value="">Semua Divisi</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
              </select>
            </div>
            <div class="xl:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Karyawan</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employees"
                :loading="loadingEmployees"
                label="name"
                track-by="id"
                placeholder="Cari & pilih karyawan..."
                :disabled="loadingEmployees"
                class="at-multiselect"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Bulan</label>
              <select v-model="bulan" class="at-input">
                <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Tahun</label>
              <select v-model="tahun" class="at-input">
                <option v-for="y in tahunOptions" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
              type="button"
              :disabled="!selectedEmployee || isLoading"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm transition-all disabled:opacity-50"
              @click="applyFilter"
            >
              <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-search'"></i>
              Tampilkan
            </button>
            <span v-if="periodLabel" class="inline-flex items-center gap-1.5 text-xs text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
              <i class="fas fa-calendar-alt text-indigo-500"></i>
              Periode payroll: <strong class="text-slate-800">{{ periodLabel }}</strong>
            </span>
          </div>
        </section>

        <!-- Empty -->
        <section v-if="!hasData" class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-50 flex items-center justify-center">
            <i class="fas fa-user-clock text-2xl text-indigo-400"></i>
          </div>
          <p class="text-slate-600 font-medium">Belum ada data ditampilkan</p>
          <p class="text-sm text-slate-500 mt-1">Pilih karyawan dan periode, lalu klik <strong>Tampilkan</strong></p>
        </section>

        <template v-else>
          <!-- Employee -->
          <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-md">
                  <i class="fas fa-user text-white text-xl"></i>
                </div>
                <div>
                  <h3 class="text-xl font-bold text-slate-900">{{ employee.nama_lengkap }}</h3>
                  <p class="text-sm text-slate-600 mt-0.5">
                    <span class="font-medium">{{ employee.nik || '-' }}</span>
                    <span class="mx-2 text-slate-300">|</span>
                    {{ employee.nama_jabatan || '-' }}
                    <span class="mx-2 text-slate-300">|</span>
                    {{ employee.nama_outlet || '-' }}
                    <span v-if="employee.nama_divisi">
                      <span class="mx-2 text-slate-300">|</span>
                      {{ employee.nama_divisi }}
                    </span>
                  </p>
                </div>
              </div>
              <button
                type="button"
                :disabled="exportingPdf"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition-colors disabled:opacity-60 xl:hidden"
                @click="exportPdf"
              >
                <i :class="exportingPdf ? 'fas fa-spinner fa-spin' : 'fas fa-file-pdf'"></i>
                Export PDF
              </button>
            </div>
          </section>

          <!-- Summary -->
          <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-9 gap-3">
            <div
              v-for="card in summaryCards"
              :key="card.key"
              class="rounded-xl border p-4 transition-shadow hover:shadow-md"
              :class="toneClasses[card.tone]"
            >
              <div class="flex items-center gap-2 mb-2">
                <i :class="['fas', card.icon, 'text-sm opacity-80']"></i>
                <p class="text-xs font-semibold uppercase tracking-wide opacity-90">{{ card.label }}</p>
              </div>
              <p class="text-2xl font-bold leading-none">
                {{ card.value }}<span class="text-sm font-medium ml-1 opacity-70">{{ card.unit }}</span>
              </p>
            </div>
            <div
              v-for="item in leaveSummaryItems"
              :key="item.label"
              class="rounded-xl border p-4 bg-slate-50 border-slate-200 text-slate-700"
            >
              <p class="text-xs font-semibold uppercase tracking-wide opacity-80 capitalize mb-2">{{ item.label }}</p>
              <p class="text-2xl font-bold">{{ item.value }}<span class="text-sm font-medium ml-1 opacity-70">hari</span></p>
            </div>
          </section>

          <!-- Charts -->
          <section v-if="outletStats.length" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Distribusi Scan IN per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">Persentase kehadiran (scan masuk) di masing-masing outlet</p>
              <div class="mt-4 bg-slate-50 rounded-xl p-2">
                <apexchart type="pie" height="340" :options="pieOptions" :series="pieSeries" />
              </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Total Jam per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">Durasi kerja (IN→OUT, termasuk cross-day) per outlet</p>
              <div class="mt-4 bg-slate-50 rounded-xl p-2">
                <apexchart type="bar" height="340" :options="hoursBarOptions" :series="hoursBarSeries" />
              </div>
            </div>
          </section>

          <section v-else class="bg-white border border-slate-200 rounded-2xl shadow-sm p-10 text-center text-slate-500 text-sm">
            Tidak ada data scan absensi per outlet pada periode ini.
          </section>

          <!-- Table -->
          <section v-if="outletStats.length" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
              <h3 class="text-base font-semibold text-slate-900">Detail per Outlet</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                    <th class="px-5 py-3 text-left font-semibold">Outlet</th>
                    <th class="px-5 py-3 text-right font-semibold">Scan IN</th>
                    <th class="px-5 py-3 text-right font-semibold">Scan OUT</th>
                    <th class="px-5 py-3 text-right font-semibold">% IN</th>
                    <th class="px-5 py-3 text-right font-semibold">Total Jam</th>
                    <th class="px-5 py-3 text-right font-semibold">Sesi</th>
                    <th class="px-5 py-3 text-right font-semibold">Tanpa Checkout</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="(row, idx) in outletStats"
                    :key="row.id_outlet"
                    class="hover:bg-indigo-50/40 transition-colors"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'"
                  >
                    <td class="px-5 py-3.5 font-medium text-slate-900">{{ row.nama_outlet }}</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.scan_in }}</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.scan_out }}</td>
                    <td class="px-5 py-3.5 text-right font-semibold text-indigo-600">{{ row.scan_in_percentage }}%</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.total_hours }} jam</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.sessions }}</td>
                    <td class="px-5 py-3.5 text-right" :class="row.no_checkout_sessions > 0 ? 'text-rose-600 font-semibold' : 'text-slate-700'">
                      {{ row.no_checkout_sessions }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.at-input {
  @apply w-full rounded-lg border-slate-300 bg-white text-slate-800 text-sm shadow-sm
    focus:border-indigo-500 focus:ring-indigo-500 transition-colors;
}

:deep(.at-multiselect .multiselect__tags) {
  @apply rounded-lg border-slate-300 bg-white min-h-[42px] pt-2;
}

:deep(.at-multiselect .multiselect__input),
:deep(.at-multiselect .multiselect__single) {
  @apply text-sm text-slate-800 bg-white;
}

:deep(.at-multiselect .multiselect__content-wrapper) {
  @apply border-slate-200 shadow-lg rounded-lg;
}

:deep(.at-multiselect .multiselect__option--highlight) {
  @apply bg-indigo-600;
}

:deep(.at-multiselect .multiselect__option) {
  @apply text-sm text-slate-700;
}
</style>
