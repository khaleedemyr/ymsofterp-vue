<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="mb-6">
        <button @click="goBack" class="flex items-center text-gray-600 hover:text-gray-800 mb-4">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
          <i class="fa-solid fa-shopping-bag text-green-500"></i> Detail Retail Non Food
        </h1>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Header Info -->
        <div class="bg-green-50 px-6 py-4 border-b border-green-200">
          <div class="flex justify-between items-start">
            <div>
              <h2 class="text-xl font-bold text-gray-800">{{ props.retailNonFood.retail_number }}</h2>
              <p class="text-gray-600">{{ formatDate(props.retailNonFood.transaction_date) }}</p>
            </div>
            <div class="text-right">
              <div class="text-2xl font-bold text-green-600">{{ formatRupiah(props.retailNonFood.total_amount) }}</div>
              <span :class="getStatusClass(props.retailNonFood.status)" class="px-3 py-1 text-sm font-semibold rounded-full">
                {{ formatStatus(props.retailNonFood.status) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Transaction Details -->
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Transaksi</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Outlet:</span>
                  <span class="font-medium">{{ props.retailNonFood.outlet?.nama_outlet }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Warehouse Outlet:</span>
                  <span class="font-medium">{{ props.retailNonFood.warehouse_outlet_name || '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Dibuat oleh:</span>
                  <span class="font-medium">{{ props.retailNonFood.creator?.name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tanggal dibuat:</span>
                  <span class="font-medium">{{ formatDateTime(props.retailNonFood.created_at) }}</span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Catatan</h3>
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-700">{{ props.retailNonFood.notes || 'Tidak ada catatan' }}</p>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Items</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Item</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Harga</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, index) in props.retailNonFood.items" :key="item.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ item.qty }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ formatRupiah(item.price) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">{{ formatRupiah(item.subtotal) }}</td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="5" class="px-6 py-4 text-right font-bold text-gray-700">Total:</td>
                    <td class="px-6 py-4 text-right font-bold text-green-600">{{ formatRupiah(props.retailNonFood.total_amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
          <div v-if="props.retailNonFood.status === 'pending' && canApprove" class="flex justify-end gap-2">
            <button @click="reject" class="btn btn-error px-6 py-2 rounded-lg">
              <i class="fa fa-times mr-2"></i> Tolak
            </button>
            <button @click="approve" class="btn btn-success px-6 py-2 rounded-lg">
              <i class="fa fa-check mr-2"></i> Setujui
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  retailNonFood: Object
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')
const userRole = computed(() => page.props.auth?.user?.role || '')

const canApprove = computed(() => {
  return userRole.value === 'admin' || userRole.value === 'approver'
})

const canDelete = computed(() => {
  if (userRole.value === 'admin') return true
  if (userRole.value === 'approver') return true
  if (userRole.value === 'outlet' && props.retailNonFood.status === 'pending') return true
  return false
})

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

function formatDateTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
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

function goBack() {
  router.visit('/retail-non-food')
}

async function approve() {
  const confirm = await Swal.fire({
    title: 'Setujui Transaksi?',
    text: 'Apakah Anda yakin ingin menyetujui transaksi ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Setujui',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  })

  if (!confirm.isConfirmed) return

  try {
    await axios.patch(`/retail-non-food/${props.retailNonFood.id}/approve`)
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Transaksi berhasil disetujui',
      timer: 1500,
      showConfirmButton: false
    })
    router.visit(`/retail-non-food/${props.retailNonFood.id}`)
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Terjadi kesalahan saat menyetujui transaksi'
    })
  }
}

async function reject() {
  const confirm = await Swal.fire({
    title: 'Tolak Transaksi?',
    text: 'Apakah Anda yakin ingin menolak transaksi ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Tolak',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  })

  if (!confirm.isConfirmed) return

  try {
    await axios.patch(`/retail-non-food/${props.retailNonFood.id}/reject`)
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Transaksi berhasil ditolak',
      timer: 1500,
      showConfirmButton: false
    })
    router.visit(`/retail-non-food/${props.retailNonFood.id}`)
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Terjadi kesalahan saat menolak transaksi'
    })
  }
}
</script> 