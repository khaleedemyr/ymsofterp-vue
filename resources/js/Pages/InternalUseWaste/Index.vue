<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-recycle text-green-500"></i> Internal Use & Waste
        </h1>
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="goReport" class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:bg-blue-600 transition-all font-semibold">
            <i class="fa fa-file-lines mr-1"></i> Laporan Internal Use
          </button>
          <button type="button" @click="goReportWasteSpoil" class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:bg-yellow-600 transition-all font-semibold">
            <i class="fa fa-file-lines mr-1"></i> Laporan Spoil & Waste
          </button>
          <button type="button" @click="goCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Baru
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-4 md:p-6 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Tipe</label>
            <select v-model="filterForm.type" class="input input-bordered w-full">
              <option value="">Semua</option>
              <option value="internal_use">Internal Use</option>
              <option value="spoil">Spoil</option>
              <option value="waste">Waste</option>
              <option value="r_and_d">RnD</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Warehouse</label>
            <select v-model="filterForm.warehouse_id" class="input input-bordered w-full">
              <option value="">Semua</option>
              <option v-for="w in props.warehouses" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Dari tanggal</label>
            <input v-model="filterForm.date_from" type="date" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Sampai tanggal</label>
            <input v-model="filterForm.date_to" type="date" class="input input-bordered w-full" />
          </div>
          <div class="lg:col-span-1">
            <label class="block text-xs font-bold text-gray-600 mb-1">Cari item</label>
            <input v-model="filterForm.search" type="text" class="input input-bordered w-full" placeholder="Nama item" @keyup.enter="applyFilters" />
          </div>
          <div class="flex flex-wrap gap-2">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Per halaman</label>
              <select v-model.number="filterForm.per_page" class="input input-bordered w-full min-w-[5rem]">
                <option :value="10">10</option>
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
            <button type="button" class="btn bg-green-600 text-white px-4 py-2 rounded-lg h-[42px] self-end" @click="applyFilters">
              <i class="fa fa-filter mr-1"></i> Terapkan
            </button>
            <button type="button" class="btn btn-ghost px-4 py-2 rounded-lg h-[42px] self-end border" @click="resetFilters">
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-green-50 to-green-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Dok</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Tipe</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Item</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Qty</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Unit</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Catatan</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!props.rows.length">
              <td colspan="9" class="text-center py-10 text-green-300">Tidak ada data.</td>
            </tr>
            <tr v-for="row in props.rows" :key="row.id" class="hover:bg-green-50/40">
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ documentId(row) }}
                <span
                  v-if="row.document_mode && row.document_mode !== 'normal'"
                  class="mt-1 block px-2 py-0.5 rounded-full text-[10px] font-semibold w-fit"
                  :class="{
                    'bg-indigo-100 text-indigo-700': row.document_mode === 'serial',
                    'bg-purple-100 text-purple-700': row.document_mode === 'mixed',
                  }"
                >{{ documentModeLabel(row.document_mode) }}</span>
              </td>
              <td class="px-4 py-3">{{ formatDate(row.date) }}</td>
              <td class="px-4 py-3">{{ typeLabel(row.type) }}</td>
              <td class="px-4 py-3">{{ row.warehouse_name }}</td>
              <td class="px-4 py-3">{{ row.item_name }}</td>
              <td class="px-4 py-3">{{ formatNumber(row.qty) }}</td>
              <td class="px-4 py-3">{{ row.unit_name }}</td>
              <td class="px-4 py-3 text-sm max-w-xs truncate" :title="lineNoteDisplay(row)">{{ lineNoteDisplay(row) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">
                <button type="button" class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(documentId(row))">
                  <i class="fa fa-eye mr-1"></i> Detail
                </button>
                <button
                  v-if="!row.document_mode || row.document_mode === 'normal'"
                  type="button"
                  class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition ml-1"
                  @click="goEdit(documentId(row))"
                >
                  <i class="fa fa-pen mr-1"></i> Edit
                </button>
                <button
                  v-if="props.canDelete"
                  type="button"
                  class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-1"
                  @click="onDelete(documentId(row))"
                  :disabled="loadingId === documentId(row)"
                >
                  <span v-if="loadingId === documentId(row)"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                  <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.pagination.last_page > 1" class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-gray-600">
        <div>
          Menampilkan {{ props.pagination.from ?? 0 }}–{{ props.pagination.to ?? 0 }} dari {{ props.pagination.total }}
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn btn-sm border rounded px-3 py-1"
            :disabled="props.pagination.current_page <= 1"
            @click="goPage(props.pagination.current_page - 1)"
          >
            Sebelumnya
          </button>
          <span class="px-2">Halaman {{ props.pagination.current_page }} / {{ props.pagination.last_page }}</span>
          <button
            type="button"
            class="btn btn-sm border rounded px-3 py-1"
            :disabled="props.pagination.current_page >= props.pagination.last_page"
            @click="goPage(props.pagination.current_page + 1)"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { reactive, ref, watch } from 'vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  warehouses: { type: Array, default: () => [] },
  canDelete: Boolean,
})

