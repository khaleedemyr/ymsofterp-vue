<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Switch } from '@headlessui/vue';
import SupplierFormModal from './SupplierFormModal.vue';
import ImportModal from './ImportModal.vue';

const props = defineProps({
  suppliers: Object, // { data, links, meta }
  filters: Object,
});

const search = ref(props.filters?.search || '');
const showInactive = ref(false);
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedSupplier = ref(null);
const showImportModal = ref(false);

const debouncedSearch = debounce(() => {
  router.get('/suppliers', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

watch(showInactive, (val) => {
  router.get('/suppliers', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  modalMode.value = 'create';
  selectedSupplier.value = null;
  showModal.value = true;
}

function openEdit(supplier) {
  modalMode.value = 'edit';
  selectedSupplier.value = supplier;
  showModal.value = true;
}

async function hapus(supplier) {
  const result = await Swal.fire({
    title: 'Hapus Supplier?',
    text: `Yakin ingin menghapus supplier "${supplier.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('suppliers.destroy', supplier.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Supplier berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(supplier) {
  const newStatus = supplier.status === 'active' ? 'inactive' : 'active';
  router.patch(route('suppliers.toggle-status', supplier.id), { status: newStatus }, {
    preserveState: true,
    onSuccess: reload,
  });
}
</script>

<template>
  <AppLayout title="Suppliers">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Suppliers
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <div class="flex items-center space-x-4">
                <input
                  v-model="search"
                  type="text"
                  placeholder="Search suppliers..."
                  class="rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                />
              </div>
              <div class="flex items-center space-x-4">
                <button
                  @click="showImportModal = true"
                  class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                >
                  Import
                </button>
                <button
                  @click="openCreate"
                  class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                >
                  Add Supplier
                </button>
              </div>
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
                placeholder="Cari kode/nama/CP/phone/email..."
                class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>
            <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
              <table class="w-full min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Kode</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Contact Person</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="suppliers.data.length === 0">
                    <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data supplier.</td>
                  </tr>
                  <tr v-for="supplier in suppliers.data" :key="supplier.id" class="hover:bg-blue-50 transition shadow-sm">
                    <td class="px-4 py-3 font-mono font-semibold text-blue-700">{{ supplier.code }}</td>
                    <td class="px-4 py-3 font-semibold">{{ supplier.name }}</td>
                    <td class="px-4 py-3">{{ supplier.contact_person }}</td>
                    <td class="px-4 py-3">{{ supplier.phone }}</td>
                    <td class="px-4 py-3">{{ supplier.email }}</td>
                    <td class="px-4 py-3">
                      <button
                        :class="supplier.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                        class="px-2 py-1 rounded-full text-xs font-semibold shadow hover:opacity-80 transition"
                        @click="toggleStatus(supplier)"
                      >
                        {{ supplier.status }}
                      </button>
                    </td>
                    <td class="px-4 py-3">
                      <div class="flex gap-2">
                        <button @click="openEdit(supplier)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                          Edit
                        </button>
                        <button @click="hapus(supplier)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
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
                v-for="link in suppliers.links"
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
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <ImportModal
      :show="showImportModal"
      @close="showImportModal = false"
    />

    <SupplierFormModal
      :show="showModal"
      :mode="modalMode"
      :supplier="selectedSupplier"
      @close="closeModal"
      @success="reload"
    />
  </AppLayout>
</template> 