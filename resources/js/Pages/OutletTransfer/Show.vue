<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-right-left"></i> Detail Pindah Outlet
        </h1>
        <div class="flex gap-2">
          <Link
            :href="route('outlet-transfer.index')"
            class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-arrow-left mr-2"></i> Kembali
          </Link>
          <button
            v-if="transfer.status === 'draft'"
            @click="submitTransfer"
            class="bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-paper-plane mr-2"></i> Submit
          </button>
          <button
            v-if="transfer.status === 'submitted' && canApprove"
            @click="approveTransfer"
            class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-check mr-2"></i> Approve
          </button>
          <button
            v-if="transfer.status === 'draft'"
            @click="confirmDelete"
            class="bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-trash mr-2"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Transfer</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Nomor Transfer</label>
                <div class="mt-1 font-mono font-semibold text-blue-700">{{ transfer.transfer_number }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Tanggal Transfer</label>
                <div class="mt-1">{{ formatDate(transfer.transfer_date) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Status</label>
                <div class="mt-1">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getStatusClass(transfer.status)">
                    {{ getStatusText(transfer.status) }}
                  </span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Dibuat Oleh</label>
                <div class="mt-1">{{ transfer.creator?.nama_lengkap }}</div>
              </div>
              <div v-if="transfer.approver">
                <label class="block text-sm font-medium text-gray-600">Disetujui Oleh</label>
                <div class="mt-1">{{ transfer.approver?.nama_lengkap }}</div>
              </div>
              <div v-if="transfer.approval_at">
                <label class="block text-sm font-medium text-gray-600">Tanggal Approval</label>
                <div class="mt-1">{{ formatDate(transfer.approval_at) }}</div>
              </div>
              <div v-if="transfer.approval_notes">
                <label class="block text-sm font-medium text-gray-600">Catatan Approval</label>
                <div class="mt-1">{{ transfer.approval_notes }}</div>
              </div>
            </div>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Gudang</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Outlet Asal</label>
                <div class="mt-1">{{ getOutletName(transfer.warehouse_outlet_from?.outlet_id) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Warehouse Outlet Asal</label>
                <div class="mt-1">{{ transfer.warehouse_outlet_from?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Outlet Tujuan</label>
                <div class="mt-1">{{ getOutletName(transfer.warehouse_outlet_to?.outlet_id) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Warehouse Outlet Tujuan</label>
                <div class="mt-1">{{ transfer.warehouse_outlet_to?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Keterangan</label>
                <div class="mt-1">{{ transfer.notes || '-' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Detail Item</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in transfer.items" :key="item.id" class="hover:bg-blue-50">
                <td class="px-6 py-4">
                  <div class="font-medium text-gray-900">{{ item.item?.name }}</div>
                  <div class="text-sm text-gray-500">{{ item.item?.sku }}</div>
                </td>
                <td class="px-6 py-4">{{ item.quantity }}</td>
                <td class="px-6 py-4">{{ item.unit?.name || '-' }}</td>
                <td class="px-6 py-4">{{ item.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  transfer: Object,
  outlets: Object,
  user: Object,
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getOutletName(outletId) {
  if (!outletId || !props.outlets) return '-';
  return props.outlets[outletId]?.nama_outlet || '-';
}

function getStatusText(status) {
  const statusMap = {
    'draft': 'Draft',
    'submitted': 'Menunggu Approval',
    'approved': 'Disetujui',
    'rejected': 'Ditolak'
  };
  return statusMap[status] || status;
}

function getStatusClass(status) {
  const classMap = {
    'draft': 'bg-gray-100 text-gray-800',
    'submitted': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800'
  };
  return classMap[status] || 'bg-gray-100 text-gray-800';
}

const canApprove = computed(() => {
  if (!props.user || !props.transfer.warehouse_outlet_to?.name) return false;
  
  const userJabatan = props.user.id_jabatan;
  const userStatus = props.user.status;
  const isSuperadmin = props.user.id_role === '5af56935b011a' && userStatus === 'A';
  
  if (isSuperadmin) return true;
  
  const warehouseName = props.transfer.warehouse_outlet_to.name;
  
  switch (warehouseName) {
    case 'Kitchen':
      return [163, 174, 180, 345, 346, 347, 348, 349].includes(userJabatan) && userStatus === 'A';
    case 'Bar':
      return [175, 182, 323].includes(userJabatan) && userStatus === 'A';
    case 'Service':
      return [176, 322, 164, 321].includes(userJabatan) && userStatus === 'A';
    default:
      return false;
  }
});

function submitTransfer() {
  Swal.fire({
    title: 'Submit Transfer?',
    text: "Transfer akan dikirim untuk approval. Anda tidak dapat mengedit setelah submit.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Submit!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('outlet-transfer.submit', props.transfer.id))
        .then(() => {
          Swal.fire('Berhasil!', 'Transfer berhasil di-submit untuk approval.', 'success');
          router.reload({ preserveScroll: true });
        })
        .catch(() => {
          Swal.fire('Error!', 'Terjadi kesalahan saat submit transfer.', 'error');
        });
    }
  });
}

function approveTransfer() {
  Swal.fire({
    title: 'Approve Transfer?',
    text: "Transfer akan disetujui dan stock akan dipindahkan.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Approve!',
    cancelButtonText: 'Batal',
    input: 'textarea',
    inputPlaceholder: 'Catatan approval (opsional)',
    inputAttributes: {
      'aria-label': 'Catatan approval'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route('outlet-transfer.approve', props.transfer.id), {
        notes: result.value || ''
      }, {
        onSuccess: () => {
          Swal.fire(
            'Berhasil!',
            'Transfer berhasil di-approve.',
            'success'
          );
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Terjadi kesalahan saat approve transfer.',
            'error'
          );
        }
      });
    }
  });
}

function confirmDelete() {
  Swal.fire({
    title: 'Apakah Anda yakin?',
    text: "Data yang dihapus tidak dapat dikembalikan!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('outlet-transfer.destroy', props.transfer.id), {
        onSuccess: () => {
          Swal.fire(
            'Terhapus!',
            'Data berhasil dihapus.',
            'success'
          );
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Terjadi kesalahan saat menghapus data.',
            'error'
          );
        }
      });
    }
  });
}
</script> 