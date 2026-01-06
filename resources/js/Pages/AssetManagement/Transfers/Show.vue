<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-exchange-alt text-blue-500"></i> Transfer Detail
          </h1>
          <p class="text-gray-600 mt-1">Transfer #{{ transfer.id }}</p>
        </div>
        <Link
          href="/asset-management/transfers"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
        >
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset Information -->
        <div>
          <h2 class="text-lg font-bold text-gray-900 mb-4">Asset Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">Asset Code</label>
              <p class="text-sm font-semibold text-gray-900 mt-1">{{ transfer.asset?.asset_code }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Asset Name</label>
              <p class="text-sm font-semibold text-gray-900 mt-1">{{ transfer.asset?.name }}</p>
            </div>
          </div>
        </div>

        <!-- Transfer Information -->
        <div>
          <h2 class="text-lg font-bold text-gray-900 mb-4">Transfer Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">From Outlet</label>
              <p class="text-sm text-gray-900 mt-1">
                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                  {{ transfer.from_outlet?.name || 'Tidak Terikat' }}
                </span>
              </p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">To Outlet</label>
              <p class="text-sm text-gray-900 mt-1">
                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                  {{ transfer.to_outlet?.name || '-' }}
                </span>
              </p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Transfer Date</label>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(transfer.transfer_date) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Status</label>
              <p class="mt-1">
                <span :class="getStatusBadgeClass(transfer.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ transfer.status }}
                </span>
              </p>
            </div>
          </div>
        </div>

        <!-- Reason -->
        <div v-if="transfer.reason">
          <h2 class="text-lg font-bold text-gray-900 mb-4">Reason</h2>
          <p class="text-sm text-gray-700 whitespace-pre-line">{{ transfer.reason }}</p>
        </div>

        <!-- Notes -->
        <div v-if="transfer.notes">
          <h2 class="text-lg font-bold text-gray-900 mb-4">Notes</h2>
          <p class="text-sm text-gray-700 whitespace-pre-line">{{ transfer.notes }}</p>
        </div>

        <!-- Request Information -->
        <div>
          <h2 class="text-lg font-bold text-gray-900 mb-4">Request Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">Requested By</label>
              <p class="text-sm text-gray-900 mt-1">{{ transfer.requester?.name || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Requested At</label>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(transfer.created_at) }}</p>
            </div>
            <div v-if="transfer.approver">
              <label class="text-sm font-medium text-gray-500">Approved By</label>
              <p class="text-sm text-gray-900 mt-1">{{ transfer.approver.name }}</p>
            </div>
            <div v-if="transfer.approved_at">
              <label class="text-sm font-medium text-gray-500">Approved At</label>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(transfer.approved_at) }}</p>
            </div>
            <div v-if="transfer.rejection_reason">
              <label class="text-sm font-medium text-gray-500">Rejection Reason</label>
              <p class="text-sm text-red-600 mt-1">{{ transfer.rejection_reason }}</p>
            </div>
            <div v-if="transfer.completed_at">
              <label class="text-sm font-medium text-gray-500">Completed At</label>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(transfer.completed_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="transfer.status === 'Pending'" class="flex gap-3 pt-4 border-t border-gray-200">
          <button
            @click="approveTransfer"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-check"></i> Approve
          </button>
          <button
            @click="rejectTransfer"
            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-times"></i> Reject
          </button>
        </div>
        <div v-if="transfer.status === 'Approved'" class="flex gap-3 pt-4 border-t border-gray-200">
          <button
            @click="completeTransfer"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-check-circle"></i> Complete Transfer
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  transfer: Object,
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getStatusBadgeClass(status) {
  const classes = {
    'Pending': 'bg-yellow-100 text-yellow-800',
    'Approved': 'bg-green-100 text-green-800',
    'Completed': 'bg-blue-100 text-blue-800',
    'Rejected': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

async function approveTransfer() {
  const result = await Swal.fire({
    title: 'Approve Transfer?',
    text: `Transfer asset "${props.transfer.asset?.name}" dari ${props.transfer.from_outlet?.name} ke ${props.transfer.to_outlet?.name}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/transfers/${props.transfer.id}/approve`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Transfer berhasil di-approve', 'success');
        router.reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal approve transfer', 'error');
    }
  }
}

async function rejectTransfer() {
  const { value: reason } = await Swal.fire({
    title: 'Reject Transfer?',
    input: 'textarea',
    inputLabel: 'Alasan Rejection',
    inputPlaceholder: 'Masukkan alasan rejection...',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Reject',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value) {
        return 'Alasan rejection harus diisi!'
      }
    }
  });

  if (reason) {
    try {
      const response = await axios.post(`/asset-management/transfers/${props.transfer.id}/reject`, {
        rejection_reason: reason
      }, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Transfer berhasil di-reject', 'success');
        router.reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal reject transfer', 'error');
    }
  }
}

async function completeTransfer() {
  const result = await Swal.fire({
    title: 'Complete Transfer?',
    text: `Konfirmasi bahwa asset sudah dipindahkan ke ${props.transfer.to_outlet?.name}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Complete',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/transfers/${props.transfer.id}/complete`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Transfer berhasil di-complete', 'success');
        router.reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal complete transfer', 'error');
    }
  }
}
</script>

