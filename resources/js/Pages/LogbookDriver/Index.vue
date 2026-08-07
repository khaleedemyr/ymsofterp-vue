<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-truck text-cyan-600"></i>
            Logbook Driver
          </h1>
          <p class="text-sm text-gray-500 mt-1">Catatan aktivitas driver per outlet (header + baris log)</p>
        </div>
        <Link
          :href="route('logbook-drivers.create')"
          class="inline-flex items-center gap-2 bg-cyan-600 text-white px-4 py-2 rounded-lg shadow hover:bg-cyan-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Logbook
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nomor, outlet, driver..."
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
            <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Per page</label>
            <select
              v-model.number="filterForm.per_page"
              class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
              @change="changePerPage"
            >
              <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div class="xl:col-span-6 flex gap-2">
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
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Driver</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Outlet</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Jml Log</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in records.data" :key="row.id" class="hover:bg-cyan-50/40">
                <td class="px-4 py-3 text-sm font-medium text-cyan-700 whitespace-nowrap">
                  <Link :href="route('logbook-drivers.show', row.id)">{{ row.number }}</Link>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ formatDate(row.log_date) }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ row.driver_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ row.outlet_name }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-700">{{ row.items_count || 0 }}</td>
                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                  <Link :href="route('logbook-drivers.show', row.id)" class="text-cyan-600 hover:text-cyan-800 mr-3" title="Detail">
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link :href="route('logbook-drivers.edit', row.id)" class="text-amber-600 hover:text-amber-800 mr-3" title="Edit">
                    <i class="fa-solid fa-pen"></i>
                  </Link>
                  <button type="button" class="text-red-600 hover:text-red-800" title="Hapus" @click="destroyRow(row)">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="!records.data?.length">
                <td colspan="6" class="px-4 py-10 text-center text-gray-500">Belum ada Logbook Driver.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="records.total > 0"
          class="px-4 py-3 border-t flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-gray-600"
        >
          <div>
            Menampilkan {{ showingFrom }}–{{ showingTo }} dari {{ records.total }} data
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Halaman {{ records.current_page }} / {{ records.last_page }}</span>
            <Link
              v-if="records.prev_page_url"
              :href="records.prev_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
              preserve-scroll
            >Prev</Link>
            <span v-else class="px-3 py-1 rounded bg-gray-50 text-gray-300 cursor-not-allowed">Prev</span>
            <Link
              v-if="records.next_page_url"
              :href="records.next_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
              preserve-scroll
            >Next</Link>
            <span v-else class="px-3 py-1 rounded bg-gray-50 text-gray-300 cursor-not-allowed">Next</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  records: Object,
  filters: Object,
  outlets: Array,
  isSuperAdmin: Boolean,
})

const perPageOptions = [10, 15, 25, 50, 100]

const filterForm = reactive({
  search: props.filters?.search || '',
  outlet_id: props.filters?.outlet_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: Number(props.filters?.per_page) || 15,
})

const showingFrom = computed(() => {
  if (!props.records?.total) return 0
  return ((props.records.current_page - 1) * props.records.per_page) + 1
})

const showingTo = computed(() => {
  if (!props.records?.total) return 0
  return Math.min(props.records.current_page * props.records.per_page, props.records.total)
})

function applyFilters() {
  router.get(
    route('logbook-drivers.index'),
    { ...filterForm, page: 1 },
    { preserveState: true, replace: true },
  )
}

function resetFilters() {
  filterForm.search = ''
  filterForm.outlet_id = ''
  filterForm.date_from = ''
  filterForm.date_to = ''
  filterForm.per_page = 15
  applyFilters()
}

function changePerPage() {
  applyFilters()
}

function formatDate(value) {
  if (!value) return '-'
  const d = String(value).slice(0, 10)
  const [y, m, day] = d.split('-')
  if (!y || !m || !day) return value
  return `${day}/${m}/${y}`
}

function destroyRow(row) {
  if (!confirm(`Hapus logbook ${row.number}?`)) return
  router.delete(route('logbook-drivers.destroy', row.id))
}
</script>
