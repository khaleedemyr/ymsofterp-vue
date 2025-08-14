<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  movements: Object, // { data, links, meta }
  filters: Object,
  employees: Array,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const employeeId = ref(props.filters?.employee_id || '');

const debouncedSearch = debounce(() => {
  router.get('/employee-movements', {
    search: search.value,
    status: status.value,
    employee_id: employeeId.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function onFilterChange() {
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
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium"
              >
                Tambah Employee Movement
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

              <!-- Employee Filter -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                <select
                  v-model="employeeId"
                  @change="onFilterChange"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Semua Employee</option>
                  <option
                    v-for="employee in employees"
                    :key="employee.id"
                    :value="employee.id"
                  >
                    {{ employee.nama_lengkap }} ({{ employee.nik }})
                  </option>
                </select>
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
                <tr v-for="movement in movements.data" :key="movement.id">
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
                        class="text-blue-600 hover:text-blue-900"
                      >
                        Detail
                      </button>
                      <button
                        @click="openEdit(movement)"
                        class="text-indigo-600 hover:text-indigo-900"
                      >
                        Edit
                      </button>
                      <button
                        @click="hapus(movement)"
                        class="text-red-600 hover:text-red-900"
                      >
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="movements.links && movements.links.length > 3" class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Showing {{ movements.from }} to {{ movements.to }} of {{ movements.total }} results
              </div>
              <div class="flex space-x-2">
                <button
                  v-for="link in movements.links"
                  :key="link.label"
                  @click="goToPage(link.url)"
                  :disabled="!link.url"
                  :class="[
                    'px-3 py-2 text-sm font-medium rounded-md',
                    link.active
                      ? 'bg-blue-600 text-white'
                      : link.url
                      ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                      : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                  ]"
                  v-html="link.label"
                ></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
