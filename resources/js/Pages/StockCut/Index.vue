<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-scissors text-blue-500"></i> Log Potong Stock
        </h1>
        <div class="flex gap-2">
          <Link :href="route('stock-cut.form')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fa-solid fa-plus mr-1"></i> Tambah Potong Stock
          </Link>
          <Link :href="route('stock-cut.menu-cost')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
            <i class="fa-solid fa-calculator mr-1"></i> Report Cost Menu
          </Link>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-xl p-6">
        <!-- Search and Filter Section -->
        <div class="mb-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search Bar -->
            <div class="md:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
              <div class="relative">
                <input 
                  type="text" 
                  v-model="searchQuery" 
                  placeholder="Cari outlet atau user..."
                  class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
                <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400"></i>
              </div>
            </div>
            
            <!-- Filter Tanggal Dari -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
              <input 
                type="date" 
                v-model="filterDateFrom" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            
            <!-- Filter Tanggal Sampai -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
              <input 
                type="date" 
                v-model="filterDateTo" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
          
          <!-- Reset Filter Button -->
          <div class="flex justify-end">
            <button 
              @click="resetFilters" 
              class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition text-sm"
            >
              <i class="fa-solid fa-rotate-left mr-1"></i> Reset Filter
            </button>
          </div>
        </div>

        <!-- Per Page Selector -->
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-700">Tampilkan:</label>
            <select 
              v-model="perPage" 
              @change="changePerPage(perPage)"
              class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
            <span class="text-sm text-gray-600">per halaman</span>
          </div>
          <div v-if="loading" class="text-sm text-gray-600">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data...
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-2 text-left">Tanggal</th>
                <th class="px-4 py-2 text-left">Outlet</th>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">User</th>
                <th class="px-4 py-2 text-left">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading" class="bg-gray-50">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 block"></i>
                  Memuat data...
                </td>
              </tr>
              <tr v-else-if="filteredLogs.length === 0" class="bg-gray-50">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
                  Tidak ada data yang ditemukan
                </td>
              </tr>
              <tr v-else v-for="log in filteredLogs" :key="log.id" class="hover:bg-gray-50">
                <td class="px-4 py-2">{{ log.tanggal }}</td>
                <td class="px-4 py-2">{{ log.outlet_name }}</td>
                <td class="px-4 py-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                        :class="getTypeBadgeClass(log.type_filter)">
                    {{ getTypeName(log.type_filter) }}
                  </span>
                </td>
                <td class="px-4 py-2">{{ log.user_name }}</td>
                <td class="px-4 py-2">
                  <button @click="rollback(log.id)" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">
                    <i class="fa-solid fa-undo"></i> Undo
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Menampilkan {{ filteredLogs.length }} dari {{ total }} data
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || loading"
              class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              <i class="fa-solid fa-chevron-left"></i> Sebelumnya
            </button>
            
            <div class="flex items-center gap-1">
              <template v-if="lastPage <= 7">
                <button
                  v-for="page in lastPage"
                  :key="page"
                  @click="changePage(page)"
                  :class="[
                    'px-3 py-1 border rounded-lg transition',
                    currentPage === page 
                      ? 'bg-blue-600 text-white border-blue-600' 
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                  :disabled="loading"
                >
                  {{ page }}
                </button>
              </template>
              <template v-else>
                <!-- Always show first page -->
                <button
                  @click="changePage(1)"
                  :class="[
                    'px-3 py-1 border rounded-lg transition',
                    currentPage === 1 
                      ? 'bg-blue-600 text-white border-blue-600' 
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                  :disabled="loading"
                >
                  1
                </button>
                
                <!-- Show ellipsis if current page is far from start -->
                <span v-if="currentPage > 3" class="px-2 text-gray-500">...</span>
                
                <!-- Show pages around current page -->
                <template v-for="page in getPageNumbers()" :key="page">
                  <button
                    v-if="page > 1 && page < lastPage"
                    @click="changePage(page)"
                    :class="[
                      'px-3 py-1 border rounded-lg transition',
                      currentPage === page 
                        ? 'bg-blue-600 text-white border-blue-600' 
                        : 'border-gray-300 hover:bg-gray-50'
                    ]"
                    :disabled="loading"
                  >
                    {{ page }}
                  </button>
                </template>
                
                <!-- Show ellipsis if current page is far from end -->
                <span v-if="currentPage < lastPage - 2" class="px-2 text-gray-500">...</span>
                
                <!-- Always show last page -->
                <button
                  @click="changePage(lastPage)"
                  :class="[
                    'px-3 py-1 border rounded-lg transition',
                    currentPage === lastPage 
                      ? 'bg-blue-600 text-white border-blue-600' 
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                  :disabled="loading"
                >
                  {{ lastPage }}
                </button>
              </template>
            </div>
            
            <button 
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === lastPage || loading"
              class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              Selanjutnya <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const logs = ref([])
