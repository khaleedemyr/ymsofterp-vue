<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck text-blue-500"></i> Good Receive Outlet
        </h1>
        <div class="flex gap-2">
          <template v-if="props.user_id_outlet === 1">
            <Link href="/delivery-orders-not-received" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold inline-flex items-center gap-2">
              <i class="fa-solid fa-chart-bar"></i> Report DO Belum GR
            </Link>
          </template>
          <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Baru
          </button>
        </div>
      </div>

      <!-- Filter & Searchbar -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-2 mb-4 items-end">
        <div>
          <label class="block text-xs font-bold mb-1">Cari</label>
          <input v-model="filterSearch" type="text" class="form-input rounded border px-2 py-1" placeholder="Cari nomor GR, DO, outlet..." />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Dari</label>
          <input v-model="filterFrom" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Sampai</label>
          <input v-model="filterTo" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-bold">Terapkan</button>
      </form>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nomor GR</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nomor DO</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.goodReceives.data.length">
                <td colspan="8" class="text-center py-10 text-blue-300">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.goodReceives.data" :key="row.id">
                <td class="px-6 py-3">{{ formatDate(row.receive_date) }}</td>
                <td class="px-6 py-3">{{ row.number }}</td>
                <td class="px-6 py-3">{{ row.outlet_name }}</td>
                <td class="px-6 py-3">{{ row.warehouse_outlet_name || '-' }}</td>
                <td class="px-6 py-3">{{ row.delivery_order_number }}</td>
                <td class="px-6 py-3">
                  <div class="flex flex-col">
                    <span class="font-medium text-gray-900">{{ row.creator_name || '-' }}</span>
                    <span class="text-xs text-gray-500">{{ formatDateTime(row.created_at) }}</span>
                  </div>
                </td>
                <td class="px-6 py-3">{{ row.status }}</td>
                <td class="px-6 py-3">
                  <button class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(row.id)">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <template v-if="props.canDelete">
                    <button v-if="!row.outlet_payment_id" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition ml-2" @click="goEdit(row.id)">
                      <i class="fa fa-edit mr-1"></i> Edit
                    </button>
                    <button class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2" @click="onDelete(row.id)" :disabled="loadingId === row.id">
                      <span v-if="loadingId === row.id"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                      <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="props.goodReceives && props.goodReceives.data && props.goodReceives.last_page > 1" class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow-sm items-center gap-1">
          <button 
            :disabled="props.goodReceives.current_page === 1" 
            @click="goToPage(props.goodReceives.current_page - 1)" 
            class="px-3 py-1 border bg-white text-blue-700 hover:bg-blue-100 rounded-l disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i class="fas fa-chevron-left"></i> Previous
          </button>
          
          <div class="flex items-center gap-1">
            <template v-for="page in getPageNumbers()" :key="page">
              <button
                v-if="page !== '...'"
                @click="goToPage(page)"
                :class="[
                  'px-3 py-1 border font-medium transition',
                  page === props.goodReceives.current_page
                    ? 'bg-blue-600 text-white'
                    : 'bg-white text-blue-700 hover:bg-blue-100'
                ]"
              >
                {{ page }}
              </button>
              <span v-else class="px-2 text-gray-400">...</span>
            </template>
          </div>
          
          <button 
            :disabled="props.goodReceives.current_page === props.goodReceives.last_page" 
            @click="goToPage(props.goodReceives.current_page + 1)" 
            class="px-3 py-1 border bg-white text-blue-700 hover:bg-blue-100 rounded-r disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next <i class="fas fa-chevron-right"></i>
          </button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, Link } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ref, computed } from 'vue'

const props = defineProps({
  goodReceives: Object,
  user_id_outlet: Number,
  filters: Object,
  outlets: Array,
  canDelete: Boolean
})

const loadingId = ref(null)
const filterOutlet = ref(props.filters?.outlet_id || '')
const filterSearch = ref(props.filters?.search || '')
const filterFrom = ref(props.filters?.from || '')
const filterTo = ref(props.filters?.to || '')

function applyFilter() {
  router.visit(route('outlet-food-good-receives.index', {
    outlet_id: filterOutlet.value || undefined,
    search: filterSearch.value || undefined,
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
  }))
}

function goToPage(page) {
  router.visit(route('outlet-food-good-receives.index', {
    outlet_id: filterOutlet.value || undefined,
    search: filterSearch.value || undefined,
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
    page: page
  }))
}

function getPageNumbers() {
  if (!props.goodReceives || !props.goodReceives.last_page) return []
  
  const current = props.goodReceives.current_page
  const last = props.goodReceives.last_page
  const pages = []
  
  if (last <= 7) {
    // Show all pages if 7 or less
    for (let i = 1; i <= last; i++) {
      pages.push(i)
    }
  } else {
    // Show first page
    pages.push(1)
    
    if (current > 3) {
      pages.push('...')
    }
    
    // Show pages around current
    const start = Math.max(2, current - 1)
    const end = Math.min(last - 1, current + 1)
    
    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
    
    if (current < last - 2) {
      pages.push('...')
    }
    
    // Show last page
    pages.push(last)
  }
  
  return pages
}

function goCreate() {
  router.visit(route('outlet-food-good-receives.create'))
}

function goDetail(id) {
  router.visit(route('outlet-food-good-receives.show', id))
}

function goEdit(id) {
  router.visit(route('outlet-food-good-receives.edit', id))
}

function onDelete(id) {
  Swal.fire({
    title: 'Yakin hapus data ini?',
    text: 'Data akan dihapus permanen!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(async (result) => {
    if (result.isConfirmed) {
      loadingId.value = id
      try {
        const res = await axios.delete(`/outlet-food-good-receives/${id}`)
        if (res.data && res.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Data berhasil dihapus!',
            timer: 1500,
            showConfirmButton: false
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
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: '2-digit', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script> 