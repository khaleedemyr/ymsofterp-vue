<template>
  <AppLayout title="Contra Bon">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Contra Bon
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-medium">Daftar Contra Bon</h3>
            <Link
              :href="route('contra-bons.create')"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
            >
              Buat Contra Bon
            </Link>
          </div>

          <div class="flex gap-3 mb-4">
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Cari nomor Contra Bon..."
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            />
            <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Nomor
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tanggal
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Supplier
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    PO Number
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Dibuat Oleh
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Aksi
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="cb in contraBons" :key="cb.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ cb.number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(cb.date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ cb.supplier.name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ cb.purchase_order.number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatCurrency(cb.total_amount) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      :class="{
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                        'bg-yellow-100 text-yellow-800': cb.status === 'draft',
                        'bg-green-100 text-green-800': cb.status === 'approved',
                        'bg-red-100 text-red-800': cb.status === 'rejected'
                      }"
                    >
                      {{ cb.status.toUpperCase() }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ cb.creator.nama_lengkap }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <button @click="goToDetail(cb.id)" class="text-green-500 hover:text-green-700 mr-2" title="Detail">
                      <i class="fa fa-eye"></i>
                    </button>
                    <button @click="goToEdit(cb.id)" class="text-blue-500 hover:text-blue-700 mr-2" title="Edit">
                      <i class="fa fa-pencil-alt"></i>
                    </button>
                    
                    <button @click="confirmDelete(cb.id)" class="text-red-500 hover:text-red-700" title="Hapus">
                      <i class="fa fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  contraBons: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedStatus.value = filters?.status || '';
  },
  { immediate: true }
);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function debouncedSearch() {
  router.get('/contra-bons', { search: search.value, status: selectedStatus.value }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onStatusChange() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/contra-bons/create');
}

function openDetail(id) {
  router.visit(`/contra-bons/${id}`);
}

function goToEdit(id) {
  router.visit(`/contra-bons/${id}/edit`);
}

function goToDetail(id) {
  router.visit(`/contra-bons/${id}`);
}

function confirmDelete(id) {
  if (!id) return;
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Hapus Contra Bon?',
      text: 'Data yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.delete(`/contra-bons/${id}`, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Contra Bon berhasil dihapus!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menghapus Contra Bon', 'error');
          }
        });
      }
    });
  });
}
</script> 