<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import SubCategoryFormModal from './SubCategoryFormModal.vue';
import { Switch } from '@headlessui/vue';

const props = defineProps({
  subCategories: Array,
  categories: Array,
  coas: Array,
  regions: Array,
  outlets: Array,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedSubCategory = ref(null);
const showInactive = ref(false);

watch(showInactive, (val) => {
  router.get('/sub-categories', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

const debouncedSearch = debounce(() => {
  router.get('/sub-categories', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  modalMode.value = 'create';
  selectedSubCategory.value = null;
  showModal.value = true;
}

function openEdit(sub) {
  modalMode.value = 'edit';
  selectedSubCategory.value = sub;
  showModal.value = true;
}

async function hapus(sub) {
  const result = await Swal.fire({
    title: 'Hapus Sub Kategori?',
    text: `Yakin ingin menghapus sub kategori "${sub.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('sub-categories.destroy', sub.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Sub kategori berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(sub) {
  const newStatus = sub.status === 'active' ? 'inactive' : 'active';
  router.patch(route('sub-categories.toggle-status', sub.id), { status: newStatus }, {
    preserveState: true,
    onSuccess: reload,
  });
}

const filteredSubCategories = computed(() => {
  if (!props.subCategories || !props.subCategories.data) return [];
  let arr = props.subCategories.data;
  // Filter by search (optional, since backend already filters)
  if (search.value) {
    const s = search.value.toLowerCase();
    arr = arr.filter(
      sc =>
        (sc.code && sc.code.toLowerCase().includes(s)) ||
        (sc.name && sc.name.toLowerCase().includes(s)) ||
        (sc.description && sc.description.toLowerCase().includes(s))
    );
  }
  // Filter by status (optional, since backend already filters)
  if (showInactive.value) {
    arr = arr.filter(sc => sc.status === 'inactive');
  } else {
    arr = arr.filter(sc => sc.status === 'active');
  }
  return arr;
});
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-tag text-blue-500"></i> Sub Categories
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Sub Kategori Baru
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
          placeholder="Cari sub kategori..."
          class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <!-- <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Kode</th> -->
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">CoA</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Deskripsi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Show POS</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Availability</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredSubCategories.length === 0">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data sub kategori.</td>
            </tr>
            <tr v-for="subCategory in filteredSubCategories" :key="subCategory.id" class="hover:bg-blue-50 transition shadow-sm">
              <!-- <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ subCategory.code }}</td> -->
              <td class="px-6 py-3 font-semibold">{{ subCategory.name }}</td>
              <td class="px-6 py-3">{{ categories.find(c => c.id === subCategory.category_id)?.name || '-' }}</td>
              <td class="px-6 py-3">{{ subCategory.coa ? `${subCategory.coa.code} - ${subCategory.coa.name}` : '-' }}</td>
              <td class="px-6 py-3 text-gray-500">{{ subCategory.description || '-' }}</td>
              <td class="px-6 py-3">
                <button
                  :class="subCategory.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                  class="px-2 py-1 rounded-full text-xs font-semibold shadow hover:opacity-80 transition"
                  @click="toggleStatus(subCategory)"
                >
                  {{ subCategory.status }}
                </button>
              </td>
              <td class="px-6 py-3">
                <span :class="subCategory.show_pos == 1 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ subCategory.show_pos == 1 ? 'Yes' : 'No' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div v-if="subCategory.show_pos === 1">
                  <div v-if="subCategory.availabilities && subCategory.availabilities.length > 0">
                    <div v-for="avail in subCategory.availabilities" :key="avail.id" class="mb-1">
                      <span v-if="avail.availability_type === 'byRegion' && avail.region">
                        Region: {{ avail.region.name }}
                      </span>
                      <span v-else-if="avail.availability_type === 'byOutlet' && avail.outlet">
                        Outlet: {{ avail.outlet.nama_outlet }}
                      </span>
                    </div>
                  </div>
                  <span v-else class="text-gray-400">All Outlets</span>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openEdit(subCategory)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    Edit
                  </button>
                  <button @click="hapus(subCategory)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination (optional, if you have pagination) -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in props.subCategories.links"
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
      <SubCategoryFormModal
        :show="showModal"
        :mode="modalMode"
        :sub-category="selectedSubCategory"
        :categories="categories"
        :coas="coas"
        :regions="regions"
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