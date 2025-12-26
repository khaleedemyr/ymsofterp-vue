<template>
  <AppLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4 flex justify-between items-center">
          <button @click="$inertia.visit('/contra-bons')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
          <button 
            v-if="canEdit"
            @click="$inertia.visit(`/contra-bons/${contraBon.id}/edit`)" 
            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 font-semibold flex items-center gap-2"
          >
            <i class="fa fa-pencil-alt"></i> Edit
          </button>
        </div>
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-6">
              Detail Contra Bon
            </h2>
            <!-- Header Info -->
            <div class="grid grid-cols-2 gap-4 mb-6">
              <div>
                <h3 class="text-lg font-semibold mb-2">Informasi Contra Bon</h3>
                <div class="space-y-2">
                  <p><span class="font-medium">Nomor:</span> {{ contraBon.number }}</p>
                  <p><span class="font-medium">Tanggal:</span> {{ formatDate(contraBon.date) }}</p>
                  <p><span class="font-medium">No Invoice Supplier:</span> {{ contraBon.supplier_invoice_number || '-' }}</p>
                  <p><span class="font-medium">Status:</span> 
                    <span :class="getStatusClass(contraBon.status)">{{ contraBon.status }}</span>
                  </p>
                  <p><span class="font-medium">Supplier:</span> {{ contraBon.supplier?.name }}</p>
                  <p><span class="font-medium">Dibuat oleh:</span> {{ contraBon.creator?.nama_lengkap }}</p>
                  <p><span class="font-medium">Source Type:</span></p>
                  <div v-if="contraBon.source_types && contraBon.source_types.length > 0" class="flex flex-wrap gap-1 mt-1">
                    <span 
                      v-for="sourceType in contraBon.source_types" 
                      :key="sourceType"
                      :class="{
                        'bg-blue-100 text-blue-700 border border-blue-300': sourceType === 'PR Foods',
                        'bg-green-100 text-green-700 border border-green-300': sourceType === 'RO Supplier',
                        'bg-purple-100 text-purple-700 border border-purple-300': sourceType === 'Retail Food',
                        'bg-orange-100 text-orange-700 border border-orange-300': sourceType === 'Warehouse Retail Food',
                        'bg-gray-100 text-gray-700 border border-gray-300': sourceType === 'Unknown',
                      }" 
                      class="px-2 py-1 rounded-full text-xs font-semibold">
                      {{ sourceType === 'PR Foods' ? 'PRF' : sourceType === 'Retail Food' ? 'RF' : sourceType === 'Warehouse Retail Food' ? 'RWF' : sourceType }}
                    </span>
                  </div>
                  <span v-else :class="{
                    'bg-blue-100 text-blue-700': contraBon.source_type_display === 'PR Foods',
                    'bg-green-100 text-green-700': contraBon.source_type_display === 'RO Supplier',
                    'bg-purple-100 text-purple-700': contraBon.source_type_display === 'Retail Food',
                    'bg-orange-100 text-orange-700': contraBon.source_type_display === 'Warehouse Retail Food',
                    'bg-gray-100 text-gray-700': contraBon.source_type_display === 'Unknown',
                  }" class="px-2 py-1 rounded-full text-xs font-semibold inline-block mt-1">
                    {{ contraBon.source_type_display || (contraBon.source_type === 'purchase_order' ? 'PO/GR' : 'Retail Food') }}
                  </span>
                  <div v-if="contraBon.source_numbers && contraBon.source_numbers.length > 0" class="mt-2">
                    <p><span class="font-medium">Source Numbers:</span></p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span v-for="number in contraBon.source_numbers" :key="number" 
                            :class="getSourceNumberBadgeClass(number, contraBon.source_types)"
                            class="text-xs px-2 py-1 rounded-full font-semibold border">
                        {{ number }}
                      </span>
                    </div>
                  </div>
                  <div v-if="contraBon.source_outlets && contraBon.source_outlets.length > 0">
                    <p><span class="font-medium">Outlet:</span></p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span v-for="outlet in contraBon.source_outlets" :key="outlet" 
                            class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                        {{ outlet }}
                      </span>
                    </div>
                  </div>
                  <!-- Discount Info -->
                  <div v-if="contraBon.po_discount_info && (contraBon.po_discount_info.discount_total_percent > 0 || contraBon.po_discount_info.discount_total_amount > 0)" class="mt-3 pt-3 border-t border-gray-200">
                    <p class="text-sm font-semibold text-blue-800 mb-1">Informasi Diskon PO:</p>
                    <div v-if="contraBon.po_discount_info.discount_total_percent > 0" class="text-xs">
                      Diskon Total: <span class="font-semibold text-red-600">{{ contraBon.po_discount_info.discount_total_percent }}%</span>
                    </div>
                    <div v-if="contraBon.po_discount_info.discount_total_amount > 0" class="text-xs">
                      Diskon Total: <span class="font-semibold text-red-600">{{ formatRupiah(contraBon.po_discount_info.discount_total_amount) }}</span>
                    </div>
                    <div class="text-xs mt-1">
                      Subtotal PO: <span class="font-semibold">{{ formatRupiah(contraBon.po_discount_info.subtotal) }}</span>
                    </div>
                    <div class="text-xs">
                      Grand Total PO: <span class="font-semibold text-green-600">{{ formatRupiah(contraBon.po_discount_info.grand_total) }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <h3 class="text-lg font-semibold mb-2">Catatan</h3>
                <p class="text-gray-600">{{ contraBon.notes || '-' }}</p>
              </div>
            </div>
            <!-- Tampilkan gambar jika ada -->
            <div v-if="contraBon.image_path" class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Scan/Foto Contra Bon Fisik</label>
              <img
                :src="`/storage/${contraBon.image_path}`"
                alt="Scan Contra Bon"
                class="max-w-xs rounded shadow cursor-pointer"
                @click="showLightbox = true"
              />
              <VueEasyLightbox
                :visible="showLightbox"
                :imgs="[`/storage/${contraBon.image_path}`]"
                @hide="showLightbox = false"
              />
            </div>

            <!-- Approval Section -->
            <div v-if="canApproveFinanceManager || canApproveGMFinance" class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Approval</h3>
              <div class="bg-gray-50 p-4 rounded-lg">
                <div v-if="canApproveFinanceManager">
                  <button @click="approveFinanceManager(true)" :disabled="loadingApprove" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2 flex items-center">
                    <span v-if="loadingApprove" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
                    Approve (Finance Manager)
                  </button>
                  <button @click="approveFinanceManager(false)" :disabled="loadingApprove" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700 flex items-center">
                    <span v-if="loadingApprove" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
                    Reject
                  </button>
                </div>
                <div v-if="canApproveGMFinance">
                  <button @click="approveGMFinance(true)" :disabled="loadingApprove" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2 flex items-center">
                    <span v-if="loadingApprove" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
                    Approve (GM Finance)
                  </button>
                  <button @click="approveGMFinance(false)" :disabled="loadingApprove" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700 flex items-center">
                    <span v-if="loadingApprove" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
                    Reject
                  </button>
                </div>
              </div>
            </div>

            <!-- Approval History -->
            <div class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Riwayat Approval</h3>
              <div class="space-y-4">
                <!-- Finance Manager Approval -->
                <div class="bg-gray-50 p-4 rounded-lg">
                  <h4 class="font-medium mb-2">Finance Manager</h4>
                  <div v-if="contraBon.finance_manager_approved_at">
                    <span class="text-green-600 font-semibold">Approved</span>
                    oleh <b>{{ contraBon.finance_manager?.nama_lengkap || contraBon.finance_manager_approved_by }}</b>
                    pada {{ formatDateTime(contraBon.finance_manager_approved_at) }}
                    <span v-if="contraBon.finance_manager_note">- Note: {{ contraBon.finance_manager_note }}</span>
                  </div>
                  <div v-else>
                    <span class="text-gray-500">Belum di-approve</span>
                  </div>
                </div>
                <!-- GM Finance Approval -->
                <div class="bg-gray-50 p-4 rounded-lg">
                  <h4 class="font-medium mb-2">GM Finance</h4>
                  <div v-if="contraBon.gm_finance_approved_at">
                    <span class="text-green-600 font-semibold">Approved</span>
                    oleh <b>{{ contraBon.gm_finance?.nama_lengkap || contraBon.gm_finance_approved_by }}</b>
                    pada {{ formatDateTime(contraBon.gm_finance_approved_at) }}
                    <span v-if="contraBon.gm_finance_note">- Note: {{ contraBon.gm_finance_note }}</span>
                  </div>
                  <div v-else>
                    <span class="text-gray-500">Belum di-approve</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Daftar Item</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                      <th v-if="contraBon.source_type === 'purchase_order'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in contraBon.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.item?.name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.unit?.name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.price) }}</td>
                      <td v-if="contraBon.source_type === 'purchase_order'" class="px-6 py-4 whitespace-nowrap text-xs">
                        <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-red-600">
                          <div v-if="item.discount_percent > 0">{{ item.discount_percent }}%</div>
                          <div v-if="item.discount_amount > 0">{{ formatRupiah(item.discount_amount) }}</div>
                        </div>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span v-if="contraBon.source_type === 'purchase_order' && item.po_item_total">
                          {{ formatRupiah(item.po_item_total) }}
                        </span>
                        <span v-else>
                          {{ formatRupiah(item.total) }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.notes || '-' }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="bg-gray-50">
                      <td :colspan="contraBon.source_type === 'purchase_order' ? 4 : 3" class="px-6 py-4 text-right font-medium">Total:</td>
                      <td class="px-6 py-4 font-medium">{{ formatRupiah(contraBon.total_amount) }}</td>
                      <td v-if="contraBon.source_type === 'purchase_order'"></td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'
// Lightbox
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  contraBon: {
    type: Object,
    required: true
  },
  user: {
    type: Object,
    required: true
  }
})

