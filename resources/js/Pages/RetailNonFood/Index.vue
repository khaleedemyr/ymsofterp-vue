<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-shopping-bag text-white text-xl"></i>
              </div>
              <span>Outlet Retail Non Food</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1 ml-14">Kelola transaksi retail non food outlet</p>
          </div>
          <button 
            @click="goCreate" 
            class="group relative inline-flex items-center gap-2 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold hover:from-green-600 hover:to-green-700 transform hover:-translate-y-0.5"
          >
            <i class="fa-solid fa-plus text-lg"></i>
            <span>Tambah Baru</span>
          </button>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <div class="p-1.5 bg-green-100 rounded-lg">
              <i class="fa-solid fa-filter text-green-600"></i>
            </div>
            Filter Data
          </h3>
          <div class="text-sm text-gray-500">
            <span v-if="hasActiveFilters" class="text-green-600 font-semibold">
              <i class="fa-solid fa-check-circle mr-1"></i>{{ activeFiltersCount }} filter aktif
            </span>
            <span v-else class="text-gray-400">
              <i class="fa-solid fa-info-circle mr-1"></i>Tidak ada filter aktif
            </span>
          </div>
        </div>
        
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Search -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-search mr-1 text-gray-400"></i>Cari
            </label>
            <input 
              type="text" 
              v-model="filters.search" 
              placeholder="No. transaksi, outlet..."
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>

          <!-- Date From -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-calendar-alt mr-1 text-gray-400"></i>Tanggal Dari
            </label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>

          <!-- Date To -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-calendar-check mr-1 text-gray-400"></i>Tanggal Sampai
            </label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>
        </form>

        <!-- Filter Actions -->
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
          <button 
            @click="clearFilters" 
            v-if="hasActiveFilters"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 border-2 border-gray-200 rounded-xl hover:bg-gray-200 hover:border-gray-300 transition-all duration-200"
          >
            <i class="fa-solid fa-times"></i>
            Reset Filter
          </button>
          <button 
            @click="applyFilters" 
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-green-500 to-green-600 rounded-xl hover:from-green-600 hover:to-green-700 shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
          >
            <i class="fa-solid fa-search"></i>
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- Results Info -->
      <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-xl p-4 mb-6 shadow-sm">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
          <div class="flex items-center gap-2 text-sm font-semibold text-green-800">
            <i class="fa-solid fa-info-circle text-lg"></i>
            <span>Menampilkan <span class="font-bold text-green-900">{{ props.retailNonFoods.data.length }}</span> dari <span class="font-bold text-green-900">{{ props.retailNonFoods.total }}</span> transaksi</span>
            <span v-if="hasActiveFilters" class="ml-2 px-2 py-0.5 bg-green-200 rounded-full text-xs">
              (dengan filter)
            </span>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar text-gray-400"></i>
                    Tanggal
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-hashtag text-gray-400"></i>
                    No. Transaksi
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-store text-gray-400"></i>
                    Outlet
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-tags text-gray-400"></i>
                    Category Budget
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-money-bill-wave text-gray-400"></i>
                    Total
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-info-circle text-gray-400"></i>
                    Status
                  </div>
                </th>
                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cog text-gray-400"></i>
                    Aksi
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="!props.retailNonFoods.data.length" class="hover:bg-gray-50">
                <td colspan="7" class="text-center py-16">
                  <div class="flex flex-col items-center gap-3">
                    <div class="p-4 bg-gray-100 rounded-full">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">Tidak ada data transaksi</p>
                    <p class="text-sm text-gray-400">Coba ubah filter atau tambahkan transaksi baru</p>
                  </div>
                </td>
              </tr>
              <tr 
                v-for="row in props.retailNonFoods.data" 
                :key="row.id"
                class="hover:bg-green-50/50 transition-colors duration-150 border-b border-gray-100"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">
                    {{ formatDate(row.transaction_date) }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-semibold text-gray-900 font-mono">
                    {{ row.retail_number }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900 font-medium">
                    {{ row.outlet?.nama_outlet || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-700">
                    {{ row.category_budget?.name || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-bold text-green-700">
                    {{ formatRupiah(row.total_amount) }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="{
                    'inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-full shadow-sm': true,
                    'bg-yellow-100 text-yellow-800 border border-yellow-200': row.status === 'pending',
                    'bg-green-100 text-green-800 border border-green-200': row.status === 'approved',
                    'bg-red-100 text-red-800 border border-red-200': row.status === 'rejected'
                  }">
                    <i :class="{
                      'fa-solid fa-clock mr-1.5': row.status === 'pending',
                      'fa-solid fa-check-circle mr-1.5': row.status === 'approved',
                      'fa-solid fa-times-circle mr-1.5': row.status === 'rejected'
                    }"></i>
                    {{ formatStatus(row.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center justify-center gap-2">
                    <button 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg hover:from-blue-600 hover:to-blue-700 shadow-sm hover:shadow transition-all duration-200 transform hover:scale-105" 
                      @click="goDetail(row.id)"
                    >
                      <i class="fa fa-eye"></i>
                      Detail
                    </button>
                    <button 
                      v-if="canDelete" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-r from-red-500 to-red-600 rounded-lg hover:from-red-600 hover:to-red-700 shadow-sm hover:shadow transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed" 
                      @click="onDelete(row)" 
                      :disabled="loadingId === row.id"
                      title="Hapus transaksi"
                    >
                      <span v-if="loadingId === row.id">
                        <i class="fa fa-spinner fa-spin"></i>
                      </span>
                      <span v-else>
                        <i class="fa fa-trash"></i>
                      </span>
                    </button>
                    <span 
                      v-else-if="!canDelete" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed"
                      title="Hanya admin yang dapat menghapus transaksi"
                    >
                      <i class="fa fa-lock"></i>
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex flex-wrap justify-end items-center gap-2 mt-6">
        <button
          v-for="link in props.retailNonFoods.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-4 py-2 rounded-xl border-2 text-sm font-semibold transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
          :class="[
            link.active 
              ? 'bg-gradient-to-r from-green-500 to-green-600 text-white border-green-600 shadow-lg' 
              : 'bg-white text-green-700 border-green-200 hover:bg-green-50 hover:border-green-300',
            !link.url ? 'cursor-not-allowed' : 'cursor-pointer'
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
import axios from 'axios'

const props = defineProps({
  user: Object,
  retailNonFoods: Object,
  filters: Object,
  canDelete: Boolean
})

const loadingId = ref(null)

// Filter state
const filters = ref({
  search: props.filters?.search || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || ''
})

// Computed properties for filter status
const hasActiveFilters = computed(() => {
  return filters.value.search || 
         filters.value.date_from || 
         filters.value.date_to
})

const activeFiltersCount = computed(() => {
  let count = 0
  if (filters.value.search) count++
  if (filters.value.date_from) count++
  if (filters.value.date_to) count++
  return count
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
  
  router.get('/retail-non-food', filterParams, { 
    preserveState: true, 
    replace: true 
  })
}

function clearFilters() {
  filters.value = {
    search: '',
    date_from: '',
    date_to: ''
  }
  router.get('/retail-non-food', {}, { 
    preserveState: true, 
    replace: true 
  })
}

function goCreate() {
  router.visit('/retail-non-food/create')
}

function goDetail(id) {
  router.visit(`/retail-non-food/${id}`)
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true })
}

function formatStatus(status) {
  const statusMap = {
    pending: 'Menunggu Persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak'
  }
  return statusMap[status] || status
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
    const res = await axios.delete(`/retail-non-food/${row.id}`)
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
