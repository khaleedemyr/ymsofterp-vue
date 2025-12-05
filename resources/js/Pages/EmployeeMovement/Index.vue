<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  movements: Object, // { data, links, meta }
  filters: Object,
  employees: Array,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const selectedEmployee = ref(null);

// Convert employees array to multiselect format
const employeeOptions = ref(props.employees.map(emp => ({
  id: emp.id,
  name: `${emp.nama_lengkap} (${emp.nik})`,
  nik: emp.nik
})));

// Set selected employee if filter is applied
if (props.filters?.employee_id) {
  const employee = props.employees.find(emp => emp.id == props.filters.employee_id);
  if (employee) {
    selectedEmployee.value = {
      id: employee.id,
      name: `${employee.nama_lengkap} (${employee.nik})`,
      nik: employee.nik
    };
  }
}

const debouncedSearch = debounce(() => {
  router.get('/employee-movements', {
    search: search.value,
    status: status.value,
    employee_id: selectedEmployee.value?.id || '',
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function onFilterChange() {
  debouncedSearch();
}

function onEmployeeChange() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/employee-movements/create');
}

function openEdit(movement) {
  router.visit(`/employee-movements/${movement.id}/edit`);
}

function openShow(movement) {
  router.visit(`/employee-movements/${movement.id}`);
}

async function hapus(movement) {
  const result = await Swal.fire({
    title: 'Hapus Employee Movement?',
    text: `Yakin ingin menghapus employee movement untuk "${movement.employee_name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  router.delete(route('employee-movements.destroy', movement.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Employee movement berhasil dihapus!', 'success'),
  });
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'pending':
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
    case 'pending':
      return 'Pending';
    case 'approved':
      return 'Approved';
    case 'rejected':
      return 'Rejected';
    default:
      return status;
  }
}
</script>

<template>
  <AppLayout title="Employee Movement">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Employee Movement
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <!-- Header -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900">Daftar Employee Movement</h3>
              <button
                @click="openCreate"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center space-x-2"
              >
                <i class="fas fa-plus"></i>
                <span>Tambah Employee Movement</span>
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="p-6 border-b border-gray-200 bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Search -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input
                  v-model="search"
                  @input="onSearchInput"
                  type="text"
                  placeholder="Cari nama, NIK, atau email..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Status Filter -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select
                  v-model="status"
                  @change="onFilterChange"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">Semua Status</option>
                  <option value="draft">Draft</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>

              <!-- Employee Filter with Multiselect -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                <Multiselect
                  v-model="selectedEmployee"
                  :options="employeeOptions"
                  :searchable="true"
                  :clear-on-select="false"
                  :close-on-select="true"
                  :show-labels="false"
                  track-by="id"
                  label="name"
                  placeholder="Pilih employee..."
                  @select="onEmployeeChange"
                  @remove="onEmployeeChange"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Employee
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Position
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Division
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Created At
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="movement in movements.data" :key="movement.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">
                        {{ movement.employee_name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ movement.nik }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ movement.employee_position || movement.nama_jabatan || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ movement.employee_division || movement.nama_divisi || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      :class="[
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                        getStatusBadgeClass(movement.status)
                      ]"
                    >
                      {{ getStatusText(movement.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ new Date(movement.created_at).toLocaleDateString('id-ID') }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                      <button
                        @click="openShow(movement)"
                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                        title="Detail"
                      >
                        <i class="fas fa-eye mr-1"></i>
                        Detail
                      </button>
                      <button
                        @click="openEdit(movement)"
                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                        title="Edit"
                      >
                        <i class="fas fa-edit mr-1"></i>
                        Edit
                      </button>
                      <button
                        @click="hapus(movement)"
                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                        title="Hapus"
                      >
                        <i class="fas fa-trash mr-1"></i>
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Enhanced Pagination -->
          <div v-if="movements.links && movements.links.length > 3" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                <span class="font-medium">{{ movements.from }}</span>
                to
                <span class="font-medium">{{ movements.to }}</span>
                of
                <span class="font-medium">{{ movements.total }}</span>
                results
              </div>
              <div class="flex items-center space-x-1">
                <!-- Previous Page -->
                <button
                  v-if="movements.prev_page_url"
                  @click="goToPage(movements.prev_page_url)"
                  class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50"
                >
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button
                  v-else
                  disabled
                  class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-300 rounded-l-md cursor-not-allowed"
                >
                  <i class="fas fa-chevron-left"></i>
                </button>

                <!-- Page Numbers -->
                <template v-for="(link, index) in movements.links" :key="index">
                  <button
                    v-if="link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                    @click="goToPage(link.url)"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 text-sm font-medium border',
                      link.active
                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                    ]"
                    v-html="link.label"
                  ></button>
                </template>

                <!-- Next Page -->
                <button
                  v-if="movements.next_page_url"
                  @click="goToPage(movements.next_page_url)"
                  class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50"
                >
                  <i class="fas fa-chevron-right"></i>
                </button>
                <button
                  v-else
                  disabled
                  class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-300 rounded-r-md cursor-not-allowed"
                >
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- No Data Message -->
          <div v-if="movements.data.length === 0" class="px-6 py-12 text-center">
            <div class="text-gray-500">
              <i class="fas fa-inbox text-4xl mb-4"></i>
              <p class="text-lg font-medium">Tidak ada data employee movement</p>
              <p class="text-sm">Coba ubah filter pencarian Anda</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Multiselect styling */
:deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__input) {
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__single) {
  background: transparent;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option) {
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
