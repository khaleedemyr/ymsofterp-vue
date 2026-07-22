<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-laptop-medical text-cyan-600"></i>
            IT Work Report
          </h1>
          <p class="text-sm text-gray-500 mt-1">Laporan kunjungan IT per outlet — ticket, WhatsApp, atau proaktif</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            @click="exportExcel"
            class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50 transition"
          >
            <i class="fa-solid fa-file-excel text-green-600"></i>
            Export Excel
          </button>
          <Link
            :href="route('it-work-reports.create')"
            class="inline-flex items-center gap-2 bg-cyan-600 text-white px-4 py-2 rounded-lg shadow hover:bg-cyan-700 transition"
          >
            <i class="fa-solid fa-plus"></i>
            Buat Report
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 xl:grid-cols-8 gap-3 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nomor, outlet, judul..."
              class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet</label>
            <select v-model="filterForm.outlet_id" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
              <option value="">Semua</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Sumber</label>
            <select v-model="filterForm.source_type" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
              <option value="all">Semua</option>
              <option v-for="(label, key) in sourceOptions" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select v-model="filterForm.status" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
              <option value="all">Semua</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Scope</label>
            <select v-model="filterForm.scope" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
              <option value="">Semua</option>
              <option v-for="(label, key) in scopeOptions" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
          </div>
          <div class="xl:col-span-8 flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700">
              <i class="fa-solid fa-filter"></i> Filter
            </button>
            <button type="button" @click="resetFilters" class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
              Reset
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nomor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Judul</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sumber</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Pelaksana</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Device</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in reports.data" :key="row.id" class="hover:bg-cyan-50/40">
                <td class="px-4 py-3 text-sm font-medium text-cyan-700 whitespace-nowrap">
                  <Link :href="route('it-work-reports.show', row.id)">{{ row.number }}</Link>
                </td>
                <td class="px-4 py-3 text-sm text-gray-800 max-w-xs">
                  <Link
                    :href="route('it-work-reports.show', row.id)"
                    class="line-clamp-2 hover:text-cyan-700"
                    :title="row.title || ''"
                  >
                    {{ row.title || '—' }}
                  </Link>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ formatDate(row.work_date) }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ row.outlet_name }}</td>
                <td class="px-4 py-3 text-sm">
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                    {{ sourceOptions[row.source_type] || row.source_type }}
                  </span>
                  <div v-if="row.ticket" class="text-xs text-gray-500 mt-0.5">{{ row.ticket.ticket_number }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ row.executor?.nama_lengkap || '-' }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-700">{{ row.items_count || 0 }}</td>
                <td class="px-4 py-3">
                  <span :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
                </td>
                <td class="px-4 py-3 text-right text-sm">
                  <Link :href="route('it-work-reports.show', row.id)" class="text-cyan-600 hover:text-cyan-800 mr-3">
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link
                    v-if="row.status === 'draft'"
                    :href="route('it-work-reports.edit', row.id)"
                    class="text-amber-600 hover:text-amber-800"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="!reports.data?.length">
                <td colspan="9" class="px-4 py-10 text-center text-gray-500">Belum ada IT Work Report.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="reports.last_page > 1" class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
          <span>Halaman {{ reports.current_page }} / {{ reports.last_page }}</span>
          <div class="flex gap-2">
            <Link
              v-if="reports.prev_page_url"
              :href="reports.prev_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
            >Prev</Link>
            <Link
              v-if="reports.next_page_url"
              :href="reports.next_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
            >Next</Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  reports: Object,
  filters: Object,
  outlets: Array,
  executors: Array,
  scopeOptions: Object,
  sourceOptions: Object,
})

const filterForm = reactive({
  search: props.filters?.search || '',
  outlet_id: props.filters?.outlet_id || '',
  source_type: props.filters?.source_type || 'all',
  status: props.filters?.status || 'all',
  scope: props.filters?.scope || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: props.filters?.per_page || 15,
})

function applyFilters() {
  router.get(route('it-work-reports.index'), { ...filterForm }, { preserveState: true, replace: true })
}

function resetFilters() {
  Object.assign(filterForm, {
    search: '',
    outlet_id: '',
    source_type: 'all',
    status: 'all',
    scope: '',
    date_from: '',
    date_to: '',
    per_page: 15,
  })
  applyFilters()
}

function exportExcel() {
  const params = new URLSearchParams()
  Object.entries(filterForm).forEach(([k, v]) => {
    if (v !== '' && v != null && v !== 'all') params.set(k, v)
  })
  window.location.href = route('it-work-reports.export') + '?' + params.toString()
}

function formatDate(value) {
  if (!value) return '-'
  const s = String(value)
  if (/^\d{4}-\d{2}-\d{2}$/.test(s.slice(0, 10)) && !s.includes('T')) {
    return s.slice(0, 10)
  }
  // Legacy ISO UTC dari cast date Laravel → pakai tanggal lokal
  if (s.includes('T')) {
    const d = new Date(s)
    if (!Number.isNaN(d.getTime())) {
      const p = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
    }
  }
  return s.slice(0, 10)
}

function statusLabel(status) {
  return status === 'submitted' ? 'Submitted' : 'Draft'
}

function statusClass(status) {
  return status === 'submitted'
    ? 'inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700'
    : 'inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700'
}
</script>
