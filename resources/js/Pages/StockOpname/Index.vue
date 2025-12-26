<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Stock Opname
        </h1>
        <div class="flex gap-3">
          <Link
            :href="route('outlet-stock-opname-report.index')"
            class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa-solid fa-file-lines mr-2"></i> Report
          </Link>
          <Link
            :href="route('stock-opnames.create')"
            class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa-solid fa-plus mr-2"></i> Buat Stock Opname Baru
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <input
            type="text"
            v-model="filters.search"
            @input="applyFilters"
            placeholder="Cari opname number, notes..."
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <select
            v-model="filters.status"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="all">Semua Status</option>
            <option value="DRAFT">Draft</option>
            <option value="SUBMITTED">Submitted</option>
            <option value="APPROVED">Approved</option>
            <option value="REJECTED">Rejected</option>
            <option value="COMPLETED">Completed</option>
          </select>
          <select
            v-model="filters.outlet_id"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :disabled="!outletSelectable"
          >
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
              {{ outlet.name }}
            </option>
          </select>
          <input
            type="date"
            v-model="filters.date_from"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <input
            type="date"
            v-model="filters.date_to"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-hashtag mr-2 text-blue-500"></i>Opname Number
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-store mr-2 text-blue-500"></i>Outlet
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-warehouse mr-2 text-blue-500"></i>Warehouse Outlet
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-calendar mr-2 text-blue-500"></i>Tanggal
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-info-circle mr-2 text-blue-500"></i>Status
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-user mr-2 text-blue-500"></i>Created By
                </th>
                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <i class="fa-solid fa-cog mr-2 text-blue-500"></i>Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="stockOpnames.data.length === 0">
                <td colspan="7" class="text-center py-16">
                  <div class="flex flex-col items-center justify-center">
                    <i class="fa-solid fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg font-medium">Tidak ada data stock opname</p>
                    <p class="text-gray-400 text-sm mt-2">Mulai dengan membuat stock opname baru</p>
                  </div>
                </td>
              </tr>
              <tr
                v-for="opname in stockOpnames.data"
                :key="opname.id"
                class="hover:bg-blue-50 transition-colors duration-150"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-semibold text-gray-900">
                    {{ opname.opname_number }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">
                    <i class="fa-solid fa-store mr-2 text-gray-400"></i>
                    {{ opname.outlet?.nama_outlet || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">
                    <i class="fa-solid fa-warehouse mr-2 text-gray-400"></i>
                    {{ opname.warehouse_outlet?.name || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">
                    <i class="fa-solid fa-calendar mr-2 text-gray-400"></i>
                    {{ formatDate(opname.opname_date) }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(opname.status)" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold">
                    <i :class="getStatusIcon(opname.status)" class="mr-1.5"></i>
                    {{ opname.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">
                    <i class="fa-solid fa-user mr-2 text-gray-400"></i>
                    {{ opname.creator?.nama_lengkap || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="route('stock-opnames.show', opname.id)"
                      class="inline-flex items-center px-3 py-1.5 bg-blue-500 text-white text-xs font-semibold rounded-lg hover:bg-blue-600 transition-colors shadow-sm"
                      title="View Details"
                    >
                      <i class="fa-solid fa-eye mr-1.5"></i>
                      View
                    </Link>
                    <Link
                      v-if="opname.status === 'DRAFT'"
                      :href="route('stock-opnames.edit', opname.id)"
                      class="inline-flex items-center px-3 py-1.5 bg-green-500 text-white text-xs font-semibold rounded-lg hover:bg-green-600 transition-colors shadow-sm"
                      title="Edit"
                    >
                      <i class="fa-solid fa-edit mr-1.5"></i>
                      Edit
                    </Link>
                    <button
                      v-if="opname.status === 'DRAFT'"
                      @click="deleteStockOpname(opname.id)"
                      class="inline-flex items-center px-3 py-1.5 bg-red-500 text-white text-xs font-semibold rounded-lg hover:bg-red-600 transition-colors shadow-sm"
                      title="Delete"
                    >
                      <i class="fa-solid fa-trash mr-1.5"></i>
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4" v-if="stockOpnames.data.length > 0">
        <div class="text-sm text-gray-600 bg-gray-50 px-4 py-2 rounded-lg">
          <i class="fa-solid fa-info-circle mr-2 text-blue-500"></i>
          Menampilkan <span class="font-semibold text-gray-900">{{ stockOpnames.from }}</span> - 
          <span class="font-semibold text-gray-900">{{ stockOpnames.to }}</span> dari 
          <span class="font-semibold text-gray-900">{{ stockOpnames.total }}</span> data
        </div>
        <div class="flex gap-2">
          <Link
            v-if="stockOpnames.prev_page_url"
            :href="stockOpnames.prev_page_url"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm"
          >
            <i class="fa-solid fa-chevron-left mr-2"></i>
            Prev
          </Link>
          <Link
            v-if="stockOpnames.next_page_url"
            :href="stockOpnames.next_page_url"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm"
          >
            Next
            <i class="fa-solid fa-chevron-right ml-2"></i>
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  stockOpnames: Object,
  outlets: Array,
  filters: Object,
  user_outlet_id: [String, Number],
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
  outlet_id: props.filters?.outlet_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

function applyFilters() {
  router.get(route('stock-opnames.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function getStatusClass(status) {
  const classes = {
    DRAFT: 'bg-gray-100 text-gray-700 border border-gray-300',
    SAVED: 'bg-blue-100 text-blue-700 border border-blue-300',
    SUBMITTED: 'bg-yellow-100 text-yellow-700 border border-yellow-300',
    APPROVED: 'bg-green-100 text-green-700 border border-green-300',
    REJECTED: 'bg-red-100 text-red-700 border border-red-300',
    COMPLETED: 'bg-blue-100 text-blue-700 border border-blue-300',
  };
  return classes[status] || 'bg-gray-100 text-gray-700 border border-gray-300';
}

function getStatusIcon(status) {
  const icons = {
    DRAFT: 'fa-solid fa-file',
    SAVED: 'fa-solid fa-save',
    SUBMITTED: 'fa-solid fa-paper-plane',
    APPROVED: 'fa-solid fa-check-circle',
    REJECTED: 'fa-solid fa-times-circle',
    COMPLETED: 'fa-solid fa-check-double',
  };
  return icons[status] || 'fa-solid fa-info-circle';
}

async function deleteStockOpname(id) {
  const result = await Swal.fire({
    title: 'Hapus Stock Opname?',
    text: 'Apakah Anda yakin ingin menghapus stock opname ini? Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    showLoaderOnConfirm: true,
    preConfirm: async () => {
      try {
        const response = await axios.delete(route('stock-opnames.destroy', id), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });
        return response;
      } catch (error) {
        let errorMessage = 'Gagal menghapus stock opname';
        if (error.response?.data?.message) {
          errorMessage = error.response.data.message;
        } else if (error.response?.data?.error) {
          errorMessage = error.response.data.error;
        } else if (error.message) {
          errorMessage = error.message;
        }
        Swal.showValidationMessage(errorMessage);
        return false;
      }
    },
    allowOutsideClick: () => !Swal.isLoading()
  });

  if (result.isConfirmed && result.value) {
    await Swal.fire({
      title: 'Berhasil!',
      text: 'Stock opname berhasil dihapus.',
      icon: 'success',
      confirmButtonColor: '#3085d6',
      timer: 2000,
      timerProgressBar: true
    });
    
    router.reload();
  } else if (result.isDismissed) {
    // User cancelled, do nothing
  } else {
    // Error occurred, already shown in validation message
  }
}
</script>

