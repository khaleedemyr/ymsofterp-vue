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
const avatarLoadFailed = ref(new Set())

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const filterApplied = computed(() => !!(props.filters?.user_id || props.filters?.area))
const hasData = computed(() => props.outletStats.length > 0 && filterApplied.value)
const outletsByHours = computed(() =>
  [...props.outletStats].sort((a, b) => (b.total_hours || 0) - (a.total_hours || 0))
)
const maxVisitDays = computed(() => Math.max(...props.outletStats.map((o) => o.visit_days || 0), 1))
const maxTotalHours = computed(() => Math.max(...outletsByHours.value.map((o) => o.total_hours || 0), 1))
const hourSlots = computed(() => Array.from({ length: 24 }, (_, i) => i))
const modalHourlyMax = computed(() => {
  const data = outletModalData.value?.hourly_frequency?.data || []
  return Math.max(...data, 1)
})

const avatarTones = [
  'bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500',
  'bg-violet-500', 'bg-sky-500', 'bg-teal-500', 'bg-orange-500',
]

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`
})

const chartBase = {
  theme: { mode: 'light' },
  chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#475569' },
}

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

function visitorAvatarUrl(visitor) {
  if (visitor?.avatar) return `/storage/${visitor.avatar}`
  if (visitor?.photo) return `/storage/${visitor.photo}`
  return null
}

function avatarTone(userId) {
  return avatarTones[Math.abs(Number(userId) || 0) % avatarTones.length]
}

function barPct(value, max) {
  if (!value || !max) return 0
  return Math.max(12, Math.round((value / max) * 100))
}

function visitBarStyle(outlet) {
  const days = outlet.visit_days || 0
  if (days === 0) return { backgroundColor: '#cbd5e1' }
  if (days <= 2) return { backgroundColor: '#f59e0b' }
  if (days <= 5) return { backgroundColor: '#3b82f6' }
  return { backgroundColor: '#059669' }
}

function hourBarPct(count) {
  if (!count) return 0
  return Math.max(18, Math.round((count / modalHourlyMax.value) * 100))
}

function modalHourVisitors(hour) {
  return outletModalData.value?.hourly_frequency?.visitors_by_hour?.[hour] || []
}

function modalHourCount(hour) {
  return outletModalData.value?.hourly_frequency?.data?.[hour] || 0
}

function visibleVisitorsInBar(visitors, max = 4) {
  return (visitors || []).slice(0, max)
}

function overflowInBar(visitors, max = 4) {
  const total = visitors?.length || 0
  return total > max ? total - max : 0
}

function showVisitorInitials(visitor) {
  return !visitorAvatarUrl(visitor) || avatarLoadFailed.value.has(visitor.id)
}

function onAvatarError(userId) {
  avatarLoadFailed.value.add(userId)
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
              Area menentukan karyawan regional (Regional Management). Hanya outlet aktif (is_outlet=1) yang punya SN mesin absensi.
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
          <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Total Jam per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">
                Durasi kunjungan (IN→OUT) karyawan regional per outlet
                <span class="text-indigo-600">· Klik bar untuk detail</span>
              </p>
              <div class="mt-4 space-y-2 max-h-[360px] overflow-y-auto pr-1">
                <div
                  v-for="outlet in outletsByHours"
                  :key="'hours-' + outlet.id_outlet"
                  class="flex items-center gap-2"
                >
                  <div
                    class="w-[130px] shrink-0 text-[11px] text-slate-700 truncate text-right"
                    :title="outlet.nama_outlet"
                  >{{ outlet.nama_outlet }}</div>
                  <div
                    class="flex-1 h-9 bg-slate-100 rounded-lg overflow-hidden cursor-pointer"
                    @click="openOutletDetail(outlet)"
                  >
                    <div
                      v-if="outlet.total_hours > 0"
                      class="h-full rounded-lg flex items-center gap-1.5 px-2 bg-indigo-500 hover:bg-indigo-600 transition-colors"
                      :style="{ width: barPct(outlet.total_hours, maxTotalHours) + '%', minWidth: outlet.visitors?.length ? '80px' : '52px' }"
                    >
                      <div v-if="outlet.visitors?.length" class="flex items-center -space-x-1 shrink-0">
                        <div
                          v-for="visitor in visibleVisitorsInBar(outlet.visitors)"
                          :key="visitor.id"
                          class="rv-avatar-wrap group relative"
                        >
                          <div class="rv-avatar rv-avatar-in-bar" :class="showVisitorInitials(visitor) ? avatarTone(visitor.id) : ''">
                            <img
                              v-if="visitorAvatarUrl(visitor) && !avatarLoadFailed.has(visitor.id)"
                              :src="visitorAvatarUrl(visitor)"
                              :alt="visitor.name"
                              class="w-full h-full object-cover"
                              @error="onAvatarError(visitor.id)"
                            />
                            <span v-if="showVisitorInitials(visitor)" class="rv-avatar-initials">{{ visitor.initials }}</span>
                          </div>
                          <div class="rv-avatar-tooltip">
                            <p class="font-semibold text-slate-900">{{ visitor.name }}</p>
                            <p class="text-slate-500 mt-0.5">{{ visitor.nama_jabatan }}</p>
                          </div>
                        </div>
                        <span v-if="overflowInBar(outlet.visitors)" class="rv-avatar-more-in-bar">+{{ overflowInBar(outlet.visitors) }}</span>
                      </div>
                      <span class="text-[10px] font-bold text-white ml-auto whitespace-nowrap">{{ outlet.total_hours }} jam</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Coverage Kunjungan</h3>
              <p class="text-xs text-slate-500 mt-1">Proporsi outlet yang sudah pernah dikunjungi</p>
              <div class="mt-6">
                <apexchart type="donut" height="280" :options="pieOptions" :series="pieSeries" />
              </div>
            </div>
          </section>

          <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="xl:col-span-2">
              <h3 class="text-base font-semibold text-slate-900">Frekuensi Kunjungan per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">
                <span class="inline-block w-3 h-3 rounded bg-emerald-500 mr-1"></span>Sering
                <span class="inline-block w-3 h-3 rounded bg-blue-500 mx-1"></span>Cukup
                <span class="inline-block w-3 h-3 rounded bg-amber-500 mx-1"></span>Jarang
                <span class="inline-block w-3 h-3 rounded bg-slate-300 mx-1"></span>Belum pernah
                <span class="text-indigo-600">· Klik bar untuk detail</span>
                <span class="text-slate-400">· Hover avatar untuk nama & jabatan</span>
              </p>
              <div class="mt-4 space-y-2 max-h-[520px] overflow-y-auto pr-1">
                <div
                  v-for="outlet in outletStats"
                  :key="outlet.id_outlet"
                  class="flex items-center gap-2"
                >
                  <div
                    class="w-[130px] shrink-0 text-[11px] text-slate-700 truncate text-right"
                    :title="outlet.nama_outlet"
                  >{{ outlet.nama_outlet }}</div>
                  <div
                    class="flex-1 h-9 bg-slate-100 rounded-lg overflow-hidden cursor-pointer"
                    @click="openOutletDetail(outlet)"
                  >
                    <div
                      v-if="outlet.visit_days > 0"
                      class="h-full rounded-lg flex items-center gap-1.5 px-2 transition-colors hover:brightness-95"
                      :style="{ ...visitBarStyle(outlet), width: barPct(outlet.visit_days, maxVisitDays) + '%', minWidth: outlet.visitors?.length ? '80px' : '52px' }"
                    >
                      <div v-if="outlet.visitors?.length" class="flex items-center -space-x-1 shrink-0">
                        <div
                          v-for="visitor in visibleVisitorsInBar(outlet.visitors)"
                          :key="visitor.id"
                          class="rv-avatar-wrap group relative"
                        >
                          <div class="rv-avatar rv-avatar-in-bar" :class="showVisitorInitials(visitor) ? avatarTone(visitor.id) : ''">
                            <img
                              v-if="visitorAvatarUrl(visitor) && !avatarLoadFailed.has(visitor.id)"
                              :src="visitorAvatarUrl(visitor)"
                              :alt="visitor.name"
                              class="w-full h-full object-cover"
                              @error="onAvatarError(visitor.id)"
                            />
                            <span v-if="showVisitorInitials(visitor)" class="rv-avatar-initials">{{ visitor.initials }}</span>
                          </div>
                          <div class="rv-avatar-tooltip">
                            <p class="font-semibold text-slate-900">{{ visitor.name }}</p>
                            <p class="text-slate-500 mt-0.5">{{ visitor.nama_jabatan }}</p>
                          </div>
                        </div>
                        <span v-if="overflowInBar(outlet.visitors)" class="rv-avatar-more-in-bar">+{{ overflowInBar(outlet.visitors) }}</span>
                      </div>
                      <span class="text-[10px] font-bold text-white ml-auto whitespace-nowrap">{{ outlet.visit_days }} hari</span>
                    </div>
                  </div>
                </div>
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
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                  <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                    <p class="text-xs text-indigo-600 font-medium">Hari Kunjungan</p>
                    <p class="text-xl font-bold text-indigo-800">{{ outletModalData.summary.visit_days }}</p>
                  </div>
                  <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-600 font-medium">Total Scan IN</p>
                    <p class="text-xl font-bold text-emerald-800">{{ outletModalData.summary.scan_in_count }}</p>
                  </div>
                  <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <p class="text-xs text-rose-600 font-medium">Total Scan OUT</p>
                    <p class="text-xl font-bold text-rose-800">{{ outletModalData.summary.scan_out_count }}</p>
                  </div>
                  <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <p class="text-xs text-violet-600 font-medium">Total Jam</p>
                    <p class="text-xl font-bold text-violet-800">{{ outletModalData.summary.total_hours }} jam</p>
                  </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
                  <h4 class="text-sm font-semibold text-slate-900">Frekuensi Kunjungan per Jam</h4>
                  <p class="text-xs text-slate-500 mt-0.5">Distribusi scan IN · hover avatar untuk nama & jabatan</p>
                  <div class="mt-4 flex items-end gap-0.5 h-[200px] border-b border-slate-200 pb-1 overflow-x-auto">
                    <div
                      v-for="hour in hourSlots"
                      :key="'hour-' + hour"
                      class="flex flex-col items-center justify-end flex-1 min-w-[22px] h-full"
                    >
                      <div
                        v-if="modalHourCount(hour) > 0"
                        class="w-full bg-indigo-500 rounded-t-md flex flex-col items-center justify-end gap-0.5 px-0.5 pb-1 pt-1"
                        :style="{ height: hourBarPct(modalHourCount(hour)) + '%', minHeight: '36px' }"
                      >
                        <div v-if="modalHourVisitors(hour).length" class="flex flex-col-reverse items-center -space-y-0.5 mb-0.5">
                          <div
                            v-for="visitor in visibleVisitorsInBar(modalHourVisitors(hour), 3)"
                            :key="visitor.id"
                            class="rv-avatar-wrap group relative"
                          >
                            <div class="rv-avatar rv-avatar-in-bar" :class="showVisitorInitials(visitor) ? avatarTone(visitor.id) : ''">
                              <img
                                v-if="visitorAvatarUrl(visitor) && !avatarLoadFailed.has(visitor.id)"
                                :src="visitorAvatarUrl(visitor)"
                                :alt="visitor.name"
                                class="w-full h-full object-cover"
                                @error="onAvatarError(visitor.id)"
                              />
                              <span v-if="showVisitorInitials(visitor)" class="rv-avatar-initials">{{ visitor.initials }}</span>
                            </div>
                            <div class="rv-avatar-tooltip">
                              <p class="font-semibold text-slate-900">{{ visitor.name }}</p>
                              <p class="text-slate-500 mt-0.5">{{ visitor.nama_jabatan }}</p>
                            </div>
                          </div>
                        </div>
                        <span class="text-[8px] font-bold text-white leading-none">{{ modalHourCount(hour) }}</span>
                      </div>
                      <span class="text-[8px] text-slate-500 mt-1 leading-none">{{ String(hour).padStart(2, '0') }}</span>
                    </div>
                  </div>
                </div>

                <div v-if="outletModalData.daily_visits.length" class="overflow-x-auto rounded-xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <th class="px-4 py-2.5 text-left font-semibold w-8"></th>
                        <th class="px-4 py-2.5 text-left font-semibold">Hari</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Karyawan</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Jam Masuk</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Jam Keluar</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Durasi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <template v-for="day in outletModalData.daily_visits" :key="day.tanggal">
                        <template v-for="(session, sIdx) in day.sessions" :key="day.tanggal + '-' + session.user_id">
                          <tr class="hover:bg-indigo-50/30 transition-colors">
                            <td class="px-4 py-3">
                              <button
                                v-if="session.scans?.length"
                                type="button"
                                class="text-slate-400 hover:text-indigo-600"
                                @click="toggleDayScans(day.tanggal + '-' + session.user_id)"
                              >
                                <i :class="['fas', expandedDays.has(day.tanggal + '-' + session.user_id) ? 'fa-chevron-down' : 'fa-chevron-right', 'text-xs']"></i>
                              </button>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ sIdx === 0 ? day.hari : '' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ sIdx === 0 ? day.tanggal_label : '' }}</td>
                            <td class="px-4 py-3 text-slate-800 font-medium">{{ session.user_name }}</td>
                            <td class="px-4 py-3 text-emerald-700 font-medium">{{ session.jam_masuk_display || '—' }}</td>
                            <td class="px-4 py-3 text-rose-700 font-medium">
                              <span v-if="session.has_no_checkout" class="text-amber-600 text-xs font-semibold">Belum checkout</span>
                              <template v-else>{{ session.jam_keluar_display || '—' }}</template>
                              <span v-if="session.is_cross_day && session.jam_keluar_display" class="text-xs text-amber-600 ml-1">+1 hari</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ session.durasi_label }}</td>
                          </tr>
                          <tr v-if="expandedDays.has(day.tanggal + '-' + session.user_id) && session.scans?.length" class="bg-slate-50">
                            <td colspan="7" class="px-4 py-3">
                              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Riwayat Scan</p>
                              <div class="space-y-1.5">
                                <div
                                  v-for="(scan, scanIdx) in session.scans"
                                  :key="scanIdx"
                                  class="flex flex-wrap items-center gap-2 text-xs"
                                >
                                  <span
                                    class="inline-flex items-center px-2 py-0.5 rounded font-semibold"
                                    :class="scan.type === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                  >{{ scan.type }}</span>
                                  <span class="font-mono font-medium text-slate-800">{{ scan.jam_label }}</span>
                                </div>
                              </div>
                            </td>
                          </tr>
                        </template>
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


.rv-avatar-wrap {
  @apply relative z-10 hover:z-30;
}

.rv-avatar {
  @apply w-6 h-6 rounded-full border-2 border-white shadow-sm overflow-hidden
    flex items-center justify-center text-[9px] font-bold text-white;
}

.rv-avatar-in-bar {
  @apply w-5 h-5 text-[8px] border border-white/90 shadow;
}

.rv-avatar-initials {
  @apply leading-none select-none;
}

.rv-avatar-more-in-bar {
  @apply inline-flex items-center justify-center min-w-[1.1rem] h-4 px-0.5
    rounded-full bg-white/25 text-[8px] font-bold text-white border border-white/60;
}

.rv-avatar-tooltip {
  @apply absolute left-1/2 bottom-full -translate-x-1/2 mb-2 w-max max-w-[180px]
    px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 shadow-lg text-[11px]
    opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all
    pointer-events-none z-50;
}

.rv-avatar-tooltip::after {
  content: '';
  @apply absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-white;
}
</style>