const isSuperadmin = computed(() =>
  props.user?.id_role === '5af56935b011a' && props.user?.status === 'A'
)

// Check if user can edit (Finance Manager or Superadmin)
const canEdit = computed(() => {
  const isFinanceManager = props.user?.id_jabatan == 160 && props.user?.status == 'A';
  return isFinanceManager || isSuperadmin.value;
})

const canApproveFinanceManager = computed(() =>
  ((props.user?.id_jabatan === 160 && props.user?.status === 'A') || isSuperadmin.value)
  && props.contraBon.status === 'draft'
  && !props.contraBon.finance_manager_approved_at
)

const canApproveGMFinance = computed(() =>
  (
    (props.user?.id_jabatan === 152 && props.user?.status === 'A') ||
    (props.user?.id_role === '5af56935b011a' && props.user?.status === 'A')
  )
  && props.contraBon.status === 'draft'
  && props.contraBon.finance_manager_approved_at
  && !props.contraBon.gm_finance_approved_at
)

const loadingApprove = ref(false);

const showLightbox = ref(false)

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
    pending_gm_finance: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return `px-2 py-1 rounded-full text-xs font-medium ${classes[status] || classes.draft}`
}

const formatRupiah = (value) => {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

const getSourceNumberBadgeClass = (number, sourceTypes) => {
  if (!number) return 'bg-gray-100 text-gray-800 border-gray-300';
  
  const numStr = String(number).toUpperCase();
  
  // Deteksi berdasarkan prefix number
  if (numStr.startsWith('PRF-')) {
    return 'bg-blue-100 text-blue-800 border-blue-300';
  } else if (numStr.startsWith('RWF')) {
    return 'bg-orange-100 text-orange-800 border-orange-300';
  } else if (numStr.startsWith('RF')) {
    return 'bg-purple-100 text-purple-800 border-purple-300';
  }
  
  // Fallback: gunakan source_types jika tersedia
  if (sourceTypes && sourceTypes.length > 0) {
    if (sourceTypes.includes('PR Foods')) {
      return 'bg-blue-100 text-blue-800 border-blue-300';
    } else if (sourceTypes.includes('Warehouse Retail Food')) {
      return 'bg-orange-100 text-orange-800 border-orange-300';
    } else if (sourceTypes.includes('Retail Food')) {
      return 'bg-purple-100 text-purple-800 border-purple-300';
    } else if (sourceTypes.includes('RO Supplier')) {
      return 'bg-green-100 text-green-800 border-green-300';
    }
  }
  
  // Default
  return 'bg-gray-100 text-gray-800 border-gray-300';
}

async function approveFinanceManager(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? 'Approve Contra Bon?' : 'Reject Contra Bon?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    loadingApprove.value = true;
    try {
      const response = await axios.post(route('contra-bons.approve', props.contraBon.id), { approved, note });
      loadingApprove.value = false;
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
        });
        router.reload();
      } else {
        Swal.fire('Gagal', response.data.message || 'Terjadi kesalahan saat approve', 'error');
      }
    } catch (e) {
      loadingApprove.value = false;
      Swal.fire('Gagal', e.response?.data?.message || 'Terjadi kesalahan saat approve', 'error');
    }
  }
}

async function approveGMFinance(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? 'Approve Contra Bon?' : 'Reject Contra Bon?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    loadingApprove.value = true;
    try {
      const response = await axios.post(route('contra-bons.approve', props.contraBon.id), { approved, note });
      loadingApprove.value = false;
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
        });
        router.reload();
      } else {
        Swal.fire('Gagal', response.data.message || 'Terjadi kesalahan saat approve', 'error');
      }
    } catch (e) {
      loadingApprove.value = false;
      Swal.fire('Gagal', e.response?.data?.message || 'Terjadi kesalahan saat approve', 'error');
    }
  }
}
</script> 