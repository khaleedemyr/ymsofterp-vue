<template>
  <AppLayout title="Internal Warehouse Transfer">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Internal Warehouse Transfer
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <!-- Header with Create Button -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900">Internal Warehouse Transfer List</h3>
              <Link
                :href="route('internal-warehouse-transfer.create')"
                class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition"
              >
                Create New Transfer
              </Link>
            </div>
          </div>

          <!-- Search and Filters -->
          <div class="p-6 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input
                  v-model="search"
                  type="text"
                  placeholder="Search by transfer number or notes..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @input="debouncedSearch"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input
                  v-model="from"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @change="debouncedSearch"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                <input
                  v-model="to"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @change="debouncedSearch"
                />
              </div>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Departemen Asal</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Departemen Tujuan</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="tr in transfers.data" :key="tr.id" class="hover:bg-gray-50">
                  <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ tr.transfer_number }}</td>
                  <td class="px-6 py-3">{{ formatDate(tr.transfer_date) }}</td>
                  <td class="px-6 py-3">{{ getOutletName(tr.outlet_id) }}</td>
                  <td class="px-6 py-3">{{ tr.warehouse_outlet_from?.name }}</td>
                  <td class="px-6 py-3">{{ tr.warehouse_outlet_to?.name }}</td>
                  <td class="px-6 py-3">{{ tr.creator?.nama_lengkap }}</td>
                  <td class="px-6 py-3">
                    <div class="flex gap-2">
                      <button @click="goToDetail(tr.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                        <i class="fa fa-eye mr-1"></i> Detail
                      </button>
                      <button v-if="props.canDelete" @click="confirmDelete(tr.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                        <i class="fa fa-trash mr-1"></i> Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="px-6 py-4 border-t border-gray-200">
            <Pagination :links="transfers.links" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  transfers: Object,
  filters: Object,
  outlets: Object,
  canDelete: Boolean,
})

const search = ref(props.filters?.search || '')
const from = ref(props.filters?.from || '')
const to = ref(props.filters?.to || '')

// Debounced search function
let searchTimeout
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    router.get('/internal-warehouse-transfer', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true })
  }, 300)
}

// Format date
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

// Get outlet name
const getOutletName = (outletId) => {
  if (!outletId || !props.outlets) return '-'
  return props.outlets[outletId]?.nama_outlet || '-'
}

// Navigation functions
const goToDetail = (id) => {
  router.visit(`/internal-warehouse-transfer/${id}`)
}

const confirmDelete = (id) => {
  if (confirm('Are you sure you want to delete this transfer?')) {
    router.delete(route('internal-warehouse-transfer.destroy', id), {
      onSuccess: () => {
        // Success message will be handled by the controller
      },
      onError: (errors) => {
        console.error('Error deleting transfer:', errors)
      }
    })
  }
}
</script>
