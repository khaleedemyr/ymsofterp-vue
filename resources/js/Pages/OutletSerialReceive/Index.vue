<template>
  <Head title="GR Nomor Seri" />
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-purple-800 flex items-center gap-2">
          <i class="fa-solid fa-barcode text-purple-500"></i> GR Nomor Seri
        </h1>
        <a href="/outlet-serial-receive/create"
          class="px-4 py-2 bg-purple-600 text-white rounded-lg shadow hover:bg-purple-700 flex items-center gap-2 font-semibold">
          <i class="fa fa-plus"></i> Buat GR Serial
        </a>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap gap-4 items-end">
        <div>
          <label class="text-xs font-semibold text-gray-600 block mb-1">Dari Tanggal</label>
          <input type="date" v-model="filters.date_from" class="border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 block mb-1">Sampai Tanggal</label>
          <input type="date" v-model="filters.date_to" class="border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 block mb-1">Cari Nomor</label>
          <input type="text" v-model="filters.search" placeholder="Nomor GR..." class="border rounded px-3 py-2 text-sm" />
        </div>
        <button @click="applyFilter" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-semibold hover:bg-blue-700">
          <i class="fa fa-search"></i> Filter
        </button>
        <button @click="resetFilter" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm font-semibold hover:bg-gray-300">
          <i class="fa fa-times"></i> Reset
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <div v-if="!grList.data || !grList.data.length" class="text-center text-gray-400 py-10">
          Belum ada data GR Serial.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-purple-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">No</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Nomor GR</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Tanggal</th>
                <th class="px-3 py-2 text-center text-xs font-bold text-purple-700">Jml Serial</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">User</th>
                <th class="px-3 py-2 text-center text-xs font-bold text-purple-700">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in grList.data" :key="row.id" class="hover:bg-purple-50 transition">
                <td class="px-3 py-2">{{ (grList.current_page - 1) * grList.per_page + idx + 1 }}</td>
                <td class="px-3 py-2 font-mono font-bold text-purple-700">{{ row.number }}</td>
                <td class="px-3 py-2">{{ formatDate(row.receive_date) }}</td>
                <td class="px-3 py-2 text-center font-semibold">{{ row.total_serials }}</td>
                <td class="px-3 py-2">{{ row.created_by_name || '-' }}</td>
                <td class="px-3 py-2 text-center space-x-2">
                  <a :href="`/outlet-serial-receive/${row.id}`"
                    class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 inline-block">
                    <i class="fa fa-eye"></i> Detail
                  </a>
                  <button @click="onDelete(row)"
                    class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                    <i class="fa fa-trash"></i> Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="grList.last_page > 1" class="mt-4 flex justify-center gap-1">
          <template v-for="link in grList.links" :key="link.label">
            <a v-if="link.url" :href="link.url"
              :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
              v-html="link.label"></a>
            <span v-else class="px-3 py-1 rounded text-sm bg-gray-50 text-gray-400" v-html="link.label"></span>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive } from 'vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  grList: Object,
  filters: Object,
})

const filters = reactive({
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
})

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function applyFilter() {
  router.get('/outlet-serial-receive', {
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
    search: filters.search || undefined,
  }, { preserveState: true })
}

function resetFilter() {
  filters.date_from = ''
  filters.date_to = ''
  filters.search = ''
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
    confirmButtonColor: '#d33',
  })

  if (!confirm.isConfirmed) return

  try {
    await axios.delete(`/outlet-serial-receive/${row.id}`)
    router.reload()
    Swal.fire('Berhasil', `GR ${row.number} berhasil dihapus.`, 'success')
  } catch (e) {
    const msg = e?.response?.data?.errors?.message || 'Gagal menghapus GR.'
    Swal.fire('Gagal', msg, 'error')
  }
}
</script>
