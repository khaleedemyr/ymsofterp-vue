<template>
  <AppLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4">
          <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
          <button 
            @click="showPreview = true" 
            class="ml-4 px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
            :disabled="po.status !== 'approved'"
            :class="po.status !== 'approved' ? 'opacity-50 cursor-not-allowed' : ''"
            title="PO harus berstatus approved untuk preview"
          >
            <i class="fas fa-print mr-2"></i> Preview PO
          </button>
        </div>
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-6">
              Detail Purchase Order Foods
            </h2>
            <!-- Header Info -->
            <div class="grid grid-cols-2 gap-4 mb-6">
              <div>
                <h3 class="text-lg font-semibold mb-2">Informasi PO</h3>
                <div class="space-y-2">
                  <p><span class="font-medium">Nomor PO:</span> {{ po.number }}</p>
                  <p><span class="font-medium">Tanggal:</span> {{ formatDate(po.date) }}</p>
                  <p><span class="font-medium">Status:</span> 
                    <span :class="getStatusClass(po.status)">{{ po.status }}</span>
                  </p>
                  <p><span class="font-medium">Supplier:</span> {{ po.supplier?.name }}</p>
                  <p><span class="font-medium">Tanggal Kedatangan:</span> {{ formatDate(po.arrival_date) }}</p>
                  <p><span class="font-medium">Dibuat oleh:</span> {{ po.creator?.nama_lengkap }}</p>
                </div>
              </div>
              <div>
                <h3 class="text-lg font-semibold mb-2">Catatan</h3>
                <p class="text-gray-600">{{ po.notes || '-' }}</p>
              </div>
            </div>

            <!-- RO Supplier Information -->
            <div v-if="roSupplierInfo" class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
              <h3 class="text-lg font-semibold mb-3 text-green-800">Informasi RO Supplier</h3>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <p><span class="font-medium text-green-700">Nomor RO:</span> {{ roSupplierInfo.ro_number }}</p>
                  <p><span class="font-medium text-green-700">Tanggal RO:</span> {{ formatDate(roSupplierInfo.ro_date) }}</p>
                  <p><span class="font-medium text-green-700">Outlet:</span> {{ roSupplierInfo.outlet_name }}</p>
                  <p><span class="font-medium text-green-700">Warehouse Outlet:</span> {{ roSupplierInfo.warehouse_outlet_name }}</p>
                </div>
                <div class="space-y-2">
                  <p><span class="font-medium text-green-700">Pembuat RO:</span> {{ roSupplierInfo.ro_creator }}</p>
                  <p><span class="font-medium text-green-700">Deskripsi RO:</span> {{ roSupplierInfo.ro_description || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="mb-6">
              <h3 class="text-lg font-semibold mb-4">Daftar Item</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in po.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.item?.name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.unit?.name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.price) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.total) }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="bg-gray-50">
                      <td colspan="4" class="px-6 py-4 text-right font-medium">Subtotal:</td>
                      <td class="px-6 py-4 font-medium">{{ formatRupiah(po.subtotal || calculateTotal()) }}</td>
                    </tr>
                    <tr v-if="po.ppn_enabled" class="bg-gray-50">
                      <td colspan="4" class="px-6 py-4 text-right font-medium text-blue-600">PPN (11%):</td>
                      <td class="px-6 py-4 font-medium text-blue-600">{{ formatRupiah(po.ppn_amount || 0) }}</td>
                    </tr>
                    <tr class="bg-gray-100">
                      <td colspan="4" class="px-6 py-4 text-right font-bold text-lg">Grand Total:</td>
                      <td class="px-6 py-4 font-bold text-lg text-green-600">{{ formatRupiah(po.grand_total || calculateGrandTotal()) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <!-- Approval Section -->
            <div v-if="canApproveCurrent" class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Approval</h3>
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600 mb-3">
                  Anda adalah approver aktif di
                  <strong>Level {{ nextPendingFlow?.approval_level }}</strong>.
                </p>
                <button @click="approvePO(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve</button>
                <button @click="approvePO(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
              </div>
            </div>

            <!-- Approval History -->
            <div class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Riwayat Approval</h3>
              <div v-if="approvalFlows.length" class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg" v-for="flow in approvalFlows" :key="flow.id">
                  <h4 class="font-medium mb-2">Level {{ flow.approval_level }} - {{ flow.approver?.nama_lengkap || flow.approver_id }}</h4>
                  <div v-if="(flow.status || '').toUpperCase() === 'APPROVED'" class="text-green-700">
                    Approved pada {{ formatDateTime(flow.approved_at) }}
                    <span v-if="flow.comments">- Note: {{ flow.comments }}</span>
                  </div>
                  <div v-else-if="(flow.status || '').toUpperCase() === 'REJECTED'" class="text-red-700">
                    Rejected pada {{ formatDateTime(flow.rejected_at) }}
                    <span v-if="flow.comments">- Note: {{ flow.comments }}</span>
                  </div>
                  <div v-else class="text-gray-500">Menunggu approval</div>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500">Approval flow belum diatur.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <PrintPreviewModal 
      :show="showPreview"
      :po="po"
      @close="showPreview = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'
import PrintPreviewModal from './PrintPreviewModal.vue'

const props = defineProps({
  po: {
    type: Object,
    required: true
  },
  roSupplierInfo: {
    type: Object,
    default: null
  },
  user: {
    type: Object,
    required: true
  }
})

const approvalNote = ref('')
const showPreview = ref(false)

const isSuperadmin = computed(() =>
  props.user.id_role === '5af56935b011a' && props.user.status === 'A'
)
const approvalFlows = computed(() =>
  Array.isArray(props.po.approval_flows) ? props.po.approval_flows : (Array.isArray(props.po.approvalFlows) ? props.po.approvalFlows : [])
)
const nextPendingFlow = computed(() => {
  const pending = approvalFlows.value
    .filter((f) => (f.status || '').toString().toUpperCase() === 'PENDING')
    .sort((a, b) => Number(a.approval_level || 0) - Number(b.approval_level || 0))
  return pending.length ? pending[0] : null
})
const canApproveCurrent = computed(() => {
  if (!nextPendingFlow.value) return false
  if (props.po.status === 'approved' || props.po.status === 'rejected') return false
  return isSuperadmin.value || String(nextPendingFlow.value.approver_id) === String(props.user.id)
})

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const formatDateTime = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID')
}

const getStatusClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-blue-100 text-blue-800',
    pending_gm_finance: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return `px-2 py-1 rounded-full text-xs font-medium ${classes[status] || classes.draft}`
}

