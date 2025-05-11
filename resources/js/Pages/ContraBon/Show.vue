<template>
  <AppLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4">
          <button @click="$inertia.visit('/contra-bons')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
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
                  <p><span class="font-medium">Status:</span> 
                    <span :class="getStatusClass(contraBon.status)">{{ contraBon.status }}</span>
                  </p>
                  <p><span class="font-medium">Supplier:</span> {{ contraBon.supplier?.name }}</p>
                  <p><span class="font-medium">Dibuat oleh:</span> {{ contraBon.creator?.nama_lengkap }}</p>
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
                      <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.total) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">{{ item.notes || '-' }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="bg-gray-50">
                      <td colspan="4" class="px-6 py-4 text-right font-medium">Total:</td>
                      <td class="px-6 py-4 font-medium">{{ formatRupiah(contraBon.total_amount) }}</td>
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