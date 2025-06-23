<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-shopping-bag text-green-500"></i> Outlet Retail Non Food
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. Transaksi</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in props.retailNonFoods.data" :key="row.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(row.transaction_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ row.retail_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ row.outlet?.nama_outlet }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ row.warehouse_outlet_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                  {{ formatRupiah(row.total_amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(row.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                    {{ formatStatus(row.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex space-x-2">
                    <button @click="goDetail(row.id)" class="text-blue-600 hover:text-blue-900">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button v-if="canDelete(row)" @click="deleteItem(row)" class="text-red-600 hover:text-red-900">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link v-if="props.retailNonFoods.prev_page_url" :href="props.retailNonFoods.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
              Previous
            </Link>
            <Link v-if="props.retailNonFoods.next_page_url" :href="props.retailNonFoods.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
              Next
            </Link>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Showing
                <span class="font-medium">{{ props.retailNonFoods.from }}</span>
                to
                <span class="font-medium">{{ props.retailNonFoods.to }}</span>
                of
                <span class="font-medium">{{ props.retailNonFoods.total }}</span>
                results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <Link v-for="link in props.retailNonFoods.links" :key="link.label" :href="link.url" v-html="link.label" :class="[
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                  link.url === null ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-500 hover:bg-gray-50',
                  link.active ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300'
                ]"></Link>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  retailNonFoods: Object,
  user: Object
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')
const userRole = computed(() => page.props.auth?.user?.role || '')

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

function formatStatus(status) {
  const statusMap = {
    pending: 'Menunggu Persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak'
  }
  return statusMap[status] || status
}

function getStatusClass(status) {
  const classMap = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return classMap[status] || 'bg-gray-100 text-gray-800'
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function goCreate() {
  router.visit('/retail-non-food/create')
}

function goDetail(id) {
  router.visit(`/retail-non-food/${id}`)
}

function canDelete(row) {
  if (userRole.value === 'admin') return true
  if (userRole.value === 'approver') return true
  if (userRole.value === 'outlet' && row.status === 'pending') return true
  return false
}

async function deleteItem(row) {
  const confirm = await Swal.fire({
    title: 'Hapus Transaksi?',
    text: `Yakin ingin menghapus transaksi ${row.retail_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  })

  if (!confirm.isConfirmed) return

  try {
    const response = await axios.delete(`/retail-non-food/${row.id}`)
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: response.data.message,
      timer: 1500,
      showConfirmButton: false
    })
    router.visit('/retail-non-food')
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Terjadi kesalahan saat menghapus data'
    })
  }
}
</script> 