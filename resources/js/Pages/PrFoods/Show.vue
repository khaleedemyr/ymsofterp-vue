<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  prFood: Object,
});

const user = usePage().props.auth?.user || {};

const isSuperadmin = computed(() =>
  user?.id_role === '5af56935b011a' && user?.status === 'A'
);

// Check if warehouse is MK1 or MK2
const isMKWarehouse = computed(() => {
  const warehouseName = props.prFood.warehouse?.name;
  return warehouseName === 'MK1 Hot Kitchen' || warehouseName === 'MK2 Cold Kitchen';
});

// Determine who can approve Assistant SSD Manager
const canApproveAssistantSSD = computed(() => {
  // Hanya untuk PR non-MK
  if (isMKWarehouse.value) return false;
  
  return ((user?.id_jabatan === 172 && user?.status === 'A') || isSuperadmin.value)
    && props.prFood.status === 'draft'
    && !props.prFood.assistant_ssd_manager_approved_at;
});

// Determine who can approve SSD Manager
const canApproveSSD = computed(() => {
  if (isMKWarehouse.value) {
    // For MK warehouses, Sous Chef MK (id_jabatan=179) can approve
    return ((user?.id_jabatan === 179 && user?.status === 'A') || isSuperadmin.value)
      && props.prFood.status === 'draft'
      && !props.prFood.ssd_manager_approved_at;
  } else {
    // For other warehouses, SSD Manager (id_jabatan=161) can approve
    // Tapi harus sudah di-approve asisten SSD manager terlebih dahulu
    return ((user?.id_jabatan === 161 && user?.status === 'A') || isSuperadmin.value)
      && props.prFood.status === 'draft'
      && props.prFood.assistant_ssd_manager_approved_at
      && !props.prFood.ssd_manager_approved_at;
  }
});

// COO approval dihilangkan
const canApproveCOO = computed(() => false);

// Get approver title based on warehouse
const getApproverTitle = computed(() => {
  return isMKWarehouse.value ? 'Sous Chef MK' : 'SSD Manager';
});

const assistantSsdNote = ref('');
const ssdNote = ref('');
// COO note tidak digunakan lagi
const cooNote = ref('');

async function approveAssistantSSD(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? `Approve PR?` : `Reject PR?`,
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('pr-foods.approve-assistant-ssd-manager', props.prFood.id), {
      approved,
      assistant_ssd_manager_note: note,
    });
  }
}

async function approveSSD(approved) {
  const approverTitle = getApproverTitle.value;
  const { value: note } = await Swal.fire({
    title: approved ? `Approve PR?` : `Reject PR?`,
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('pr-foods.approve-ssd-manager', props.prFood.id), {
      approved,
      ssd_manager_note: note,
    });
  }
}

// Fungsi approve COO dihapus (tidak digunakan)
</script>
<template>
  <AppLayout>
    <div class="max-w-3xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/pr-foods')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-file-invoice text-blue-500"></i> Detail PR Foods
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div><b>No. PR:</b> {{ prFood.pr_number }}</div>
          <div><b>Tanggal:</b> {{ new Date(prFood.tanggal).toLocaleDateString('id-ID') }}</div>
          <div><b>Warehouse:</b> {{ prFood.warehouse?.name }}</div>
          <div><b>Requester:</b> {{ prFood.requester?.nama_lengkap }}</div>
          <div><b>Status:</b> <span class="font-semibold">{{ prFood.status }}</span></div>
        </div>
        <div><b>Keterangan:</b> {{ prFood.description }}</div>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h2 class="text-lg font-bold mb-2">Detail Item</h2>
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Note</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tgl Kedatangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in prFood.items" :key="item.id">
              <td class="px-3 py-2">
                <div>{{ item.item?.name }}</div>
                <div v-if="item.stock_small !== undefined" class="text-xs text-gray-500 mt-1">
                  Stok:
                  <span v-if="item.unit_small">{{ Number(item.stock_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_small }}</span>
                  <span v-if="item.unit_medium"> | {{ Number(item.stock_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_medium }}</span>
                  <span v-if="item.unit_large"> | {{ Number(item.stock_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_large }}</span>
                </div>
              </td>
              <td class="px-3 py-2">{{ item.qty }}</td>
              <td class="px-3 py-2">{{ item.unit }}</td>
              <td class="px-3 py-2">{{ item.note }}</td>
              <td class="px-3 py-2">{{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h2 class="text-lg font-bold mb-2">Approval</h2>
        
        <!-- Approval Asisten SSD Manager (hanya untuk PR non-MK) -->
        <div v-if="!isMKWarehouse" class="mb-4">
          <b>Asisten SSD Manager:</b>
          <span v-if="prFood.assistant_ssd_manager_approved_at">
            <span class="text-green-600 font-semibold">Approved</span>
            oleh <b>{{ prFood.assistant_ssd_manager?.nama_lengkap || prFood.assistant_ssd_manager_approved_by }}</b>
            pada {{ new Date(prFood.assistant_ssd_manager_approved_at).toLocaleString('id-ID') }}
            <span v-if="prFood.assistant_ssd_manager_note">- Note: {{ prFood.assistant_ssd_manager_note }}</span>
          </span>
          <span v-else class="text-gray-500">Belum di-approve</span>
          
          <div v-if="canApproveAssistantSSD" class="mt-2">
            <button @click="approveAssistantSSD(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve (Asisten SSD Manager)</button>
            <button @click="approveAssistantSSD(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
          </div>
        </div>
        
        <!-- Approval SSD Manager / Sous Chef MK -->
        <div class="mb-2">
          <b>{{ getApproverTitle }}:</b>
          <span v-if="prFood.ssd_manager_approved_at">
            <span class="text-green-600 font-semibold">Approved</span>
            oleh <b>{{ prFood.ssd_manager?.nama_lengkap || prFood.ssd_manager_approved_by }}</b>
            pada {{ new Date(prFood.ssd_manager_approved_at).toLocaleString('id-ID') }}
            <span v-if="prFood.ssd_manager_note">- Note: {{ prFood.ssd_manager_note }}</span>
          </span>
          <span v-else class="text-gray-500">Belum di-approve</span>
        </div>
        
        <div v-if="canApproveSSD">
          <button @click="approveSSD(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve ({{ getApproverTitle }})</button>
          <button @click="approveSSD(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 