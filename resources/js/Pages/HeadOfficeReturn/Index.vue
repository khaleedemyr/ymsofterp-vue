<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Kelola Return Outlet</h1>
          <p class="text-gray-600">Kelola dan approve return dari outlet</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
          <i class="fa fa-building mr-1"></i>
          <span>Head Office</span>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              type="text"
              v-model="filters.search"
              @input="debouncedSearch"
              placeholder="No. Return, GR, Outlet..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
            />
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input
              type="date"
              v-model="filters.date_from"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input
              type="date"
              v-model="filters.date_to"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
            />
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="filters.status"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
            >
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>

        <!-- Filter Actions -->
        <div class="flex justify-end gap-2 mt-4">
          <button
            @click="clearFilters"
            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <!-- Returns Table -->
      <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  No. Return
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Outlet
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  GR Number
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tanggal Return
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Dibuat Oleh
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Approved/Rejected Oleh
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Aksi
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="returnItem in returns.data" :key="returnItem.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ returnItem.return_number }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ returnItem.nama_outlet }}</div>
                  <div class="text-xs text-gray-500">{{ returnItem.warehouse_name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ returnItem.gr_number }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ formatDate(returnItem.return_date) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(returnItem.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                    {{ getStatusText(returnItem.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ returnItem.created_by_name }}</div>
                  <div class="text-xs text-gray-500">{{ formatDate(returnItem.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    <span v-if="returnItem.approved_by_name" class="text-green-600">
                      <i class="fa fa-check mr-1"></i>{{ returnItem.approved_by_name }}
                    </span>
                    <span v-else-if="returnItem.rejected_by_name" class="text-red-600">
                      <i class="fa fa-times mr-1"></i>{{ returnItem.rejected_by_name }}
                    </span>
                    <span v-else class="text-gray-500">-</span>
                  </div>
                  <div class="text-xs text-gray-500">
                    <span v-if="returnItem.approved_at">{{ formatDate(returnItem.approved_at) }}</span>
                    <span v-else-if="returnItem.rejection_at">{{ formatDate(returnItem.rejection_at) }}</span>
                    <span v-else>-</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <Link
                      :href="`/head-office-return/${returnItem.id}`"
                      class="text-orange-600 hover:text-orange-900 transition-colors"
                    >
                      <i class="fa fa-eye mr-1"></i> Detail
                    </Link>
                    
                    <template v-if="returnItem.status === 'pending'">
                      <button
                        @click="approveReturn(returnItem.id)"
                        class="text-green-600 hover:text-green-900 transition-colors"
                      >
                        <i class="fa fa-check mr-1"></i> Approve
                      </button>
                      <button
                        @click="showRejectModal(returnItem.id)"
                        class="text-red-600 hover:text-red-900 transition-colors"
                      >
                        <i class="fa fa-times mr-1"></i> Reject
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="returns.data.length === 0" class="text-center py-12">
          <i class="fa fa-inbox text-4xl text-gray-300 mb-4"></i>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada return</h3>
          <p class="text-gray-500">Belum ada return yang dibuat oleh outlet</p>
        </div>

        <!-- Pagination -->
        <div v-if="returns.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <Link
                v-if="returns.prev_page_url"
                :href="returns.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Previous
              </Link>
              <Link
                v-if="returns.next_page_url"
                :href="returns.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Next
              </Link>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ returns.from }}</span>
                  sampai
                  <span class="font-medium">{{ returns.to }}</span>
                  dari
                  <span class="font-medium">{{ returns.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <Link
                    v-if="returns.prev_page_url"
                    :href="returns.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                  >
                    <i class="fa fa-chevron-left"></i>
                  </Link>
                  <Link
                    v-if="returns.next_page_url"
                    :href="returns.next_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                  >
                    <i class="fa fa-chevron-right"></i>
                  </Link>
                </nav>
              </div>
            </div>
          </div>
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
import { ref, reactive, watch } from 'vue'
import { debounce } from 'lodash'
import Swal from 'sweetalert2'

const props = defineProps({
  user: Object,
  returns: Object,
  filters: Object
})

const filters = reactive({ ...props.filters })
const showReject = ref(false)
const rejectReason = ref('')
const selectedReturnId = ref(null)

const debouncedSearch = debounce(() => {
  applyFilters()
}, 500)

function applyFilters() {
  router.get('/head-office-return', filters, {
    preserveState: true,
    replace: true
  })
}

function clearFilters() {
  Object.keys(filters).forEach(key => {
    filters[key] = ''
  })
  applyFilters()
}

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

async function approveReturn(id) {
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
      const response = await axios.post(`/head-office-return/${id}/approve`);
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
          timer: 1500,
          showConfirmButton: false
        });
        // Reload the page to refresh the data
        window.location.reload();
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

function showRejectModal(id) {
  selectedReturnId.value = id
  rejectReason.value = ''
  showReject.value = true
}

function closeRejectModal() {
  showReject.value = false
  selectedReturnId.value = null
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
    const response = await axios.post(`/head-office-return/${selectedReturnId.value}/reject`, {
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
      closeRejectModal();
      // Reload the page to refresh the data
      window.location.reload();
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
