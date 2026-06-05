<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { REGIONAL_DEPARTMENTS } from './regionalOutletUtils'

const props = defineProps({
  regionalUsers: { type: Array, default: () => [] },
  outletStats: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  selectedRegional: Object,
  filters: Object,
  areas: { type: Array, default: () => [] },
})

const userId = ref(props.filters?.user_id || '')
const area = ref(props.filters?.area || '')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const isLoading = ref(false)

const hasData = computed(() => props.outletStats.length > 0 && (props.filters?.user_id || props.filters?.area))

const periodLabel = computed(() => {
  if (!startDate.value || !endDate.value) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${fmt(startDate.value)} – ${fmt(endDate.value)}`
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
  chart: { ...chartBase.chart, type: 'bar' },
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

function onUserChange() {
  if (userId.value) area.value = ''
}

function applyFilter() {
  isLoading.value = true
  router.get(route('regional.visit-report.index'), {
    user_id: userId.value || undefined,
    area: area.value || undefined,
    start_date: startDate.value,
    end_date: endDate.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
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
              Berdasarkan scan IN absensi regional ke outlet area Bar / Kitchen / Service
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
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Regional (Karyawan)</label>
              <select v-model="userId" class="rv-input" @change="onUserChange">
                <option value="">Semua per area</option>
                <option v-for="u in regionalUsers" :key="u.id" :value="u.id">
                  {{ u.name }} — {{ u.area }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Area</label>
              <select v-model="area" class="rv-input" :disabled="!!userId">
                <option value="">Pilih area</option>
                <option v-for="a in areas" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Dari Tanggal</label>
              <input v-model="startDate" type="date" class="rv-input" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Sampai Tanggal</label>
              <input v-model="endDate" type="date" class="rv-input" />
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
            <span v-if="periodLabel" class="text-xs text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
              Periode: <strong>{{ periodLabel }}</strong>
            </span>
            <span v-if="selectedRegional" class="text-xs text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-full">
              {{ selectedRegional.name }} · Area {{ selectedRegional.area }}
            </span>
          </div>
        </section>

        <section v-if="!userId && !area" class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center">
          <i class="fas fa-chart-bar text-4xl text-slate-300 mb-3"></i>
          <p class="text-slate-600 font-medium">Pilih regional atau area untuk melihat rekap kunjungan</p>
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
              </p>
              <div class="mt-4" :style="{ minHeight: Math.max(320, outletStats.length * 36) + 'px' }">
                <apexchart type="bar" :height="Math.max(320, outletStats.length * 36)" :options="visitBarOptions" :series="visitBarSeries" />
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
                  <tr v-for="(row, idx) in outletStats" :key="row.id_outlet" :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'">
                    <td class="px-5 py-3.5 font-medium text-slate-900">{{ row.nama_outlet }}</td>
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

        <section v-else class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center text-slate-500 text-sm">
          Tidak ada outlet pada area ini atau belum ada data kunjungan pada periode terpilih.
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.rv-input {
  @apply w-full rounded-lg border-slate-300 bg-white text-slate-800 text-sm shadow-sm
    focus:border-indigo-500 focus:ring-indigo-500 transition-colors px-3 py-2.5;
}
</style>
