<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 md:px-8">
      <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl p-8">
        <div class="flex justify-between items-center mb-8">
          <h1 class="text-2xl font-bold flex items-center gap-2 text-blue-700">
            <i class="fa-solid fa-store text-blue-500"></i> Detail Retail Food
          </h1>
          <div class="flex gap-2">
            <button @click="goBack" class="btn btn-ghost px-4 py-2 rounded-lg">
              <i class="fa fa-arrow-left mr-2"></i> Kembali
            </button>
            <button v-if="canDelete" @click="confirmDelete" class="btn btn-error px-4 py-2 rounded-lg">
              <i class="fa fa-trash mr-2"></i> Hapus
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div>
            <div class="text-sm text-gray-500 mb-1">Tanggal</div>
            <div class="font-medium">{{ formatDate(props.retailFood.transaction_date) }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">No Transaksi</div>
            <div class="font-medium">{{ props.retailFood.transaction_number }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Outlet</div>
            <div class="font-medium">{{ props.retailFood.outlet?.nama_outlet }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Status</div>
            <div>
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                props.retailFood.status === 'approved' ? 'bg-green-100 text-green-800' :
                props.retailFood.status === 'rejected' ? 'bg-red-100 text-red-800' :
                'bg-yellow-100 text-yellow-800'
              ]">
                {{ formatStatus(props.retailFood.status) }}
              </span>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Metode Pembayaran</div>
            <div>
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                props.retailFood.payment_method === 'cash' ? 'bg-green-100 text-green-800' :
                'bg-blue-100 text-blue-800'
              ]">
                {{ props.retailFood.payment_method === 'cash' ? 'Cash' : 'Contra Bon' }}
              </span>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Supplier</div>
            <div class="font-medium">{{ props.retailFood.supplier?.name || '-' }}</div>
          </div>
        </div>

        <div class="mb-8">
          <div class="text-sm text-gray-500 mb-2">Items</div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in props.retailFood.items" :key="item.id">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.qty }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="px-3 py-2 text-right font-bold">Total:</td>
                  <td class="px-3 py-2 text-right font-bold">{{ formatRupiah(props.retailFood.total_amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div v-if="props.retailFood.notes" class="mb-8">
          <div class="text-sm text-gray-500 mb-1">Catatan</div>
          <div class="bg-gray-50 p-4 rounded-lg">{{ props.retailFood.notes }}</div>
        </div>

        <div v-if="props.retailFood.invoices && props.retailFood.invoices.length" class="mb-8">
          <div class="text-sm text-gray-500 mb-1">Bon/Invoice</div>
          <div class="flex flex-wrap gap-3">
            <div v-for="inv in props.retailFood.invoices" :key="inv.id" class="w-32 h-32 border rounded overflow-hidden flex items-center justify-center bg-gray-50">
              <a :href="`/storage/${inv.file_path}`" target="_blank" rel="noopener">
                <img :src="`/storage/${inv.file_path}`" class="object-contain w-full h-full hover:scale-110 transition-transform duration-200" />
              </a>
            </div>
          </div>
        </div>

        <div v-if="props.retailFood.status === 'pending' && canApprove" class="flex justify-end gap-2">
          <button @click="reject" class="btn btn-error px-6 py-2 rounded-lg">
            <i class="fa fa-times mr-2"></i> Tolak
          </button>
          <button @click="approve" class="btn btn-success px-6 py-2 rounded-lg">
            <i class="fa fa-check mr-2"></i> Setujui
          </button>
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
  retailFood: Object
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
  if (userRole.value === 'outlet' && props.retailFood.status === 'pending') return true
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

function formatStatus(status) {
  const statusMap = {
    pending: 'Menunggu Persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak'
  }
  return statusMap[status] || status
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function goBack() {
  router.visit('/retail-food')
}

async function approve() {
  try {
    const res = await axios.post(`/retail-food/${props.retailFood.id}/approve`)
    if (res.data.message) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: res.data.message,
        timer: 1500,
        showConfirmButton: false
      })
      router.reload()
    }
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal menyetujui transaksi'
    })
  }
}

async function reject() {
  try {
    const res = await axios.post(`/retail-food/${props.retailFood.id}/reject`)
    if (res.data.message) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: res.data.message,
        timer: 1500,
        showConfirmButton: false
      })
      router.reload()
    }
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal menolak transaksi'
    })
  }
}

async function confirmDelete() {
  const result = await Swal.fire({
    title: 'Hapus Transaksi?',
    text: 'Transaksi yang dihapus tidak dapat dikembalikan',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444'
  })

  if (result.isConfirmed) {
    try {
      const res = await axios.delete(`/retail-food/${props.retailFood.id}`)
      if (res.data.message) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: res.data.message,
          timer: 1500,
          showConfirmButton: false
        })
        router.visit('/retail-food')
      }
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: e.response?.data?.message || 'Gagal menghapus transaksi'
      })
    }
  }
}
</script> 