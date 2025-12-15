<template>
  <AppLayout title="Employee Resignation">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-minus text-red-500"></i> Employee Resignation
        </h1>
        <div class="flex gap-3">
          <Link :href="'/employee-resignations/create'" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fas fa-plus mr-2"></i>
            Buat Resignation Baru
          </Link>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <!-- Total -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-gray-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <i class="fa-solid fa-list text-4xl text-gray-300"></i>
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
        <!-- Rejected -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-red-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Rejected</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.rejected }}</p>
            </div>
            <i class="fa-solid fa-times-circle text-4xl text-red-300"></i>
          </div>
        </div>
      </div>

      <!-- Filter and Search -->
      <div class="flex flex-col gap-4 mb-6">
        <!-- Search and Basic Filters -->
        <div class="flex flex-col md:flex-row gap-4">
          <input
            type="text"
            v-model="search"
            @input="onSearchInput"
            placeholder="Cari nomor, employee, outlet, atau notes..."
            class="flex-1 px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
          />
          <select
            v-model="status"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
          >
            <option value="all">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <select
            v-model="outlet"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
          >
            <option value="all">Semua Outlet</option>
            <option v-for="o in filterOptions.outlets" :key="o.id_outlet" :value="o.id_outlet">
              {{ o.nama_outlet }}
            </option>
          </select>
          <select
            v-model="perPage"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
          >
            <option value="15">15 Per Halaman</option>
            <option value="30">30 Per Halaman</option>
            <option value="50">50 Per Halaman</option>
          </select>
        </div>
        <!-- Date Range Filter -->
        <div class="flex flex-col md:flex-row gap-4 items-end">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Dari Tanggal:</label>
            <input
              type="date"
              v-model="dateFrom"
              @change="debouncedSearch"
              class="px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Sampai Tanggal:</label>
            <input
              type="date"
              v-model="dateTo"
              @change="debouncedSearch"
              class="px-4 py-2 rounded-xl border border-red-200 shadow focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
            />
          </div>
          <button
            v-if="dateFrom || dateTo"
            @click="clearDateFilter"
            class="px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition text-sm font-medium"
          >
            <i class="fas fa-times mr-1"></i>
            Hapus Filter Tanggal
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resignation Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resignation Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="resignation in data.data" :key="resignation.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ resignation.resignation_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div>
                    <div class="font-medium">{{ resignation.employee?.nama_lengkap || 'Unknown' }}</div>
                    <div class="text-xs text-gray-500">{{ resignation.employee?.nik || '-' }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ resignation.outlet?.nama_outlet || 'Unknown' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(resignation.resignation_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="resignation.resignation_type === 'prosedural' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                    {{ resignation.resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getStatusColor(resignation.status)">
                    {{ getStatusText(resignation.status) }}
                  </span>
                  <!-- Pending Approval Info -->
                  <div v-if="getPendingApprover(resignation)" class="mt-1">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-1.5">
                      <div class="flex items-center gap-1.5">
                        <i class="fas fa-clock text-yellow-600 text-xs"></i>
                        <div class="text-xs">
                          <p class="font-medium text-yellow-800 leading-tight">Menunggu Approval</p>
                          <p class="text-yellow-700 leading-tight">{{ getPendingApprover(resignation) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ resignation.creator?.nama_lengkap || 'Unknown' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDateTime(resignation.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <Link
                      :href="`/employee-resignations/${resignation.id}`"
                      class="text-blue-600 hover:text-blue-900"
                      title="View"
                    >
                      <i class="fas fa-eye"></i>
                    </Link>
                    <Link
                      v-if="canEdit(resignation)"
                      :href="`/employee-resignations/${resignation.id}/edit`"
                      class="text-green-600 hover:text-green-900"
                      title="Edit"
                    >
                      <i class="fas fa-edit"></i>
                    </Link>
                    <button
                      v-if="canDelete(resignation)"
                      @click="confirmDelete(resignation)"
                      class="text-red-600 hover:text-red-900"
                      title="Delete"
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
          <i class="fa-solid fa-user-minus text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 text-lg">Tidak ada Employee Resignation ditemukan</p>
        </div>

        <!-- Pagination -->
        <div v-if="data.links && data.links.length > 3" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} hasil
            </div>
            <div class="flex gap-2">
              <Link
                v-for="link in data.links"
                :key="link.label"
                :href="link.url || '#'"
                v-html="link.label"
                :class="[
                  'px-3 py-2 rounded-md text-sm font-medium',
                  link.active
                    ? 'bg-red-600 text-white'
                    : link.url
                    ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                ]"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  data: Object,
  filters: Object,
  filterOptions: Object,
  statistics: Object,
  auth: {
    type: Object,
    default: () => ({ user: null })
  }
});

const user = computed(() => props.auth?.user || null);

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const outlet = ref(props.filters?.outlet || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/employee-resignations', {
    search: search.value,
    status: status.value,
    outlet: outlet.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function clearDateFilter() {
  dateFrom.value = '';
  dateTo.value = '';
  debouncedSearch();
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getStatusColor(status) {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'submitted':
      return 'bg-yellow-100 text-yellow-800';
    case 'approved':
      return 'bg-green-100 text-green-800';
    case 'rejected':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'draft':
      return 'Draft';
    case 'submitted':
      return 'Submitted';
    case 'approved':
      return 'Approved';
    case 'rejected':
      return 'Rejected';
    default:
      return status;
  }
}

function getPendingApprover(resignation) {
  if (!resignation.approval_flows || resignation.approval_flows.length === 0) {
    return null;
  }
  
  const pendingFlow = resignation.approval_flows.find(flow => flow.status === 'PENDING');
  if (pendingFlow && pendingFlow.approver) {
    return `Level ${pendingFlow.approval_level}: ${pendingFlow.approver.nama_lengkap}`;
  }
  
  return null;
}

function canEdit(resignation) {
  if (!user.value) return false;
  const isSuperadmin = user.value.id_role === '5af56935b011a';
  const isCreator = resignation.created_by == user.value.id;
  return (resignation.status === 'draft' || resignation.status === 'rejected') && (isCreator || isSuperadmin);
}

function canDelete(resignation) {
  if (!user.value) return false;
  const isSuperadmin = user.value.id_role === '5af56935b011a';
  const isCreator = resignation.created_by == user.value.id;
  return (resignation.status === 'draft' || resignation.status === 'rejected') && (isCreator || isSuperadmin);
}

function confirmDelete(resignation) {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/employee-resignations/${resignation.id}`, {
        onSuccess: () => {
          Swal.fire(
            'Deleted!',
            'Employee resignation has been deleted.',
            'success'
          );
        },
        onError: (errors) => {
          Swal.fire(
            'Error!',
            errors.message || 'Failed to delete employee resignation.',
            'error'
          );
        }
      });
    }
  });
}
</script>

