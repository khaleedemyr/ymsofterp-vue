<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-right-left"></i> Pindah Outlet
        </h1>
        <Link
          :href="route('outlet-transfer.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Pindah Outlet
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor transfer/warehouse outlet..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus-border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse Outlet Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet Tujuan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse Outlet Tujuan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!transfers.data || !transfers.data.length">
              <td colspan="9" class="text-center py-10 text-gray-400">Belum ada data Pindah Outlet.</td>
            </tr>
            <tr v-for="tr in transfers.data" :key="tr.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ tr.transfer_number }}</td>
              <td class="px-6 py-3">{{ formatDate(tr.transfer_date) }}</td>
              <td class="px-6 py-3">{{ getOutletName(tr.warehouse_outlet_from?.outlet_id) }}</td>
              <td class="px-6 py-3">{{ tr.warehouse_outlet_from?.name }}</td>
              <td class="px-6 py-3">{{ getOutletName(tr.warehouse_outlet_to?.outlet_id) }}</td>
              <td class="px-6 py-3">{{ tr.warehouse_outlet_to?.name }}</td>
              <td class="px-6 py-3">
                <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getStatusClass(tr.status)">
                  {{ getStatusText(tr.status) }}
                </span>
              </td>
              <td class="px-6 py-3">{{ tr.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2 flex-wrap">
                  <button @click="goToDetail(tr.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button v-if="tr.status === 'draft'" @click="submitTransfer(tr.id)" class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-paper-plane mr-1"></i> Submit
                  </button>
                  <button v-if="tr.status === 'submitted' && canApprove(tr)" @click="approveTransfer(tr.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-check mr-1"></i> Approve
                  </button>
                  <button v-if="tr.status === 'draft' && props.canDelete" @click="confirmDelete(tr.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in transfers.links"
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

    <!-- Submit Modal (pilih approvers) -->
    <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Submit untuk Approval</h3>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Approvers (berurutan dari terendah ke tertinggi)</label>
          <select
            v-model="selectedApprovers"
            multiple
            size="8"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="u in (users || [])" :key="u.id" :value="u.id">
              {{ u.nama_lengkap }} - {{ u.jabatan?.nama_jabatan || '-' }}
            </option>
          </select>
          <p class="mt-2 text-xs text-gray-500">Jika ingin multi approval, pilih lebih dari 1 approver. Approver level tertinggi akan eksekusi pindah stok saat approve terakhir.</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="closeSubmitModal" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
            Batal
          </button>
          <button @click="submitForApproval" :disabled="submitting || selectedApprovers.length === 0" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50">
            <i v-if="submitting" class="fa fa-spinner fa-spin mr-2"></i>
            Submit
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  transfers: Object,
  filters: Object,
  outlets: Object,
  user: Object,
  users: Array,
  canDelete: Boolean,
});

const search = ref(props.filters?.search || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

const showSubmitModal = ref(false);
const selectedTransferId = ref(null);
const selectedApprovers = ref([]);
const submitting = ref(false);

watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    from.value = filters?.from || '';
    to.value = filters?.to || '';
  },
  { immediate: true }
);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getOutletName(outletId) {
  if (!outletId || !props.outlets) return '-';
  return props.outlets[outletId]?.nama_outlet || '-';
}

function debouncedSearch() {
  router.get('/outlet-transfer', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onDateChange() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function goToDetail(id) {
  router.visit(`/outlet-transfer/${id}`);
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

function getNextPendingFlow(transfer) {
  const flows = transfer?.approval_flows || [];
  const pending = flows.filter(f => f?.status === 'PENDING');
  pending.sort((a, b) => (a.approval_level ?? 0) - (b.approval_level ?? 0));
  return pending[0] || null;
}

function canApprove(transfer) {
  if (!props.user || !transfer.warehouse_outlet_to?.name) return false;
  
  const userJabatan = props.user.id_jabatan;
  const userStatus = props.user.status;
  const isSuperadmin = props.user.id_role === '5af56935b011a' && userStatus === 'A';
  
  if (isSuperadmin) return true;

  // New flow: only next approver can approve
  const nextFlow = getNextPendingFlow(transfer);
  if (nextFlow) {
    return nextFlow.approver_id == props.user.id;
  }
  
  const warehouseName = transfer.warehouse_outlet_to.name;
  
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
}

function submitTransfer(id) {
  if (!id) return;
  selectedTransferId.value = id;
  selectedApprovers.value = [];
  showSubmitModal.value = true;
}

function closeSubmitModal() {
  showSubmitModal.value = false;
  selectedTransferId.value = null;
  selectedApprovers.value = [];
}

async function submitForApproval() {
  if (!selectedTransferId.value) return;
  if (!selectedApprovers.value || selectedApprovers.value.length === 0) {
    Swal.fire('Error!', 'Pilih minimal 1 approver.', 'error');
    return;
  }
  submitting.value = true;
  try {
    await axios.post(route('outlet-transfer.submit', selectedTransferId.value), {
      approvers: selectedApprovers.value,
    });
    closeSubmitModal();
    Swal.fire('Berhasil!', 'Transfer berhasil di-submit untuk approval.', 'success');
    router.reload({ preserveScroll: true });
  } catch (e) {
    Swal.fire('Error!', 'Terjadi kesalahan saat submit transfer.', 'error');
  } finally {
    submitting.value = false;
  }
}

function approveTransfer(id) {
  if (!id) return;
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
      router.post(route('outlet-transfer.approve', id), {
        action: 'approve',
        comments: result.value || ''
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

function confirmDelete(id) {
  if (!id) return;
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
      router.delete(route('outlet-transfer.destroy', id), {
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