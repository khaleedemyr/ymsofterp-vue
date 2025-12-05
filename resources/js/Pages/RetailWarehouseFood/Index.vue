<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-warehouse text-blue-500"></i> Warehouse Retail Food
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-filter text-blue-500"></i> Filter Data
        </h3>
        
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input 
              type="text" 
              v-model="filters.search" 
              placeholder="No. transaksi, supplier, warehouse..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Payment Method -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran</label>
            <select 
              v-model="filters.payment_method" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua</option>
              <option value="cash">Cash</option>
              <option value="contra_bon">Contra Bon</option>
            </select>
          </div>

        </form>

        <!-- Filter Actions -->
        <div class="flex justify-between items-center mt-4">
          <div class="text-sm text-gray-600">
            <span v-if="hasActiveFilters" class="text-blue-600 font-medium">
              Filter aktif: {{ activeFiltersCount }}
            </span>
            <span v-else class="text-gray-500">
              Tidak ada filter aktif
            </span>
          </div>
          <div class="flex gap-2">
            <button 
              @click="clearFilters" 
              v-if="hasActiveFilters"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors"
            >
              <i class="fa-solid fa-times mr-1"></i> Reset Filter
            </button>
            <button 
              @click="applyFilters" 
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
            >
              <i class="fa-solid fa-search mr-1"></i> Terapkan Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Results Info -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <div class="flex justify-between items-center">
          <div class="text-sm text-blue-800">
            <i class="fa-solid fa-info-circle mr-1"></i>
            Menampilkan {{ props.retailWarehouseFoods.data.length }} dari {{ props.retailWarehouseFoods.total }} transaksi
            <span v-if="hasActiveFilters" class="ml-2 font-medium">
              (dengan filter aktif)
            </span>
          </div>
          <div v-if="hasActiveFilters" class="text-xs text-blue-600">
            <i class="fa-solid fa-filter mr-1"></i>
            Filter: {{ activeFiltersCount }} aktif
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. Transaksi</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Division</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Metode Pembayaran</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.retailWarehouseFoods.data.length">
                <td colspan="9" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.retailWarehouseFoods.data" :key="row.id">
                <td class="px-6 py-3">{{ formatDate(row.transaction_date) }}</td>
                <td class="px-6 py-3">{{ row.retail_number }}</td>
                <td class="px-6 py-3">{{ row.warehouse?.name || row.warehouse_name || '-' }}</td>
                <td class="px-6 py-3">{{ row.warehouse_division?.name || row.warehouse_division_name || '-' }}</td>
                <td class="px-6 py-3">{{ row.supplier?.name || row.supplier_name || '-' }}</td>
                <td class="px-6 py-3">
                  <span :class="{
                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-green-100 text-green-800': row.payment_method === 'cash',
                    'bg-blue-100 text-blue-800': row.payment_method === 'contra_bon'
                  }">
                    {{ row.payment_method === 'cash' ? 'Cash' : 'Contra Bon' }}
                  </span>
                </td>
                <td class="px-6 py-3 text-right">{{ formatRupiah(row.total_amount) }}</td>
                <td class="px-6 py-3">
                  <span :class="{
                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-yellow-100 text-yellow-800': row.status === 'draft',
                    'bg-green-100 text-green-800': row.status === 'approved'
                  }">
                    {{ row.status === 'draft' ? 'Draft' : 'Approved' }}
                  </span>
                </td>
                <td class="px-6 py-3">
                  <button class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(row.id)">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button 
                    v-if="canDelete" 
                    class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2" 
                    @click="onDelete(row)" 
                    :disabled="loadingId === row.id"
                    title="Hapus transaksi"
                  >
                    <span v-if="loadingId === row.id"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                    <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
                  </button>
                  <span 
                    v-else-if="!canDelete" 
                    class="inline-flex items-center text-xs text-gray-400 ml-2"
                    title="Hanya admin yang dapat menghapus transaksi"
                  >
                    <i class="fa fa-lock mr-1"></i> Hapus
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in props.retailWarehouseFoods.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
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
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ref, computed, watch } from 'vue'
import { debounce } from 'lodash'

const props = defineProps({
  user: Object,
  retailWarehouseFoods: Object,
  filters: Object
})

const loadingId = ref(null)

// Filter state
const filters = ref({
  search: props.filters?.search || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  payment_method: props.filters?.payment_method || ''
})

// Computed properties for filter status
const hasActiveFilters = computed(() => {
  return filters.value.search || 
         filters.value.date_from || 
         filters.value.date_to || 
         filters.value.payment_method
})

const activeFiltersCount = computed(() => {
  let count = 0
  if (filters.value.search) count++
  if (filters.value.date_from) count++
  if (filters.value.date_to) count++
  if (filters.value.payment_method) count++
  return count
})

// Check if user can delete (only admin with id_outlet = 1)
const canDelete = computed(() => {
  return props.user?.id_outlet === 1
})

// Debounced search function
const debouncedSearch = debounce(() => {
  applyFilters()
}, 500)

// Watch for search input changes
watch(() => filters.value.search, () => {
  debouncedSearch()
})

// Filter functions
function applyFilters() {
  const filterParams = {}
  
  if (filters.value.search) filterParams.search = filters.value.search
  if (filters.value.date_from) filterParams.date_from = filters.value.date_from
  if (filters.value.date_to) filterParams.date_to = filters.value.date_to
  if (filters.value.payment_method) filterParams.payment_method = filters.value.payment_method
  
  router.get('/retail-warehouse-food', filterParams, { 
    preserveState: true, 
    replace: true 
  })
}

function clearFilters() {
  filters.value = {
    search: '',
    date_from: '',
    date_to: '',
    payment_method: ''
  }
  router.get('/retail-warehouse-food', {}, { 
    preserveState: true, 
    replace: true 
  })
}

function goCreate() {
  router.visit('/retail-warehouse-food/create')
}

function goDetail(id) {
  router.visit(`/retail-warehouse-food/${id}`)
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true })
}

async function onDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus Transaksi?',
    text: `Yakin ingin menghapus transaksi ${row.retail_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  })

  if (!result.isConfirmed) return

  loadingId.value = row.id
  try {
    const res = await axios.delete(`/retail-warehouse-food/${row.id}`)
    if (res.data && res.data.message) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: res.data.message,
        timer: 1500,
        showConfirmButton: false
      })
      setTimeout(() => router.reload(), 1200)
    }
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal menghapus transaksi'
    })
  } finally {
    loadingId.value = null
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

