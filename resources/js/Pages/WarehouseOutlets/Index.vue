<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Switch } from '@headlessui/vue';
import WarehouseOutletFormModal from './WarehouseOutletFormModal.vue';

const props = defineProps({
  warehouseOutlets: Object, // { data, links, meta }
  filters: Object,
  outlets: Array, // Add this prop for outlets data
});

const search = ref(props.filters?.search || '');
const showInactive = ref(false);
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedWarehouseOutlet = ref(null);

const debouncedSearch = debounce(() => {
  router.get('/warehouse-outlets', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

watch(showInactive, (val) => {
  router.get('/warehouse-outlets', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  modalMode.value = 'create';
  selectedWarehouseOutlet.value = null;
  showModal.value = true;
}

function openEdit(warehouseOutlet) {
  modalMode.value = 'edit';
  selectedWarehouseOutlet.value = warehouseOutlet;
  showModal.value = true;
}

async function hapus(warehouseOutlet) {
  const result = await Swal.fire({
    title: 'Hapus Gudang Outlet?',
    text: `Yakin ingin menghapus gudang outlet "${warehouseOutlet.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('warehouse-outlets.destroy', warehouseOutlet.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Gudang outlet berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(warehouseOutlet) {
  const newStatus = warehouseOutlet.status === 'active' ? 'inactive' : 'active';
  router.patch(route('warehouse-outlets.toggle-status', warehouseOutlet.id), { status: newStatus }, {
    preserveState: true,
    onSuccess: reload,
  });
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i> Gudang Outlet
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Gudang Outlet Baru
        </button>
      </div>
      <div class="flex items-center gap-3 mb-4">
        <Switch
          v-model="showInactive"
          :class="showInactive ? 'bg-blue-600' : 'bg-gray-200'"
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
        >
          <span
            :class="showInactive ? 'translate-x-6' : 'translate-x-1'"
            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
          />
        </Switch>
        <span class="ml-2 text-sm text-gray-700">Tampilkan Inactive</span>
      </div>
      <div class="mb-4">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari kode/nama outlet/lokasi..."
          class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Kode</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nama Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nama Gudang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Lokasi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="warehouseOutlets.data.length === 0">
              <td colspan="5" class="text-center py-10 text-gray-400">Tidak ada data warehouse outlet.</td>
            </tr>
            <tr v-for="warehouseOutlet in warehouseOutlets.data" :key="warehouseOutlet.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ warehouseOutlet.code }}</td>
              <td class="px-6 py-3 font-semibold">{{ warehouseOutlet.outlet?.nama_outlet }}</td>
              <td class="px-6 py-3 font-semibold">{{ warehouseOutlet.name }}</td>
              <td class="px-6 py-3">{{ warehouseOutlet.location }}</td>
              <td class="px-6 py-3">
                <button
                  :class="warehouseOutlet.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                  class="px-2 py-1 rounded-full text-xs font-semibold shadow hover:opacity-80 transition"
                  @click="toggleStatus(warehouseOutlet)"
                >
                  {{ warehouseOutlet.status }}
                </button>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openEdit(warehouseOutlet)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    Edit
                  </button>
                  <button @click="hapus(warehouseOutlet)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in warehouseOutlets.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
      <WarehouseOutletFormModal
        :show="showModal"
        :mode="modalMode"
        :warehouse-outlet="selectedWarehouseOutlet"
        :outlets="outlets"
        @close="closeModal"
        @success="reload"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-3d {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}
</style> 