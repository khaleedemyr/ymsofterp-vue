<template>
  <Head :title="'Stock Adjustment #' + adjustment.number" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
          <button
            @click="$inertia.visit('/food-inventory-adjustment')"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <i class="fa fa-arrow-left text-gray-600 text-xl"></i>
          </button>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
              <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-boxes-stacked text-white text-xl"></i>
              </div>
              <span>Stock Adjustment Detail</span>
            </h1>
            <p class="text-gray-600 ml-16">Detail informasi penyesuaian stok inventory</p>
          </div>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-blue-700">Adjustment Number</p>
            <div class="p-2 bg-blue-500 rounded-lg">
              <i class="fa-solid fa-hashtag text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-blue-900 font-mono">{{ adjustment.number }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-purple-700">Adjustment Date</p>
            <div class="p-2 bg-purple-500 rounded-lg">
              <i class="fa-solid fa-calendar text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-purple-900">{{ formatDate(adjustment.date) }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-green-700">Type</p>
            <div class="p-2 bg-green-500 rounded-lg">
              <i class="fa-solid fa-tag text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-green-900">
            <span :class="adjustment.type === 'in' ? 'text-green-700' : 'text-red-700'">
              {{ adjustment.type === 'in' ? 'Stock In' : 'Stock Out' }}
            </span>
          </p>
        </div>
        
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-orange-700">Status</p>
            <div class="p-2 bg-orange-500 rounded-lg">
              <i class="fa-solid fa-info-circle text-white text-sm"></i>
            </div>
          </div>
          <span :class="getStatusClass(adjustment.status)">
            {{ getStatusLabel(adjustment.status) }}
          </span>
        </div>
      </div>

      <!-- Detail Information -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-blue-100 rounded-lg">
            <i class="fa-solid fa-info-circle text-blue-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Detail Information</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-warehouse text-blue-500 mr-2"></i> Warehouse
              </label>
              <p class="text-base font-medium text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200">
                {{ adjustment.warehouse?.name || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-user text-blue-500 mr-2"></i> Created By
              </label>
              <div class="flex items-center gap-2 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200">
                <div class="w-8 h-8 bg-blue-200 rounded-full flex items-center justify-center text-blue-800 font-bold text-xs">
                  {{ adjustment.creator?.nama_lengkap ? adjustment.creator.nama_lengkap.charAt(0).toUpperCase() : '?' }}
                </div>
                <span class="text-base font-medium text-gray-900">{{ adjustment.creator?.nama_lengkap || '-' }}</span>
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-clock text-blue-500 mr-2"></i> Created At
              </label>
              <p class="text-base font-medium text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200">
                {{ formatDateTime(adjustment.created_at) }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-note-sticky text-blue-500 mr-2"></i> Reason
              </label>
              <p class="text-base text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200 min-h-[60px]">
                {{ adjustment.reason || '-' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-indigo-100 rounded-lg">
            <i class="fa-solid fa-list-check text-indigo-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Detail Items</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full">
            <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-box text-indigo-200"></i>
                    <span>Item</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-calculator text-indigo-200"></i>
                    <span>Quantity</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-ruler text-indigo-200"></i>
                    <span>Unit</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-note-sticky text-indigo-200"></i>
                    <span>Note</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="!adjustment.items || adjustment.items.length === 0">
                <td colspan="4" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-lg font-medium">Tidak ada item</p>
                  </div>
                </td>
              </tr>
              <tr
                v-for="item in adjustment.items"
                :key="item.id"
                class="hover:bg-indigo-50 transition-all duration-150"
              >
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900">{{ item.item?.name || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-semibold text-gray-900">{{ Number(item.qty).toFixed(2) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                    {{ item.unit }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-700 max-w-md">{{ item.note || '-' }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Approval Section -->
      <div v-if="canApprove" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-amber-100 rounded-lg">
            <i class="fa-solid fa-check-circle text-amber-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Approval</h3>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-note-sticky text-blue-500 mr-2"></i> Catatan Approval (Opsional)
            </label>
            <textarea
              v-model="approvalNote"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none"
              rows="3"
              placeholder="Masukkan catatan approval..."
            ></textarea>
          </div>
          <div class="flex justify-end gap-3">
            <button
              @click="reject"
              :disabled="loading"
              class="px-6 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold transform hover:-translate-y-0.5"
            >
              <i class="fa-solid fa-times mr-2"></i> Reject
            </button>
            <button
              @click="approve"
              :disabled="loading"
              class="px-6 py-2.5 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold transform hover:-translate-y-0.5"
            >
              <i class="fa-solid fa-check mr-2"></i> Approve
            </button>
          </div>
        </div>
      </div>

      <!-- Approval History -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-purple-100 rounded-lg">
            <i class="fa-solid fa-history text-purple-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Riwayat Approval</h3>
        </div>
        <div class="space-y-4">
          <div v-if="adjustment.approved_by_assistant_ssd_manager" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <i class="fa-solid fa-user-check text-blue-600"></i>
                <span class="font-semibold text-blue-900">Asisten SSD Manager</span>
              </div>
              <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Approved</span>
            </div>
            <p class="text-sm text-gray-700 mb-1">
              <i class="fa-solid fa-user mr-1"></i>
              {{ getApproverName(adjustment.approved_by_assistant_ssd_manager) }}
            </p>
            <p class="text-sm text-gray-600">
              <i class="fa-solid fa-clock mr-1"></i>
              {{ formatDateTime(adjustment.approved_at_assistant_ssd_manager) }}
            </p>
            <p v-if="adjustment.assistant_ssd_manager_note" class="text-sm text-gray-700 mt-2 italic">
              "{{ adjustment.assistant_ssd_manager_note }}"
            </p>
          </div>
          
          <div v-if="adjustment.approved_by_ssd_manager" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-indigo-600"></i>
                <span class="font-semibold text-indigo-900">SSD Manager / Sous Chef MK</span>
              </div>
              <span class="text-xs text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">Approved</span>
            </div>
            <p class="text-sm text-gray-700 mb-1">
              <i class="fa-solid fa-user mr-1"></i>
              {{ getApproverName(adjustment.approved_by_ssd_manager) }}
            </p>
            <p class="text-sm text-gray-600">
              <i class="fa-solid fa-clock mr-1"></i>
              {{ formatDateTime(adjustment.approved_at_ssd_manager) }}
            </p>
            <p v-if="adjustment.ssd_manager_note" class="text-sm text-gray-700 mt-2 italic">
              "{{ adjustment.ssd_manager_note }}"
            </p>
          </div>
          
          <div v-if="adjustment.approved_by_cost_control_manager" class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <i class="fa-solid fa-user-tie text-green-600"></i>
                <span class="font-semibold text-green-900">Cost Control Manager</span>
              </div>
              <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Final Approved</span>
            </div>
            <p class="text-sm text-gray-700 mb-1">
              <i class="fa-solid fa-user mr-1"></i>
              {{ getApproverName(adjustment.approved_by_cost_control_manager) }}
            </p>
            <p class="text-sm text-gray-600">
              <i class="fa-solid fa-clock mr-1"></i>
              {{ formatDateTime(adjustment.approved_at_cost_control_manager) }}
            </p>
            <p v-if="adjustment.cost_control_manager_note" class="text-sm text-gray-700 mt-2 italic">
              "{{ adjustment.cost_control_manager_note }}"
            </p>
          </div>
          
          <div v-if="!adjustment.approved_by_assistant_ssd_manager && !adjustment.approved_by_ssd_manager && !adjustment.approved_by_cost_control_manager" class="text-center py-8 text-gray-400">
            <i class="fa-solid fa-clock text-4xl mb-3"></i>
            <p class="text-lg font-medium">Belum ada approval</p>
          </div>
        </div>
      </div>

      <!-- Loading Overlay -->
      <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="bg-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3">
          <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          <span class="font-semibold text-gray-700">Memproses...</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  adjustment: Object,
  user: Object
})

const approvalNote = ref('')
const loading = ref(false)

const canApprove = computed(() => {
  if (!props.user) return false
  const status = props.adjustment.status
  const jabatan = props.user.id_jabatan
  const isSuperadmin = props.user.id_role === '5af56935b011a' && props.user.status === 'A'
  
  // MK Warehouse: hanya Sous Chef MK (179)
  // Non-MK: Asisten SSD Manager (172) atau SSD Manager (161)
  if (status === 'waiting_approval') {
    if (isSuperadmin) return true
    // Check warehouse
    const warehouse = props.adjustment.warehouse
    const isMKWarehouse = warehouse && ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'].includes(warehouse.name)
    if (isMKWarehouse) {
      return jabatan === 179 // Sous Chef MK
    } else {
      return [172, 161].includes(jabatan) // Asisten SSD Manager atau SSD Manager
    }
  }
  if (status === 'waiting_ssd_manager') {
    return isSuperadmin || [161, 172].includes(jabatan) // SSD Manager atau Asisten SSD Manager
  }
  if (status === 'waiting_cost_control') {
    return isSuperadmin || jabatan === 167 // Cost Control Manager
  }
  return false
})

function approve() {
  Swal.fire({
    title: 'Approve Stock Adjustment?',
    text: 'Setelah approve, data akan diproses ke inventory jika sudah final. Lanjutkan?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
    preConfirm: () => {
      loading.value = true
      return router.post(`/food-inventory-adjustment/${props.adjustment.id}/approve`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil', 'Stock adjustment berhasil di-approve!', 'success')
          router.reload()
        },
        onError: (err) => {
          Swal.fire('Gagal', err?.error || 'Gagal approve', 'error')
        },
        onFinish: () => {
          loading.value = false
        }
      })
    },
    allowOutsideClick: () => !Swal.isLoading()
  })
}

function reject() {
  Swal.fire({
    title: 'Reject Stock Adjustment?',
    text: 'Data akan ditolak dan tidak diproses ke inventory. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Reject',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    preConfirm: () => {
      loading.value = true
      return router.post(`/food-inventory-adjustment/${props.adjustment.id}/reject`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Ditolak', 'Stock adjustment berhasil direject.', 'success')
          router.reload()
        },
        onError: (err) => {
          Swal.fire('Gagal', err?.error || 'Gagal reject', 'error')
        },
        onFinish: () => {
          loading.value = false
        }
      })
    },
    allowOutsideClick: () => !Swal.isLoading()
  })
}

function getStatusLabel(status) {
  switch (status) {
    case 'waiting_approval':
      return 'Menunggu Approval'
    case 'waiting_ssd_manager':
      return 'Menunggu SSD Manager'
    case 'waiting_cost_control':
      return 'Menunggu Cost Control'
    case 'approved':
      return 'Approved'
    case 'rejected':
      return 'Rejected'
    default:
      return status
  }
}

function getStatusClass(status) {
  const baseClass = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold'
  switch (status) {
    case 'approved':
      return `${baseClass} bg-green-100 text-green-800`
    case 'rejected':
      return `${baseClass} bg-red-100 text-red-800`
    case 'waiting_approval':
    case 'waiting_ssd_manager':
    case 'waiting_cost_control':
      return `${baseClass} bg-yellow-100 text-yellow-800`
    default:
      return `${baseClass} bg-gray-100 text-gray-800`
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

function formatDateTime(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID')
}

function getApproverName(userId) {
  // Try to get from adjustment relationships if available
  // For now, return user ID as fallback
  // Backend should include approver names in the response
  return `User ID: ${userId}`
}
</script> 