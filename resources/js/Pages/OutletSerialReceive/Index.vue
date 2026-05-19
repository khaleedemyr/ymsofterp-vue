<template>
  <Head title="GR Nomor Seri" />
  <AppLayout>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 tracking-tight">GR Nomor Seri</h1>
          <p class="text-sm text-gray-500 mt-1">
            <template v-if="isHQ">Semua outlet</template>
            <template v-else>Outlet: <span class="font-medium text-gray-700">{{ userOutlet.name }}</span></template>
          </p>
        </div>
        <a href="/outlet-serial-receive/create"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-indigo-700 hover:shadow-md transition-all duration-200">
          <i class="fa fa-plus text-xs"></i> Buat GR Serial
        </a>
      </div>

      <!-- Filters Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
        <div class="flex flex-wrap items-end gap-3">
          <!-- Outlet filter (only for HQ) -->
          <div v-if="isHQ" class="min-w-[180px]">
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1.5">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1.5">Dari Tanggal</label>
            <input type="date" v-model="filters.date_from" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1.5">Sampai Tanggal</label>
            <input type="date" v-model="filters.date_to" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider block mb-1.5">Cari</label>
            <input type="text" v-model="filters.search" placeholder="Nomor GR..."
              class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition" />
          </div>
          <button @click="applyFilter"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-50 text-indigo-700 text-sm font-semibold rounded-lg hover:bg-indigo-100 transition">
            <i class="fa fa-search text-xs"></i> Filter
          </button>
          <button @click="resetFilter"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-100 transition">
            <i class="fa fa-times text-xs"></i> Reset
          </button>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div v-if="!grList.data || !grList.data.length" class="text-center py-16">
          <div class="text-gray-300 text-5xl mb-4"><i class="fa-solid fa-inbox"></i></div>
          <p class="text-gray-500 text-sm">Belum ada data GR Serial.</p>
        </div>
        <div v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50/50">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nomor GR</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                  <th v-if="isHQ" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Outlet</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Serial</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dibuat oleh</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="(row, idx) in grList.data" :key="row.id" class="hover:bg-indigo-50/30 transition-colors duration-150">
                  <td class="px-4 py-3.5 text-gray-500">{{ (grList.current_page - 1) * grList.per_page + idx + 1 }}</td>
                  <td class="px-4 py-3.5">
                    <a :href="`/outlet-serial-receive/${row.id}`" class="font-mono font-semibold text-indigo-600 hover:text-indigo-800 hover:underline transition">
                      {{ row.number }}
                    </a>
                  </td>
                  <td class="px-4 py-3.5 text-gray-600">{{ formatDate(row.receive_date) }}</td>
                  <td v-if="isHQ" class="px-4 py-3.5 text-gray-600">{{ row.outlet_name || row.outlet_id || '-' }}</td>
                  <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700">
                      {{ row.total_serials }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5 text-gray-600">{{ row.created_by_name || '-' }}</td>
                  <td class="px-4 py-3.5 text-center">
                    <div class="inline-flex items-center gap-1.5">
                      <a :href="`/outlet-serial-receive/${row.id}`"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                        <i class="fa fa-eye"></i> Detail
                      </a>
                      <button v-if="canDelete" @click="onDelete(row)"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 transition">
                        <i class="fa fa-trash"></i> Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="grList.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-500">
              Menampilkan {{ grList.from }}–{{ grList.to }} dari {{ grList.total }} data
            </p>
            <div class="flex gap-1">
              <template v-for="link in grList.links" :key="link.label">
                <a v-if="link.url" :href="link.url"
                  :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition', link.active ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-50 text-gray-600 hover:bg-gray-100']"
                  v-html="link.label"></a>
                <span v-else class="px-3 py-1.5 rounded-lg text-xs bg-gray-25 text-gray-300" v-html="link.label"></span>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { reactive } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  grList: Object,
  filters: Object,
  outlets: Array,
  canDelete: Boolean,
  isHQ: Boolean,
  userOutlet: Object,
})

const filters = reactive({
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  outlet_id: props.filters?.outlet_id || '',
})

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function applyFilter() {
  const params = {}
  if (filters.date_from) params.date_from = filters.date_from
  if (filters.date_to) params.date_to = filters.date_to
  if (filters.search) params.search = filters.search
  if (filters.outlet_id) params.outlet_id = filters.outlet_id
  router.get('/outlet-serial-receive', params, { preserveState: true })
}

function resetFilter() {
  filters.date_from = ''
  filters.date_to = ''
  filters.search = ''
  filters.outlet_id = ''
  router.get('/outlet-serial-receive', {}, { preserveState: true })
}

async function onDelete(row) {
  const confirm = await Swal.fire({
    title: 'Hapus GR Serial?',
    html: `GR <b>${row.number}</b> akan dihapus dan inventory akan di-rollback.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
  })

  if (!confirm.isConfirmed) return

  if (!row?.id) {
    Swal.fire('Gagal', 'ID GR tidak valid.', 'error')
    return
  }

  router.delete(route('outlet-serial-receive.destroy', row.id), {
    onSuccess: () => {
      Swal.fire({ title: 'Berhasil', text: `GR ${row.number} berhasil dihapus.`, icon: 'success', timer: 2000, showConfirmButton: false })
    },
    onError: (errors) => {
      const msg = errors?.message || 'Gagal menghapus GR.'
      Swal.fire('Gagal', msg, 'error')
    },
  })
}
</script>
