<template>
  <TransitionRoot appear :show="show" as="template">
    <Dialog as="div" @close="$emit('close')" class="relative z-[99999]">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <!-- Header -->
              <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-medium">Detail Purchase Order</h3>
                <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <!-- Loading State -->
              <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading...</span>
                </div>
              </div>

              <!-- Content -->
              <div v-else-if="po" class="space-y-6">
                <!-- Basic Info -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <div class="text-sm text-gray-600">PO Number</div>
                    <div class="font-medium">{{ po.po_number }}</div>
                  </div>
                  <div>
                    <div class="text-sm text-gray-600">Created Date</div>
                    <div class="font-medium">{{ formatDate(po.created_at) }}</div>
                  </div>
                  <div>
                    <div class="text-sm text-gray-600">Supplier</div>
                    <div class="font-medium">{{ po.supplier?.name }}</div>
                  </div>
                  <div>
                    <div class="text-sm text-gray-600">Status</div>
                    <div class="font-medium" :class="{
                      'text-yellow-600': po.status === 'DRAFT',
                      'text-green-600': po.status === 'APPROVED',
                      'text-red-600': po.status === 'REJECTED'
                    }">{{ po.status }}</div>
                  </div>
                </div>

                <!-- Items -->
                <div>
                  <h4 class="font-medium mb-3">Items</h4>
                  <div class="border rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Specifications</th>
                          <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Quantity</th>
                          <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                          <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="item in po.items" :key="item.id">
                          <td class="px-4 py-2">{{ item.item_name }}</td>
                          <td class="px-4 py-2 text-sm text-gray-500">{{ item.specifications || '-' }}</td>
                          <td class="px-4 py-2 text-center">{{ item.quantity }}</td>
                          <td class="px-4 py-2 text-right">{{ formatCurrency(item.supplier_price) }}</td>
                          <td class="px-4 py-2 text-right">{{ formatCurrency(item.quantity * item.supplier_price) }}</td>
                        </tr>
                      </tbody>
                      <tfoot class="bg-gray-50">
                        <tr>
                          <td colspan="4" class="px-4 py-2 text-right font-medium">Total:</td>
                          <td class="px-4 py-2 text-right font-medium">{{ formatCurrency(po.total_amount) }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="po.notes">
                  <h4 class="font-medium mb-2">Notes</h4>
                  <div class="bg-gray-50 p-4 rounded-lg text-sm">
                    {{ po.notes }}
                  </div>
                </div>

                <!-- Approval Status -->
                <div>
                  <h4 class="font-medium mb-3">Approval Status</h4>
                  <div class="space-y-3">
                    <div v-for="level in approvalLevels" :key="level.key" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                      <div class="flex-1">
                        <div class="font-medium">{{ level.label }}</div>
                        <div class="text-sm text-gray-500">
                          <span v-if="level.status === 'APPROVED'">Approved</span>
                          <span v-else-if="level.status === 'REJECTED'">Rejected</span>
                          <span v-else>Pending</span>
                          <span v-if="level.date"> ({{ formatDate(level.date) }})</span>
                        </div>
                        <div v-if="level.notes" class="text-xs text-gray-400 mt-1">Catatan: {{ level.notes }}</div>
                      </div>
                      <div v-if="canShowApprovalAction(level.key)" class="flex gap-2">
                        <button 
                          @click="openNotesModal(level.key, 'APPROVED')"
                          class="px-3 py-1 bg-green-100 text-green-600 rounded hover:bg-green-200"
                          :disabled="isApproving"
                        >
                          Approve
                        </button>
                        <button 
                          @click="openNotesModal(level.key, 'REJECTED')"
                          class="px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200"
                          :disabled="isApproving"
                        >
                          Reject
                        </button>
                      </div>
                      <div v-else>
                        <span :class="{
                          'text-green-600': level.status === 'APPROVED',
                          'text-red-600': level.status === 'REJECTED',
                          'text-gray-400': !level.status || level.status === 'PENDING'
                        }">
                          <i class="fas" :class="level.status === 'APPROVED' ? 'fa-check-circle' : (level.status === 'REJECTED' ? 'fa-times-circle' : 'fa-clock')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Invoice Section -->
                <div class="mt-6">
                  <h4 class="font-medium mb-2">Invoice</h4>
                  <div v-if="invoices.length === 0" class="text-gray-400 text-sm">Belum ada invoice.</div>
                  <div v-else>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-4 py-2 text-left">No. Invoice</th>
                          <th class="px-4 py-2 text-left">Tanggal</th>
                          <th class="px-4 py-2 text-left">File</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="inv in invoices" :key="inv.id">
                          <td class="px-4 py-2">{{ inv.invoice_number }}</td>
                          <td class="px-4 py-2">{{ formatDate(inv.invoice_date) }}</td>
                          <td class="px-4 py-2">
                            <a v-if="inv.invoice_file_path" :href="inv.invoice_file_path" target="_blank" class="text-blue-600 underline">Lihat File</a>
                            <span v-else>-</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>

  <!-- Modal Input Notes (teleport ke body) -->
  <teleport to="body">
    <div v-if="showNotesModal" class="fixed inset-0 z-[100001] flex items-center justify-center bg-black bg-opacity-40">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-medium mb-4">Tambahkan Catatan (Opsional)</h3>
        <textarea v-model="notesInput" rows="3" class="w-full border rounded p-2 mb-4" placeholder="Tulis catatan di sini..." autofocus></textarea>
        <div class="flex justify-end gap-2">
          <button @click="closeNotesModal" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
          <button @click="confirmApproval" :disabled="isApproving" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            {{ isApproving ? 'Menyimpan...' : 'Konfirmasi' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
} from '@headlessui/vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  poId: [String, Number],
  taskId: [String, Number],
});

