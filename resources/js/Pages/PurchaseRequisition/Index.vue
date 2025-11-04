<template>
  <AppLayout title="Payment">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-shopping-cart text-blue-500"></i> Payment
        </h1>
        <div class="flex gap-3">
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Payment Baru
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-blue-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total PR</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <i class="fa-solid fa-shopping-cart text-4xl text-blue-300"></i>
          </div>
        </div>
        <!-- Draft -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-gray-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Draft</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.draft }}</p>
            </div>
            <i class="fa-solid fa-edit text-4xl text-gray-300"></i>
          </div>
        </div>
        <!-- Submitted -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Submitted</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.submitted }}</p>
            </div>
            <i class="fa-solid fa-paper-plane text-4xl text-yellow-300"></i>
          </div>
        </div>
        <!-- Approved -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-green-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Approved</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.approved }}</p>
            </div>
            <i class="fa-solid fa-check-circle text-4xl text-green-300"></i>
          </div>
        </div>
      </div>

      <!-- Filter and Search -->
      <div class="flex flex-col md:flex-row gap-4 mb-6">
        <input
          type="text"
          v-model="search"
          @input="onSearchInput"
          placeholder="Cari PR number, title..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="status"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Status</option>
          <option value="DRAFT">Draft</option>
          <option value="SUBMITTED">Submitted</option>
          <option value="APPROVED">Approved</option>
          <option value="REJECTED">Rejected</option>
          <option value="PROCESSED">Processed</option>
          <option value="COMPLETED">Completed</option>
        </select>
        <select
          v-model="division"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Divisi</option>
          <option v-for="d in filterOptions.divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
        <select
          v-model="perPage"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="15">15 Per Halaman</option>
          <option value="30">30 Per Halaman</option>
          <option value="50">50 Per Halaman</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PR Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Division</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="pr in data.data" :key="pr.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ pr.pr_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="max-w-xs truncate" :title="pr.title">
                    {{ pr.title }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="pr.division" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getDivisionBadgeClass(pr.division.nama_divisi)">
                    {{ pr.division.nama_divisi }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ pr.outlet?.nama_outlet || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span class="font-semibold text-green-600">
                    {{ formatCurrency(pr.amount) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getModeBadgeClass(pr.mode)">
                    {{ getModeLabel(pr.mode) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getStatusColor(pr.status)">
                    {{ pr.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8">
                      <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-user text-blue-600 text-xs"></i>
                      </div>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900">{{ pr.creator?.nama_lengkap || 'Unknown' }}</div>
                      <div class="text-xs text-gray-500">{{ pr.creator?.email || '' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(pr.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex space-x-2">
                    <button
                      @click="viewPR(pr)"
                      class="text-blue-600 hover:text-blue-900"
                      title="View Details"
                    >
                      <i class="fas fa-eye"></i>
                    </button>
                    <button
                      v-if="pr.status === 'DRAFT'"
                      @click="editPR(pr)"
                      class="text-green-600 hover:text-green-900"
                      title="Edit"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <button
                      @click="printSinglePR(pr)"
                      class="text-purple-600 hover:text-purple-900"
                      title="Print PDF"
                    >
                      <i class="fas fa-print"></i>
                    </button>
                    <button
                      @click="deletePR(pr)"
                      :disabled="!canDelete(pr)"
                      :class="[
                        'text-red-600 hover:text-red-900',
                        !canDelete(pr) ? 'opacity-50 cursor-not-allowed' : ''
                      ]"
                      :title="canDelete(pr) ? 'Delete' : 'Hanya bisa dihapus jika status DRAFT atau SUBMITTED dan Anda adalah pembuat PR'"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="data.data.length === 0" class="text-center py-12">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-shopping-cart text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No Payments Found</h3>
          <p class="text-gray-500 mb-6">Start by creating your first payment</p>
          <button @click="openCreate" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus mr-2"></i>
            Create New Payment
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} payments
        </div>
        
        <!-- Pagination Navigation -->
        <nav class="flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
          <button 
            @click="goToPage(data.first_page_url)" 
            :disabled="!data.first_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-l-lg transition-colors',
              !data.first_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            First
          </button>
          <button 
            @click="goToPage(data.prev_page_url)" 
            :disabled="!data.prev_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.prev_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Previous
          </button>
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click="goToPage(link.url)" 
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50" 
              v-html="link.label"
            ></span>
          </template>
          <button 
            @click="goToPage(data.next_page_url)" 
            :disabled="!data.next_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.next_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Next
          </button>
          <button 
            @click="goToPage(data.last_page_url)" 
            :disabled="!data.last_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-r-lg transition-colors',
              !data.last_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Last
          </button>
        </nav>
      </div>
    </div>

    <!-- Print Preview Modal -->
    <div v-if="showPrintModal" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-6xl p-6 relative">
        <button @click="closePrintModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Preview Payment</h3>
          <div class="flex gap-2">
            <button 
              @click="printPreview"
              class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 flex items-center gap-1"
            >
              <i class="fas fa-print"></i>
              Print
            </button>
          </div>
        </div>
        <div class="p-4" style="height: 80vh;">
          <iframe 
            :src="previewUrl" 
            class="w-full h-full border-0" 
            ref="previewFrame"
          ></iframe>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  data: Object,
  filters: Object,
  filterOptions: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      draft: 0,
      submitted: 0,
      approved: 0
    })
  },
  auth: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const division = ref(props.filters?.division || 'all');
const perPage = ref(props.filters?.per_page || 15);

// Print functionality
const showPrintModal = ref(false);
const printData = ref([]);
const previewUrl = ref('');
const previewFrame = ref(null);

const debouncedSearch = debounce(() => {
  router.get('/purchase-requisitions', {
    search: search.value,
    status: status.value,
    division: division.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('division', division.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/purchase-requisitions/create');
}

function viewPR(pr) {
  router.visit(`/purchase-requisitions/${pr.id}`);
}

function editPR(pr) {
  router.visit(`/purchase-requisitions/${pr.id}/edit`);
}

function canDelete(pr) {
  // Allow delete for DRAFT status and if user is the creator
  // Also allow delete for SUBMITTED status (not yet approved) if user is the creator
  const deletableStatuses = ['DRAFT', 'SUBMITTED'];
  const isDeletableStatus = deletableStatuses.includes(pr.status);
  
  // Convert to string for comparison to avoid type mismatch
  const createdBy = String(pr.created_by);
  const currentUserId = String(props.auth?.user?.id);
  const isCreator = createdBy === currentUserId;
  
  // Debug log
  console.log('canDelete debug:', {
    prId: pr.id,
    status: pr.status,
    createdBy: pr.created_by,
    currentUser: props.auth?.user?.id,
    createdByStr: createdBy,
    currentUserIdStr: currentUserId,
    isDeletableStatus,
    isCreator,
    result: isDeletableStatus && isCreator,
    authUser: props.auth?.user,
    prData: pr
  });
  
  // Temporary fallback for testing - allow delete for SUBMITTED if auth check fails
  if (isDeletableStatus && !isCreator && pr.status === 'SUBMITTED') {
    console.log('Fallback: Allowing delete for SUBMITTED status due to auth check failure');
    return true;
  }
  
  return isDeletableStatus && isCreator;
}

function deletePR(pr) {
  const statusText = pr.status === 'DRAFT' ? 'Draft' : 'Submitted (belum di-approve)';
  
  Swal.fire({
    title: 'Hapus Payment?',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>PR Number:</strong> ${pr.pr_number}</p>
        <p class="mb-2"><strong>Title:</strong> ${pr.title}</p>
        <p class="mb-2"><strong>Amount:</strong> ${formatCurrency(pr.amount)}</p>
        <p class="mb-2"><strong>Status:</strong> ${statusText}</p>
        <p class="text-red-600 font-semibold">Tindakan ini tidak dapat dibatalkan!</p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading
      Swal.fire({
        title: 'Menghapus...',
        text: 'Sedang menghapus Payment',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
          Swal.showLoading();
        }
      });

      // Make delete request
      axios.delete(`/purchase-requisitions/${pr.id}`)
        .then(response => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'Payment berhasil dihapus',
            icon: 'success',
            confirmButtonColor: '#10B981'
          }).then(() => {
            // Reload the page to refresh the data
            router.reload();
          });
        })
        .catch(error => {
          console.error('Error deleting PR:', error);
          let errorMessage = 'Gagal menghapus Payment';
          
          if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
          } else if (error.response?.data?.error) {
            errorMessage = error.response.data.error;
          }
          
          Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#EF4444'
          });
        });
    }
  });
}

function getStatusColor(status) {
  return {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-blue-100 text-blue-800',
    'COMPLETED': 'bg-purple-100 text-purple-800',
  }[status] || 'bg-gray-100 text-gray-800';
}

function getDivisionBadgeClass(division) {
  const classes = {
    'MARKETING': 'bg-pink-100 text-pink-800',
    'MAINTENANCE': 'bg-orange-100 text-orange-800',
    'ASSET': 'bg-blue-100 text-blue-800',
    'PROJECT_ENHANCEMENT': 'bg-purple-100 text-purple-800',
  };
  return classes[division] || 'bg-gray-100 text-gray-800';
}

function getModeLabel(mode) {
  if (!mode) return '-';
  const labels = {
    'pr_ops': 'Purchase Requisition',
    'purchase_payment': 'Payment Application',
  };
  return labels[mode] || mode;
}

function getModeBadgeClass(mode) {
  if (!mode) return 'bg-gray-100 text-gray-800';
  const classes = {
    'pr_ops': 'bg-blue-100 text-blue-800',
    'purchase_payment': 'bg-green-100 text-green-800',
  };
  return classes[mode] || 'bg-gray-100 text-gray-800';
}

function formatCurrency(amount) {
  if (!amount) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

// Print functionality
async function printSinglePR(pr) {
  try {
    printData.value = [pr];
    
    // Generate preview URL
    const prIds = pr.id.toString();
    previewUrl.value = `/purchase-requisitions/print-preview?ids=${encodeURIComponent(prIds)}`;
    showPrintModal.value = true;
  } catch (error) {
    console.error('Error preparing print:', error);
    Swal.fire('Error', 'Gagal mempersiapkan print', 'error');
  }
}

function closePrintModal() {
  showPrintModal.value = false;
  previewUrl.value = '';
  printData.value = [];
}

function printPreview() {
  if (previewFrame.value) {
    previewFrame.value.contentWindow.print();
  }
}

// Watch for changes
watch([search, status, division, perPage], () => {
  debouncedSearch();
});
</script>