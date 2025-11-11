<template>
  <Head title="Butcher Process" />

  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-box text-blue-500"></i> Butcher Process
        </h1>
        <Link
          :href="route('butcher-processes.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Create New
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          type="text"
          placeholder="Search by number, GR number, or warehouse..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="filters.from" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="From date" />
        <span>-</span>
        <input type="date" v-model="filters.to" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="To date" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Number</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Process Date</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Jam</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">GR Number</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="butcherProcesses.data.length === 0">
              <td colspan="7" class="text-center py-10 text-blue-300">No butcher process data.</td>
            </tr>
            <tr v-for="process in butcherProcesses.data" :key="process.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ process.number }}</td>
              <td class="px-6 py-3">{{ formatDate(process.process_date) }}</td>
              <td class="px-6 py-3">{{ formatTime(process.created_at) }}</td>
              <td class="px-6 py-3">{{ process.gr_number }}</td>
              <td class="px-6 py-3">{{ process.warehouse_name }}</td>
              <td class="px-6 py-3">{{ process.created_by_name }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <Link :href="route('butcher-processes.show', process.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Detail
                  </Link>
                  <button @click="confirmDelete(process.id)" class="inline-flex items-center btn btn-xs bg-blue-200 text-blue-800 hover:bg-blue-400 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in butcherProcesses.links"
          :key="link.label"
          :disabled="!link.url"
          @click="() => link.url && router.visit(link.url, { preserveState: true, replace: true })"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  butcherProcesses: Object,
  filters: Object
})

const search = ref(props.filters.search || '')
const filters = ref({
  from: props.filters.from || '',
  to: props.filters.to || ''
})

const debouncedSearch = debounce(() => {
  router.get(
    route('butcher-processes.index'),
    { search: search.value, from: filters.value.from, to: filters.value.to },
    { preserveState: true, replace: true }
  )
}, 400)

watch([search, filters], () => {
  debouncedSearch()
})

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

const formatTime = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}

const loadingDelete = ref(false)

function confirmDelete(id) {
  Swal.fire({
    title: 'Delete Butcher Process?',
    text: 'Data dan inventory akan di-rollback. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    cancelButtonText: 'No',
    reverseButtons: true,
    focusCancel: true,
    preConfirm: () => {
      loadingDelete.value = true
      return axios.delete(`/butcher-processes/${id}`)
        .then(res => {
          if (res.data.success) {
            Swal.fire('Deleted!', 'Data berhasil dihapus dan inventory di-rollback.', 'success')
            router.reload()
          } else {
            Swal.fire('Gagal', res.data.message || 'Gagal menghapus data', 'error')
          }
        })
        .catch(err => {
          Swal.fire('Gagal', err.response?.data?.message || 'Gagal menghapus data', 'error')
        })
        .finally(() => {
          loadingDelete.value = false
        })
    },
    allowOutsideClick: () => !Swal.isLoading()
  })
}
</script> 