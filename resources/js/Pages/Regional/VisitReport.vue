<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  regionalUsers: { type: Array, default: () => [] },
  outletStats: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  selectedRegional: Object,
  includedRegionalUsers: { type: Array, default: () => [] },
  resolvedUserIds: { type: Array, default: () => [] },
  filters: Object,
  areas: { type: Array, default: () => [] },
  noRegionalUsers: { type: Boolean, default: false },
})

const userId = ref(props.filters?.user_id || '')
const area = ref(props.filters?.area || '')
const bulan = ref(props.filters?.bulan || new Date().getMonth() + 1)
const tahun = ref(props.filters?.tahun || new Date().getFullYear())
const isLoading = ref(false)

const showOutletModal = ref(false)
const outletModalLoading = ref(false)
const outletModalError = ref(null)
const outletModalData = ref(null)
const expandedDays = ref(new Set())

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const filterApplied = computed(() => !!(props.filters?.user_id || props.filters?.area))
const hasData = computed(() => props.outletStats.length > 0 && filterApplied.value)

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`
})

const chartBase = {
  theme: { mode: 'light' },
  chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#475569' },
}

const visitBarSeries = computed(() => [{
  name: 'Hari Kunjungan',
  data: props.outletStats.map((o) => o.visit_days),
}])

const visitBarOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'bar', selection: { enabled: true } },
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '70%', distributed: true } },
  colors: props.outletStats.map((o) => {
    if (o.visit_days === 0) return '#cbd5e1'
    if (o.visit_days <= 2) return '#f59e0b'
    if (o.visit_days <= 5) return '#3b82f6'
    return '#059669'
  }),
  xaxis: {
    categories: props.outletStats.map((o) => o.nama_outlet),
    labels: { style: { fontSize: '11px', colors: '#475569' } },
    title: { text: 'Jumlah hari kunjungan', style: { fontSize: '12px', color: '#64748b' } },
  },
  yaxis: { labels: { style: { fontSize: '11px', colors: '#64748b' }, maxWidth: 180 } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
    formatter: (v) => (v > 0 ? `${v} hari` : '0'),
  },
  legend: { show: false },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: {
    theme: 'light',
    y: {
      formatter: (val, opts) => {
        const item = props.outletStats[opts.dataPointIndex]
        if (!item) return `${val} hari`
        return `${val} hari kunjungan · ${item.scan_in_count} scan IN`
      },
    },
  },
}))

const pieSeries = computed(() => {
  const visited = props.summary.visited_outlets || 0
  const never = props.summary.never_visited_outlets || 0
  return [visited, never]
})

const pieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'donut' },
  labels: ['Sudah dikunjungi', 'Belum pernah'],
  colors: ['#059669', '#e2e8f0'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
  plotOptions: {
    pie: {
      donut: {
        size: '62%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Coverage',
            formatter: () => `${props.summary.visit_coverage_pct || 0}%`,
          },
        },
      },
    },
  },
}))

const modalHourlySeries = computed(() => {
  if (!outletModalData.value?.hourly_frequency) return [{ name: 'Scan IN', data: [] }]
  return [{
    name: 'Scan IN',
    data: outletModalData.value.hourly_frequency.data,
  }]
})

const modalHourlyOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'bar' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '75%' } },
  xaxis: {
    categories: outletModalData.value?.hourly_frequency?.labels || [],
    labels: {
      rotate: -45,
      style: { fontSize: '10px', colors: '#64748b' },
    },
    title: { text: 'Jam', style: { fontSize: '11px', color: '#64748b' } },
  },
  yaxis: {
    labels: { style: { colors: '#64748b' } },
    title: { text: 'Jumlah scan IN', style: { fontSize: '11px', color: '#64748b' } },
    forceNiceScale: true,
    min: 0,
  },
  colors: ['#4f46e5'],
  dataLabels: { enabled: false },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: { theme: 'light' },
}))

function onUserChange() {
  if (userId.value) area.value = ''
}

function applyFilter() {
  isLoading.value = true
  router.get(route('regional.visit-report.index'), {
    user_id: userId.value || undefined,
    area: area.value || undefined,
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
}

async function openOutletDetail(outlet) {
  if (!filterApplied.value || !outlet?.id_outlet) return

  showOutletModal.value = true
  outletModalLoading.value = true
  outletModalError.value = null
  outletModalData.value = null
  expandedDays.value = new Set()

  try {
    const res = await axios.get(route('api.regional-visit-report.outlet-detail'), {
      params: {
        outlet_id: outlet.id_outlet,
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
        user_id: props.filters.user_id || undefined,
        area: props.filters.area || undefined,
      },
    })
    if (res.data.success) {
      outletModalData.value = res.data
    } else {
      outletModalError.value = res.data.message || 'Gagal memuat detail'
    }
  } catch (e) {
    outletModalError.value = e.response?.data?.message || 'Terjadi kesalahan saat memuat detail'
  } finally {
    outletModalLoading.value = false
  }
}

function closeOutletModal() {
  showOutletModal.value = false
  outletModalData.value = null
  outletModalError.value = null
}

function onBarChartClick(_event, _chartContext, config) {
  if (config.dataPointIndex !== undefined && props.outletStats[config.dataPointIndex]) {
    openOutletDetail(props.outletStats[config.dataPointIndex])
  }
}

function toggleDayScans(tanggal) {
  const next = new Set(expandedDays.value)
  if (next.has(tanggal)) next.delete(tanggal)
  else next.add(tanggal)
  expandedDays.value = next
}

function frequencyLabel(freq) {
  if (freq === 'often') return 'Sering'
  if (freq === 'medium') return 'Cukup'
  if (freq === 'rare') return 'Jarang'
  return 'Belum pernah'
}

function frequencyClass(freq) {
  if (freq === 'often') return 'bg-emerald-100 text-emerald-700'
  if (freq === 'medium') return 'bg-blue-100 text-blue-700'
  if (freq === 'rare') return 'bg-amber-100 text-amber-700'
  return 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <AppLayout title="Rekap Kunjungan Outlet Regional">
    <div class="py-5 bg-slate-50 min-h-screen">
      <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
        <section class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-map-location-dot text-indigo-600"></i>
              Rekap Kunjungan Outlet — Regional
            </h1>
            <p class="text-sm text-slate-500 mt-1">
              Area menentukan karyawan regional (Regional Management). Rekap dihitung dari scan IN mereka ke outlet aktif (is_outlet=1).
            </p>
          </div>
          <a
            :href="route('regional.index')"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50"
          >
            <i class="fas fa-arrow-left"></i> Regional Management
          </a>
        </section>

        <!-- Filter -->
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 items-end">
            <div class="xl:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Karyawan Regional (opsional)</label>
              <select v-model="userId" class="rv-input" @change="onUserChange">
                <option value="">Semua karyawan pada area terpilih</option>
                <option v-for="u in regionalUsers" :key="u.id" :value="u.id">
                  {{ u.name }} — Area {{ u.area }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Area Regional</label>
              <select v-model="area" class="rv-input" :disabled="!!userId">
                <option value="">Pilih area</option>
                <option v-for="a in areas" :key="a" :value="a">{{ a }}</option>
              </select>
              <p v-if="!userId" class="text-[11px] text-slate-500 mt-1">Ambil semua user dengan area ini di Regional Management</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Bulan</label>
              <select v-model="bulan" class="rv-input">
                <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Tahun</label>
              <select v-model="tahun" class="rv-input">
                <option v-for="y in tahunOptions" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
              type="button"
              :disabled="isLoading || (!userId && !area)"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
              @click="applyFilter"
            >
              <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-search'"></i>
              Tampilkan
            </button>
            <span v-if="periodLabel" class="inline-flex items-center gap-1.5 text-xs text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
              <i class="fas fa-calendar-alt text-indigo-500"></i>
              Periode payroll: <strong class="text-slate-800">{{ periodLabel }}</strong>
            </span>
            <span v-if="selectedRegional" class="text-xs text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-full">
              {{ selectedRegional.name }} · Area {{ selectedRegional.area }}
            </span>
            <span v-else-if="filters?.area && includedRegionalUsers.length" class="text-xs text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-full">
              Area {{ filters.area }} · {{ includedRegionalUsers.length }} karyawan regional
            </span>
          </div>
          <div
            v-if="!selectedRegional && includedRegionalUsers.length"
            class="mt-3 flex flex-wrap gap-2"
          >
            <span
              v-for="u in includedRegionalUsers"
              :key="u.id"
              class="inline-flex items-center gap-1 text-xs text-slate-700 bg-slate-100 px-2.5 py-1 rounded-full"
            >
              <i class="fas fa-user text-slate-400 text-[10px]"></i>
              {{ u.name }}
            </span>
          </div>
        </section>

        <section v-if="!filterApplied && !userId && !area" class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center">
          <i class="fas fa-chart-bar text-4xl text-slate-300 mb-3"></i>
          <p class="text-slate-600 font-medium">Pilih area regional atau karyawan regional untuk melihat rekap kunjungan</p>
        </section>

        <section v-else-if="noRegionalUsers" class="bg-white border border-amber-200 rounded-2xl shadow-sm py-16 text-center">
          <i class="fas fa-user-slash text-4xl text-amber-400 mb-3"></i>
          <p class="text-slate-700 font-medium">Belum ada karyawan regional pada area ini</p>
          <p class="text-sm text-slate-500 mt-1">Assign dulu di Regional Management</p>
        </section>

        <template v-else-if="hasData">
          <!-- Summary -->
          <section class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
              <p class="text-xs text-slate-500 uppercase font-semibold">Total Outlet</p>
              <p class="text-2xl font-bold text-slate-900 mt-1">{{ summary.total_outlets }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
              <p class="text-xs text-emerald-700 uppercase font-semibold">Sudah Dikunjungi</p>
              <p class="text-2xl font-bold text-emerald-800 mt-1">{{ summary.visited_outlets }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
              <p class="text-xs text-amber-700 uppercase font-semibold">Belum Pernah</p>
              <p class="text-2xl font-bold text-amber-800 mt-1">{{ summary.never_visited_outlets }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
              <p class="text-xs text-indigo-700 uppercase font-semibold">Total Hari Kunjungan</p>
              <p class="text-2xl font-bold text-indigo-800 mt-1">{{ summary.total_visit_days }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
              <p class="text-xs text-blue-700 uppercase font-semibold">Coverage</p>
              <p class="text-2xl font-bold text-blue-800 mt-1">{{ summary.visit_coverage_pct }}%</p>
            </div>
          </section>

          <!-- Charts -->
          <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Frekuensi Kunjungan per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">
                <span class="inline-block w-3 h-3 rounded bg-emerald-500 mr-1"></span>Sering
                <span class="inline-block w-3 h-3 rounded bg-blue-500 mx-1"></span>Cukup
                <span class="inline-block w-3 h-3 rounded bg-amber-500 mx-1"></span>Jarang
                <span class="inline-block w-3 h-3 rounded bg-slate-300 mx-1"></span>Belum pernah
                <span class="text-indigo-600">· Klik bar untuk detail</span>
              </p>
              <div class="mt-4 rv-chart-clickable" :style="{ minHeight: Math.max(320, outletStats.length * 36) + 'px' }">
                <apexchart
                  type="bar"
                  :height="Math.max(320, outletStats.length * 36)"
                  :options="visitBarOptions"
                  :series="visitBarSeries"
                  @dataPointSelection="onBarChartClick"
                />
              </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Coverage Kunjungan</h3>
              <p class="text-xs text-slate-500 mt-1">Proporsi outlet yang sudah pernah dikunjungi</p>
              <div class="mt-6">
                <apexchart type="donut" height="300" :options="pieOptions" :series="pieSeries" />
              </div>
            </div>
          </section>

          <!-- Table -->
          <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
              <h3 class="text-base font-semibold text-slate-900">Detail Kunjungan per Outlet</h3>
              <p class="text-xs text-slate-500 mt-0.5">Klik baris untuk melihat detail per hari dan frekuensi jam</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                    <th class="px-5 py-3 text-left font-semibold">Outlet</th>
                    <th class="px-5 py-3 text-center font-semibold">Frekuensi</th>
                    <th class="px-5 py-3 text-right font-semibold">Hari Kunjungan</th>
                    <th class="px-5 py-3 text-right font-semibold">Scan IN</th>
                    <th class="px-5 py-3 text-left font-semibold">Kunjungan Terakhir</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="(row, idx) in outletStats"
                    :key="row.id_outlet"
                    class="cursor-pointer hover:bg-indigo-50/40 transition-colors"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'"
                    @click="openOutletDetail(row)"
                  >
                    <td class="px-5 py-3.5 font-medium text-slate-900">
                      <span class="text-indigo-600 hover:underline">{{ row.nama_outlet }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="frequencyClass(row.frequency)">
                        {{ frequencyLabel(row.frequency) }}
                      </span>
                    </td>
                    <td class="px-5 py-3.5 text-right font-semibold" :class="row.visit_days > 0 ? 'text-indigo-600' : 'text-slate-400'">
                      {{ row.visit_days }}
                    </td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.scan_in_count }}</td>
                    <td class="px-5 py-3.5 text-slate-600">{{ row.last_visit_label || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>

        <section v-else-if="filterApplied" class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center text-slate-500 text-sm">
          <i class="fas fa-store-slash text-3xl text-slate-300 mb-3"></i>
          <p class="font-medium text-slate-600">Belum ada kunjungan (scan IN) pada periode payroll ini</p>
          <p class="mt-1">Pastikan karyawan regional di atas sudah scan absensi di outlet dan memiliki PIN absensi.</p>
        </section>
      </div>
    </div>

    <!-- Modal Detail Outlet -->
    <Teleport to="body">
      <div v-if="showOutletModal" class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[1px]" @click="closeOutletModal"></div>

          <div class="relative z-10 w-full max-w-5xl bg-white rounded-2xl shadow-xl border border-slate-200 max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">
                  Detail Kunjungan — {{ outletModalData?.outlet?.nama_outlet || '...' }}
                </h3>
                <p v-if="periodLabel" class="text-sm text-slate-500 mt-1">{{ periodLabel }}</p>
                <p v-if="selectedRegional" class="text-xs text-slate-400 mt-0.5">{{ selectedRegional.name }} · Area {{ selectedRegional.area }}</p>
                <p v-else-if="filters?.area" class="text-xs text-slate-400 mt-0.5">Area {{ filters.area }} · {{ includedRegionalUsers.length }} karyawan regional</p>
              </div>
              <button
                type="button"
                class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                @click="closeOutletModal"
              >
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="px-6 py-4 flex-1 overflow-y-auto">
              <div v-if="outletModalLoading" class="text-center py-12 text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-3"></i>
                <p class="text-sm">Memuat detail kunjungan...</p>
              </div>

              <div v-else-if="outletModalError" class="text-center py-12">
                <i class="fas fa-exclamation-circle text-2xl text-rose-500 mb-3"></i>
                <p class="text-sm text-rose-600">{{ outletModalError }}</p>
              </div>

              <template v-else-if="outletModalData">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
                  <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                    <p class="text-xs text-indigo-600 font-medium">Hari Kunjungan</p>
                    <p class="text-xl font-bold text-indigo-800">{{ outletModalData.summary.visit_days }}</p>
                  </div>
                  <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-600 font-medium">Total Scan IN</p>
                    <p class="text-xl font-bold text-emerald-800">{{ outletModalData.summary.scan_in_count }}</p>
                  </div>
                  <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <p class="text-xs text-violet-600 font-medium">Karyawan Unik</p>
                    <p class="text-xl font-bold text-violet-800">{{ outletModalData.summary.unique_visitors }}</p>
                  </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
                  <h4 class="text-sm font-semibold text-slate-900">Frekuensi Kunjungan per Jam</h4>
                  <p class="text-xs text-slate-500 mt-0.5">Distribusi scan IN berdasarkan jam dalam sehari</p>
                  <div class="mt-3">
                    <apexchart type="bar" height="260" :options="modalHourlyOptions" :series="modalHourlySeries" />
                  </div>
                </div>

                <div v-if="outletModalData.daily_visits.length" class="overflow-x-auto rounded-xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <th class="px-4 py-2.5 text-left font-semibold w-8"></th>
                        <th class="px-4 py-2.5 text-left font-semibold">Hari</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Scan IN</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Karyawan</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <template v-for="day in outletModalData.daily_visits" :key="day.tanggal">
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                          <td class="px-4 py-3">
                            <button
                              v-if="day.scans?.length"
                              type="button"
                              class="text-slate-400 hover:text-indigo-600"
                              @click="toggleDayScans(day.tanggal)"
                            >
                              <i :class="['fas', expandedDays.has(day.tanggal) ? 'fa-chevron-down' : 'fa-chevron-right', 'text-xs']"></i>
                            </button>
                          </td>
                          <td class="px-4 py-3 font-medium text-slate-800">{{ day.hari }}</td>
                          <td class="px-4 py-3 text-slate-700">{{ day.tanggal_label }}</td>
                          <td class="px-4 py-3 text-right font-semibold text-indigo-600">{{ day.scan_in_count }}</td>
                          <td class="px-4 py-3 text-slate-700">
                            <span
                              v-for="(user, uIdx) in day.users"
                              :key="user.id"
                              class="inline-flex items-center text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full mr-1 mb-1"
                            >
                              {{ user.name }}<span v-if="uIdx < day.users.length - 1"></span>
                            </span>
                          </td>
                        </tr>
                        <tr v-if="expandedDays.has(day.tanggal) && day.scans?.length" class="bg-slate-50">
                          <td colspan="5" class="px-4 py-3">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Detail Scan IN</p>
                            <div class="space-y-1.5">
                              <div
                                v-for="(scan, scanIdx) in day.scans"
                                :key="scanIdx"
                                class="flex flex-wrap items-center gap-2 text-xs"
                              >
                                <span class="inline-flex items-center px-2 py-0.5 rounded font-semibold bg-emerald-100 text-emerald-700">IN</span>
                                <span class="text-slate-600">{{ scan.user_name }}</span>
                                <span class="font-mono font-medium text-slate-800">{{ scan.jam_label }}</span>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
                <p v-else class="text-center py-10 text-sm text-slate-500">Tidak ada kunjungan di outlet ini pada periode terpilih.</p>
              </template>
            </div>

            <div class="px-6 py-4 border-t border-slate-200 flex justify-end">
              <button
                type="button"
                class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition-colors"
                @click="closeOutletModal"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.rv-input {
  @apply w-full rounded-lg border-slate-300 bg-white text-slate-800 text-sm shadow-sm
    focus:border-indigo-500 focus:ring-indigo-500 transition-colors px-3 py-2.5;
}

:deep(.rv-chart-clickable .apexcharts-bar-area) {
  cursor: pointer;
}
</style>