const searchQuery = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const total = ref(0)
const lastPage = ref(1)
const loading = ref(false)

// Computed property untuk filtered logs (client-side filtering untuk search dan tanggal)
const filteredLogs = computed(() => {
  let result = logs.value

  // Filter berdasarkan search query (outlet name atau user name)
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(log => 
      (log.outlet_name && log.outlet_name.toLowerCase().includes(query)) ||
      (log.user_name && log.user_name.toLowerCase().includes(query))
    )
  }

  // Filter berdasarkan tanggal dari
  if (filterDateFrom.value) {
    result = result.filter(log => {
      if (!log.tanggal) return false
      const logDate = new Date(log.tanggal)
      const fromDate = new Date(filterDateFrom.value)
      fromDate.setHours(0, 0, 0, 0)
      return logDate >= fromDate
    })
  }

  // Filter berdasarkan tanggal sampai
  if (filterDateTo.value) {
    result = result.filter(log => {
      if (!log.tanggal) return false
      const logDate = new Date(log.tanggal)
      const toDate = new Date(filterDateTo.value)
      toDate.setHours(23, 59, 59, 999)
      return logDate <= toDate
    })
  }

  return result
})

// Watch untuk reload data saat page atau perPage berubah
watch([currentPage, perPage], () => {
  loadLogs()
})

async function loadLogs() {
  loading.value = true
  try {
    const res = await axios.get('/api/stock-cut/logs', {
      params: {
        page: currentPage.value,
        per_page: perPage.value
      }
    })
    logs.value = res.data.data || []
    total.value = res.data.total || 0
    lastPage.value = res.data.last_page || 1
    currentPage.value = res.data.current_page || 1
  } catch (error) {
    console.error('Error loading logs:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat data logs'
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadLogs()
})

function resetFilters() {
  searchQuery.value = ''
  filterDateFrom.value = ''
  filterDateTo.value = ''
  currentPage.value = 1
  loadLogs()
}

function changePage(page) {
  if (page >= 1 && page <= lastPage.value) {
    currentPage.value = page
  }
}

function changePerPage(newPerPage) {
  perPage.value = newPerPage
  currentPage.value = 1
}

function getPageNumbers() {
  const pages = []
  const start = Math.max(2, currentPage.value - 1)
  const end = Math.min(lastPage.value - 1, currentPage.value + 1)
  
  for (let i = start; i <= end; i++) {
    if (i > 1 && i < lastPage.value) {
      pages.push(i)
    }
  }
  
  return pages
}

function getTypeName(typeFilter) {
  if (!typeFilter || typeFilter === 'all' || typeFilter === '') {
    return 'Semua Type'
  } else if (typeFilter === 'food') {
    return 'Food'
  } else if (typeFilter === 'beverages') {
    return 'Beverages'
  }
  return typeFilter
}

function getTypeBadgeClass(typeFilter) {
  if (!typeFilter || typeFilter === 'all' || typeFilter === '') {
    return 'bg-purple-100 text-purple-800'
  } else if (typeFilter === 'food') {
    return 'bg-orange-100 text-orange-800'
  } else if (typeFilter === 'beverages') {
    return 'bg-blue-100 text-blue-800'
  }
  return 'bg-gray-100 text-gray-800'
}

async function rollback(id) {
  const result = await Swal.fire({
    title: 'Konfirmasi Rollback',
    text: 'Yakin ingin rollback potong stock ini? Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Rollback',
    cancelButtonText: 'Batal'
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(`/stock-cut/${id}`)
    
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Rollback berhasil dilakukan',
      timer: 2000,
      showConfirmButton: false
    })
    
    // Reload data
    await loadLogs()
  } catch (error) {
    console.error('Error rollback:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.error || 'Gagal melakukan rollback'
    })
  }
}
</script> 