<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  rows: Object,
  outlets: Array,
  divisions: Array,
  authUser: Object,
  filters: Object,
})

function normalizeFiltersFromProps(f) {
  if (!f) {
    return {
      outlet_id: '',
      division_id: '',
      status: 'A',
      search: '',
      per_page: 25,
    }
  }
  return {
    outlet_id: f.outlet_id != null && f.outlet_id !== '' ? String(f.outlet_id) : '',
    division_id: f.division_id != null && f.division_id !== '' ? String(f.division_id) : '',
    status: f.status ?? 'A',
    search: f.search ?? '',
    per_page: Number(f.per_page) > 0 ? Number(f.per_page) : 25,
  }
}

const filters = ref(normalizeFiltersFromProps(props.filters))

const loading = ref(false)

watch(
  () => props.filters,
  (f) => {
    filters.value = normalizeFiltersFromProps(f)
  },
  { deep: true },
)

const outletLocked = computed(
  () => props.authUser?.id_outlet && Number(props.authUser.id_outlet) !== 1,
)

const availableOutlets = computed(() => props.outlets || [])

/** Plain object for Inertia GET (bukan ref/proxy Vue) */
function buildQueryParams() {
  const f = filters.value
  const q = {
    status: f.status || 'A',
    per_page: Number(f.per_page) > 0 ? Number(f.per_page) : 25,
  }
  if (f.outlet_id) q.outlet_id = String(f.outlet_id)
  if (f.division_id) q.division_id = String(f.division_id)
  const s = f.search != null ? String(f.search).trim() : ''
  if (s) q.search = s
  return q
}

const visitOptions = {
  preserveState: false,
  replace: true,
  preserveScroll: true,
  onStart: () => {
    loading.value = true
  },
  onFinish: () => {
    loading.value = false
  },
  onCancel: () => {
    loading.value = false
  },
  onError: () => {
    loading.value = false
  },
}

function applyFilters() {
  router.get('/users/leave-balance-report', buildQueryParams(), visitOptions)
}

function exportExcel() {
  const params = new URLSearchParams()
  const q = buildQueryParams()
  Object.entries(q).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') params.append(k, String(v))
  })
  const url = `/users/leave-balance-report/export?${params.toString()}`
  window.open(url, '_blank')
}

function goToPage(url) {
  if (!url) return
  router.visit(url, visitOptions)
}

</script>

<template>
  <AppLayout title="Saldo Cuti, PH & Extra Off">
    <div class="w-full py-8 px-4">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <i class="fa-solid fa-scale-balanced text-indigo-500"></i>
            Saldo Cuti, PH & Extra Off
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Ringkasan per karyawan: cuti tahunan, PH extra off (libur nasional), dan saldo extra off.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a
            :href="route('users.index')"
            class="inline-flex items-center px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 font-semibold hover:bg-gray-300 dark:hover:bg-gray-500"
          >
            <i class="fa-solid fa-arrow-left mr-2"></i>
            Kembali ke Data Karyawan
          </a>
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 rounded-xl bg-green-600 text-white font-semibold hover:bg-green-700"
            @click="exportExcel"
          >
            <i class="fa-solid fa-file-excel mr-2"></i>
            Export Excel
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Outlet</label>
            <select
              v-model="filters.outlet_id"
              :disabled="outletLocked"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option value="">Semua outlet</option>
              <option v-for="o in availableOutlets" :key="o.id_outlet" :value="String(o.id_outlet)">
                {{ o.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Divisi</label>
            <select
              v-model="filters.division_id"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option value="">Semua divisi</option>
              <option v-for="d in divisions" :key="d.id" :value="String(d.id)">
                {{ d.nama_divisi }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status karyawan</label>
            <select
              v-model="filters.status"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option value="A">Aktif</option>
              <option value="N">Non-aktif</option>
              <option value="B">Baru</option>
              <option value="all">Semua</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cari</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nama, NIK, email..."
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
              @keyup.enter="applyFilters"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Per halaman</label>
            <select
              v-model.number="filters.per_page"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              type="button"
              class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center gap-2"
              :disabled="loading"
              @click="applyFilters"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-filter"></i>
              {{ loading ? 'Memuat…' : 'Terapkan' }}
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
        <div
          v-if="loading"
          class="absolute inset-0 z-20 bg-white/70 dark:bg-gray-900/70 flex flex-col items-center justify-center gap-2"
          aria-busy="true"
          aria-label="Memuat data"
        >
          <i class="fa-solid fa-spinner fa-spin text-4xl text-indigo-600 dark:text-indigo-400"></i>
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Memuat data…</span>
        </div>
        <div class="overflow-x-auto" :class="{ 'min-h-[200px]': loading }">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">NIK</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nama</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Outlet</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Divisi</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cuti (hari)</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">PH EO (hari)</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Extra Off (hari)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in rows.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ row.nik || '—' }}</td>
                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ row.nama_lengkap }}</td>
                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.nama_outlet || '—' }}</td>
                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">{{ row.nama_divisi || '—' }}</td>
                <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                  {{ Number(row.cuti || 0) }}
                </td>
                <td class="px-3 py-2 text-sm text-right text-blue-700 dark:text-blue-300">
                  {{ Number(row.ph_extra_off_days_approved || 0) }}
                </td>
                <td class="px-3 py-2 text-sm text-right text-purple-700 dark:text-purple-300">
                  {{ Number(row.extra_off_balance_days || 0) }}
                </td>
              </tr>
              <tr v-if="!rows.data || rows.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                  Tidak ada data
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="rows.links && rows.links.length > 3"
          class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-1 justify-center"
        >
          <button
            v-for="(link, i) in rows.links"
            :key="i"
            type="button"
            class="px-3 py-1 rounded text-sm"
            :class="[
              link.active
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200',
              !link.url ? 'opacity-50 cursor-not-allowed' : '',
            ]"
            :disabled="!link.url"
            @click="link.url && goToPage(link.url)"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
