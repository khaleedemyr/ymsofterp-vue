<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Detail Return</h1>
          <p class="text-gray-600">{{ returnData.return_number }}</p>
        </div>
        <div class="flex items-center gap-4">
          <Link
            href="/head-office-return"
            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <i class="fa fa-arrow-left mr-2"></i> Kembali
          </Link>
          
          <template v-if="returnData.status === 'pending'">
            <button
              @click="approveReturn"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
              <i class="fa fa-check mr-2"></i> Approve
            </button>
            <button
              @click="showRejectModal"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              <i class="fa fa-times mr-2"></i> Reject
            </button>
          </template>
        </div>
      </div>

      <!-- Return Information -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Return</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">No. Return</label>
            <p class="text-sm text-gray-900">{{ returnData.return_number }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <p class="text-sm text-gray-900">{{ returnData.nama_outlet }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
            <p class="text-sm text-gray-900">{{ returnData.warehouse_name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">No. Good Receive</label>
            <p class="text-sm text-gray-900">{{ returnData.gr_number }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Return</label>
            <p class="text-sm text-gray-900">{{ formatDate(returnData.return_date) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <span :class="getStatusBadgeClass(returnData.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
              {{ getStatusText(returnData.status) }}
            </span>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dibuat Oleh</label>
            <p class="text-sm text-gray-900">{{ returnData.created_by_name }}</p>
            <p class="text-xs text-gray-500">{{ formatDate(returnData.created_at) }}</p>
          </div>
          <div v-if="returnData.approved_by_name">
            <label class="block text-sm font-medium text-gray-700 mb-1">Approved Oleh</label>
            <p class="text-sm text-gray-900 text-green-600">
              <i class="fa fa-check mr-1"></i>{{ returnData.approved_by_name }}
            </p>
            <p class="text-xs text-gray-500">{{ formatDate(returnData.approved_at) }}</p>
          </div>
          <div v-if="returnData.rejected_by_name">
            <label class="block text-sm font-medium text-gray-700 mb-1">Rejected Oleh</label>
            <p class="text-sm text-gray-900 text-red-600">
              <i class="fa fa-times mr-1"></i>{{ returnData.rejected_by_name }}
            </p>
            <p class="text-xs text-gray-500">{{ formatDate(returnData.rejection_at) }}</p>
          </div>
          <div v-if="returnData.notes">
            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
            <p class="text-sm text-gray-900">{{ returnData.notes }}</p>
          </div>
          <div v-if="returnData.rejection_reason">
            <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Reject</label>
            <p class="text-sm text-red-600">{{ returnData.rejection_reason }}</p>
          </div>
        </div>
      </div>

      <!-- Return Items -->
      <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Item yang Di-return</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Item
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  SKU
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Qty Return
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Unit
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ item.item_name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ item.sku }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ item.return_qty }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ item.unit_name }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="items.length === 0" class="text-center py-12">
          <i class="fa fa-inbox text-4xl text-gray-300 mb-4"></i>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada item</h3>
          <p class="text-gray-500">Belum ada item yang di-return</p>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showReject" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
            <i class="fa fa-times text-red-600"></i>
          </div>
          <div class="mt-2 text-center">
            <h3 class="text-lg font-medium text-gray-900">Reject Return</h3>
            <div class="mt-2 px-7 py-3">
              <p class="text-sm text-gray-500">Apakah Anda yakin ingin reject return ini?</p>
            </div>
          </div>
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Reject</label>
            <textarea
              v-model="rejectReason"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
              placeholder="Masukkan alasan reject..."
            ></textarea>
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button
              @click="closeRejectModal"
              class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Batal
            </button>
            <button
              @click="rejectReturn"
              :disabled="!rejectReason.trim()"
              class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  user: Object,
  return: Object,
  items: Array
})

const returnData = props.return
const items = props.items
const showReject = ref(false)
const rejectReason = ref('')

function getStatusBadgeClass(status) {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800'
    case 'approved':
      return 'bg-green-100 text-green-800'
    case 'rejected':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

function getStatusText(status) {
  switch (status) {
    case 'pending':
      return 'Pending'
    case 'approved':
      return 'Approved'
    case 'rejected':
      return 'Rejected'
    default:
      return status
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

async function approveReturn() {
  const result = await Swal.fire({
    title: 'Approve Return?',
    text: 'Apakah Anda yakin ingin approve return ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/head-office-return/${returnData.id}/approve`);
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
          timer: 1500,
          showConfirmButton: false
        });
        router.visit('/head-office-return');
      }
    } catch (error) {
      console.error('Error approving return:', error);
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: error.response?.data?.message || 'Gagal approve return'
      });
    }
  }
}

function showRejectModal() {
  rejectReason.value = ''
  showReject.value = true
}

function closeRejectModal() {
  showReject.value = false
  rejectReason.value = ''
}

async function rejectReturn() {
  if (!rejectReason.value.trim()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Perhatian',
      text: 'Alasan reject harus diisi'
    });
    return;
  }

  try {
    const response = await axios.post(`/head-office-return/${returnData.id}/reject`, {
      rejection_reason: rejectReason.value
    });
    
    if (response.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message,
        timer: 1500,
        showConfirmButton: false
      });
      router.visit('/head-office-return');
    }
  } catch (error) {
    console.error('Error rejecting return:', error);
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Gagal reject return'
    });
  }
}
</script>