const emit = defineEmits(['close', 'po-updated']);

const loading = ref(false);
const isApproving = ref(false);
const po = ref(null);
const showNotesModal = ref(false);
const notesInput = ref('');
const pendingApproval = ref(null); // {level, status}
const invoices = ref([]);

// User info (ganti sesuai auth Anda)
const user = usePage().props.auth.user;

// Helper: cek role
function canApproveLevel(level) {
  if (!user || user.status !== 'A') return false;
  if (user.id_role === '5af56935b011a' || user.id_jabatan === 217) return true; // superadmin/sekretaris
  if (level === 'purchasing_manager' && user.id_jabatan === 168) return true;
  if (level === 'gm_finance' && user.id_jabatan === 152) return true;
  if (level === 'coo' && user.id_jabatan === 151) return true;
  if (level === 'ceo' && user.id_jabatan === 149) return true;
  return false;
}

// Helper: cek apakah tombol approve/reject boleh muncul di level ini
function canShowApprovalAction(level) {
  if (!po.value) return false;
  if (!canApproveLevel(level)) return false;
  // Urutan approval
  const getStatus = (field) => {
    const val = po.value[field];
    return val === undefined || val === null ? 'PENDING' : val;
  };
  if (level === 'purchasing_manager') {
    return getStatus('purchasing_manager_approval') === 'PENDING';
  }
  if (level === 'gm_finance') {
    return getStatus('purchasing_manager_approval') === 'APPROVED' && getStatus('gm_finance_approval') === 'PENDING';
  }
  if (level === 'coo') {
    return getStatus('gm_finance_approval') === 'APPROVED' && getStatus('coo_approval') === 'PENDING';
  }
  if (level === 'ceo') {
    return getStatus('coo_approval') === 'APPROVED' && getStatus('ceo_approval') === 'PENDING' && po.value.total_amount >= 5000000;
  }
  return false;
}

// Helper: tampilkan CEO approval?
const showCEOApproval = computed(() => po.value && po.value.total_amount >= 5000000);

// Helper: label dan data approval
const approvalLevels = computed(() => [
  {
    key: 'purchasing_manager',
    label: 'Purchasing Manager',
    status: po.value?.purchasing_manager_approval,
    date: po.value?.purchasing_manager_approval_date,
    notes: po.value?.purchasing_manager_approval_notes,
  },
  {
    key: 'gm_finance',
    label: 'GM Finance',
    status: po.value?.gm_finance_approval,
    date: po.value?.gm_finance_approval_date,
    notes: po.value?.gm_finance_approval_notes,
  },
  {
    key: 'coo',
    label: 'COO',
    status: po.value?.coo_approval,
    date: po.value?.coo_approval_date,
    notes: po.value?.coo_approval_notes,
  },
  // CEO hanya jika total_amount >= 5jt
  ...(showCEOApproval.value ? [{
    key: 'ceo',
    label: 'CEO',
    status: po.value?.ceo_approval,
    date: po.value?.ceo_approval_date,
    notes: po.value?.ceo_approval_notes,
  }] : []),
]);

// Watch both show and poId changes
watch([() => props.show, () => props.poId], async ([newShow, newPoId]) => {
  console.log('Modal show:', newShow, 'PO ID:', newPoId, 'Task ID:', props.taskId);
  if (newShow && newPoId) {
    await fetchPODetails();
  } else {
    // Reset po data when modal is closed
    po.value = null;
  }
}, { immediate: true });

async function fetchPODetails() {
  loading.value = true;
  try {
    console.log('Fetching PO details for:', props.poId, 'Task ID:', props.taskId);
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-orders/${props.poId}`);
    console.log('PO details response:', res.data);
    po.value = res.data;
    // Fetch invoices for this PO
    const invRes = await axios.get(`/api/purchase-orders/${props.poId}/invoices`);
    invoices.value = invRes.data;
  } catch (error) {
    console.error('Error fetching PO details:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal mengambil detail PO: ' + (error.response?.data?.message || error.message),
      icon: 'error'
    });
    emit('close'); // Close modal on error
  } finally {
    loading.value = false;
  }
}

function openNotesModal(level, status) {
  pendingApproval.value = { level, status };
  notesInput.value = '';
  showNotesModal.value = true;
}

function closeNotesModal() {
  showNotesModal.value = false;
  pendingApproval.value = null;
  notesInput.value = '';
}

async function confirmApproval() {
  if (!pendingApproval.value) return;
  isApproving.value = true;
  try {
    await approve(pendingApproval.value.level, pendingApproval.value.status, notesInput.value);
    closeNotesModal();
  } finally {
    isApproving.value = false;
  }
}

async function approve(level, status, notes = '') {
  isApproving.value = true;
  try {
    await axios.post(`/api/maintenance-tasks/${props.taskId}/purchase-orders/${props.poId}/approve`, {
      level,
      status,
      notes
    });
    await fetchPODetails();
    emit('po-updated');
    Swal.fire('Sukses', 'Status PO berhasil diupdate', 'success');
  } catch (error) {
    console.error('Error approving PO:', error);
    Swal.fire('Error', error.response?.data?.message || 'Gagal mengupdate status PO', 'error');
  } finally {
    isApproving.value = false;
  }
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

console.log('User:', user);
watch(po, (val) => { console.log('PO:', val); });
</script> 