const loadingId = ref(null)

const filterForm = reactive({
  type: props.filters?.type ?? '',
  warehouse_id: props.filters?.warehouse_id != null && props.filters?.warehouse_id !== '' ? String(props.filters.warehouse_id) : '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  search: props.filters?.search ?? '',
  per_page: Number(props.filters?.per_page) || props.pagination?.per_page || 15,
})

watch(
  () => props.filters,
  (f) => {
    filterForm.type = f?.type ?? ''
    filterForm.warehouse_id = f?.warehouse_id != null && f?.warehouse_id !== '' ? String(f.warehouse_id) : ''
    filterForm.date_from = f?.date_from ?? ''
    filterForm.date_to = f?.date_to ?? ''
    filterForm.search = f?.search ?? ''
    filterForm.per_page = Number(f?.per_page) || 15
  },
  { deep: true }
)

function documentId(row) {
  return row.header_id != null ? row.header_id : row.id
}

function lineNoteDisplay(row) {
  if (row.notes) return row.notes
  if (row.header_notes) return row.header_notes
  return '-'
}

function queryPayload(page) {
  const q = {
    per_page: filterForm.per_page,
    page: page || 1,
  }
  if (filterForm.type) q.type = filterForm.type
  if (filterForm.warehouse_id) q.warehouse_id = filterForm.warehouse_id
  if (filterForm.date_from) q.date_from = filterForm.date_from
  if (filterForm.date_to) q.date_to = filterForm.date_to
  if (filterForm.search && filterForm.search.trim()) q.search = filterForm.search.trim()
  return q
}

function applyFilters() {
  router.get(route('internal-use-waste.index'), queryPayload(1), { preserveState: true, replace: true })
}

function resetFilters() {
  filterForm.type = ''
  filterForm.warehouse_id = ''
  filterForm.date_from = ''
  filterForm.date_to = ''
  filterForm.search = ''
  filterForm.per_page = 15
  router.get(route('internal-use-waste.index'), { per_page: 15, page: 1 }, { preserveState: true, replace: true })
}

function goPage(page) {
  if (page < 1 || page > props.pagination.last_page) return
  router.get(route('internal-use-waste.index'), queryPayload(page), { preserveState: true, replace: true })
}

function goCreate() {
  router.visit(route('internal-use-waste.create'))
}

function goReport() {
  router.visit(route('internal-use-waste.report'))
}

function goReportWasteSpoil() {
  router.visit(route('internal-use-waste.report-waste-spoil'))
}

function goDetail(id) {
  router.visit(route('internal-use-waste.show', id))
}

function goEdit(id) {
  router.visit(route('internal-use-waste.edit', id))
}

function onDelete(id) {
  Swal.fire({
    title: 'Yakin hapus dokumen ini?',
    text: 'Semua baris item terkait akan dihapus dan stok di-rollback.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  }).then(async (result) => {
    if (result.isConfirmed) {
      loadingId.value = id
      try {
        const res = await axios.delete(route('internal-use-waste.destroy', id))
        if (res.data && res.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Data berhasil dihapus dan stok di-rollback.',
            timer: 1500,
            showConfirmButton: false,
          })
          setTimeout(() => router.reload(), 1200)
        } else {
          throw new Error('Gagal menghapus data')
        }
      } catch (e) {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: e.response?.data?.message || e.message || 'Gagal menghapus data',
        })
      } finally {
        loadingId.value = null
      }
    }
  })
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

function formatNumber(val) {
  if (val == null) return '-'
  if (Number(val) % 1 === 0) return Number(val)
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use'
  if (type === 'spoil') return 'Spoil'
  if (type === 'waste') return 'Waste'
  if (type === 'r_and_d') return 'RnD'
  return type
}

function documentModeLabel(mode) {
  if (mode === 'serial') return 'Serial'
  if (mode === 'mixed') return 'Campuran'
  return null
}
</script>

<style scoped>
.input {
  @apply border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300 transition;
}
.btn {
  @apply font-semibold shadow transition;
}
.btn-ghost {
  @apply bg-gray-100 hover:bg-gray-200;
}
</style>