const calculateTotal = () => {
  return props.po.items.reduce((sum, item) => sum + (Number(item.total) || 0), 0)
}

const calculateGrandTotal = () => {
  const subtotal = calculateTotal();
  if (props.po.ppn_enabled) {
    const ppnAmount = subtotal * 0.11; // 11% PPN
    return subtotal + ppnAmount;
  }
  return subtotal;
};

const formatRupiah = (value) => {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

async function approvePO(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? 'Approve PO?' : 'Reject PO?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    try {
      const response = await axios.post(route('po-foods.approve', props.po.id), { approved, note });
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: approved ? 'PO berhasil diapprove!' : 'PO berhasil direject!',
        });
        router.reload();
      }
    } catch (e) {
      Swal.fire('Gagal', 'Terjadi kesalahan saat approve', 'error');
    }
  }
}

function goBack() {
  try {
    const savedFilters = sessionStorage.getItem('po-foods-filters');
    if (savedFilters) {
      const filters = JSON.parse(savedFilters);
      const queryParams = new URLSearchParams();
      
      if (filters.search) queryParams.append('search', filters.search);
      if (filters.status) queryParams.append('status', filters.status);
      if (filters.from) queryParams.append('from', filters.from);
      if (filters.to) queryParams.append('to', filters.to);
      if (filters.perPage) queryParams.append('perPage', filters.perPage);
      
      const queryString = queryParams.toString();
      const url = queryString ? `/po-foods?${queryString}` : '/po-foods';
      router.visit(url);
    } else {
      router.visit('/po-foods');
    }
  } catch (error) {
    console.error('Error restoring filters:', error);
    router.visit('/po-foods');
  }
}
</script>