<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i> Items
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Add New Item
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              placeholder="Search by name or SKU..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              v-model="categoryFilter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="statusFilter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Export Dropdown -->
      <div class="relative inline-block text-left mb-4 export-dropdown">
        <button @click="showExportDropdown = !showExportDropdown"
          class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-file-export"></i> Export
          <i class="fa-solid fa-chevron-down ml-2"></i>
        </button>
        <div v-if="showExportDropdown" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
          <div class="py-1">
            <button @click="exportFile('excel')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <i class="fa-solid fa-file-excel text-green-600 mr-2"></i> Export to Excel
            </button>
          </div>
        </div>
      </div>

      <!-- Import Dropdown -->
      <div class="relative inline-block text-left mb-4 import-dropdown">
        <button @click="showImportDropdown = !showImportDropdown"
          class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-file-import"></i> Import
          <i class="fa-solid fa-chevron-down ml-2"></i>
        </button>
        <div v-if="showImportDropdown" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
          <div class="py-1">
            <button @click="downloadTemplate" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <i class="fa-solid fa-download text-blue-600 mr-2"></i> Download Template Import
            </button>
            <button @click="openImportFile" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <i class="fa-solid fa-upload text-green-600 mr-2"></i> Import File
            </button>
            <input type="file" ref="importFileInput" class="hidden" @change="handleFileChange" accept=".xlsx,.xls" />
          </div>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Item
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Category
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                SKU
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Type
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="items.data.length === 0">
              <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data item.</td>
            </tr>
            <tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <img
                      v-if="item.images && item.images.length > 0 && getImageUrl(item.images[0])"
                      :src="getImageUrl(item.images[0])"
                      class="h-10 w-10 rounded-full object-cover cursor-pointer"
                      @click="openLightbox(item)"
                      @error="(e) => e.target.style.display='none'"
                    />
                    <div
                      v-else
                      class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center"
                    >
                      <i class="fa-solid fa-box text-gray-400"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">{{ item.name }}</div>
                    <div class="text-sm text-gray-500">{{ item.warehouse_division_id }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ item.category?.name }}</div>
                <div class="text-sm text-gray-500">{{ item.sub_category?.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ item.sku }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    item.type === 'product' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800',
                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full'
                  ]"
                >
                  {{ item.type }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <label class="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="item.status === 'active'"
                    @change="toggleStatus(item)"
                    class="sr-only peer"
                  />
                  <div
                    class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:bg-green-500 transition"
                  ></div>
                  <span
                    class="ml-2 text-xs font-bold"
                    :class="item.status === 'active' ? 'text-green-700' : 'text-red-700'"
                  >
                    {{ item.status }}
                  </span>
                </label>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end gap-2">
                  <button @click="openDetail(item)" class="text-gray-600 hover:text-gray-900">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button @click="openEdit(item)" class="text-blue-600 hover:text-blue-900">
                    <i class="fa-solid fa-pen-to-square"></i>
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
          v-for="link in items.links"
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
      <ItemFormModal
        :show="showModal"
        :mode="modalMode"
        :item="selectedItem"
        :categories="categories"
        :subCategories="subCategories"
        :units="units"
        :warehouseDivisions="warehouseDivisions"
        :menuTypes="menuTypes"
        :regions="regions"
        :outlets="outlets"
        :bomItems="bomItems"
        :modifiers="modifiers"
        @close="showModal = false"
        @success="handleSuccess"
      />
      <ModalDetailItem
        :open="modalDetailOpen"
        :item="selectedDetailItem"
        :modifiers="modifiers"
        :regions="regions"
        :outlets="outlets"
        @close="modalDetailOpen = false"
      />
      <EditItemModal
        :show="showEditModal"
        :item="selectedEditItem"
        :categories="categories"
        :subCategories="subCategories"
        :units="units"
        :warehouseDivisions="warehouseDivisions"
        :menuTypes="menuTypes"
        :regions="regions"
        :outlets="outlets"
        :bomItems="bomItems"
        :modifiers="modifiers"
        @close="showEditModal = false"
        @success="handleSuccess"
      />
      <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
      />
      <!-- Modal Preview Import -->
      <div v-if="importPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 relative max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i class="fa-solid fa-eye text-blue-500"></i> Preview Import Items
          </h2>
          <button @click="closeImportPreview" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
            <i class="fa-solid fa-xmark text-2xl"></i>
          </button>
          <div v-if="importPreviewData.header.length">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 mb-4">
                <thead class="bg-gray-50">
                  <tr>
                    <th v-for="h in importPreviewData.header" :key="h" class="px-3 py-2 text-xs font-bold text-gray-700 uppercase">{{ h }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in importPreviewData.preview" :key="idx">
                    <td v-for="h in importPreviewData.header" :key="h" class="px-3 py-1 text-xs text-gray-800">{{ row[h] }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="importUploading" class="mb-4">
              <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                <div class="bg-blue-600 h-4 rounded-full" :style="{ width: importProgress + '%' }"></div>
              </div>
              <div class="text-xs text-gray-700">Uploading... {{ importProgress }}%</div>
            </div>
            <div v-if="importResults.length">
              <h3 class="font-bold mb-2">Hasil Import:</h3>
              <div class="max-h-48 overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-xs font-bold text-gray-700 uppercase">Row</th>
                      <th class="px-3 py-2 text-xs font-bold text-gray-700 uppercase">Name</th>
                      <th class="px-3 py-2 text-xs font-bold text-gray-700 uppercase">Status</th>
                      <th class="px-3 py-2 text-xs font-bold text-gray-700 uppercase">Message</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in importResults" :key="r.row" :class="r.status === 'success' ? 'bg-green-50' : 'bg-red-50'">
                      <td class="px-3 py-1 text-xs">{{ r.row }}</td>
                      <td class="px-3 py-1 text-xs">{{ r.name }}</td>
                      <td class="px-3 py-1 text-xs font-bold" :class="r.status === 'success' ? 'text-green-700' : 'text-red-700'">{{ r.status }}</td>
                      <td class="px-3 py-1 text-xs">{{ r.message }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button @click="closeImportPreview" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">Tutup</button>
              <button v-if="!importUploading && !importResults.length" @click="handleImportUpload" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2">
                <i class="fa-solid fa-upload"></i> Upload
              </button>
            </div>
          </div>
          <div v-else class="text-gray-500">Tidak ada data yang bisa dipreview.</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import ItemFormModal from './ItemFormModal.vue';
import ModalDetailItem from './ModalDetailItem.vue';
import EditItemModal from './EditItemModal.vue';
import VueEasyLightbox from 'vue-easy-lightbox'
import axios from 'axios'
import { saveAs } from 'file-saver'

const props = defineProps({
  items: Object, // { data, links, meta }
  categories: Array,
  subCategories: Array,
  units: Array,
  warehouseDivisions: Array,
  menuTypes: Array,
  regions: Array,
  outlets: Array,
  bomItems: Array,
  modifiers: Array
});

const search = ref('');
const categoryFilter = ref('');
const statusFilter = ref('');
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedItem = ref(null);
const modalDetailOpen = ref(false);
const selectedDetailItem = ref({});
const showEditModal = ref(false);
const selectedEditItem = ref(null);
const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)
const showExportDropdown = ref(false)
const showImportDropdown = ref(false)
const importFileInput = ref(null)
const importPreviewModal = ref(false)
const importPreviewData = ref({ header: [], preview: [] })
const importFile = ref(null)
const importUploading = ref(false)
const importProgress = ref(0)
const importResults = ref([])

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

const deleteItem = async (item) => {
  const result = await Swal.fire({
    title: 'Hapus Item?',
    text: `Yakin ingin menghapus item "${item.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('items.destroy', item.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Item berhasil di-nonaktifkan!', 'success'),
    onError: () => Swal.fire('Gagal', 'Gagal menghapus item.', 'error'),
  });
};

function openCreate() {
  modalMode.value = 'create';
  selectedItem.value = null;
  showModal.value = true;
}

function openEdit(item) {
  selectedEditItem.value = item;
  showEditModal.value = true;
}

async function openDetail(item) {
  // Ambil data dari list yang sudah lengkap (mapping dari controller index)
  selectedDetailItem.value = { ...item };
  modalDetailOpen.value = true;
}

function handleSuccess() {
  showModal.value = false;
  reload();
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

// Add a computed property for image handling
const getImageUrl = (image) => {
  if (!image || !image.path) return null;
  try {
    return `/storage/${image.path}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
}

const toggleStatus = async (item) => {
  const newStatus = item.status === 'active' ? 'inactive' : 'active';
  const result = await Swal.fire({
    title: `Ubah status item?`,
    text: `Yakin ingin mengubah status item \"${item.name}\" menjadi ${newStatus}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Ubah!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  await window.axios.post(route('items.toggleStatus', item.id), { status: newStatus });
  reload();
};

function openLightbox(item) {
  if (!item.images || item.images.length === 0) return
  lightboxImages.value = item.images.map(img => `/storage/${img.path}`)
  lightboxIndex.value = 0
  lightboxVisible.value = true
}

function handleExportDropdown(e) {
  if (!e.target.closest('.export-dropdown')) showExportDropdown.value = false
}

function handleImportDropdown(e) {
  if (!e.target.closest('.import-dropdown')) showImportDropdown.value = false
}

if (typeof window !== 'undefined') {
  window.addEventListener('click', handleExportDropdown)
  window.addEventListener('click', handleImportDropdown)
}

function downloadTemplate() {
  window.location.href = route('items.import.template')
}

function openImportFile() {
  importFileInput.value && importFileInput.value.click()
}

async function handleFileChange(e) {
  const file = e.target.files[0]
  if (file) {
    importFile.value = file
    importPreviewData.value = { header: [], preview: [] }
    importResults.value = []
    importProgress.value = 0
    importUploading.value = false
    // Preview
    const formData = new FormData()
    formData.append('file', file)
    try {
      const res = await axios.post(route('items.import.preview'), formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      importPreviewData.value = res.data
      importPreviewModal.value = true
    } catch (err) {
      Swal.fire('Error', 'Gagal membaca file: ' + (err.response?.data?.message || err.message), 'error')
    }
  }
}

async function handleImportUpload() {
  if (!importFile.value) return
  importUploading.value = true
  importProgress.value = 0
  importResults.value = []
  const formData = new FormData()
  formData.append('file', importFile.value)
  try {
    const res = await axios.post(route('items.import.excel'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          importProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        }
      }
    })
    importResults.value = res.data.results
    importUploading.value = false
    importProgress.value = 100
  } catch (err) {
    importUploading.value = false
    Swal.fire('Error', 'Gagal import file: ' + (err.response?.data?.message || err.message), 'error')
  }
}

function closeImportPreview() {
  importPreviewModal.value = false
  importFile.value = null
  importPreviewData.value = { header: [], preview: [] }
  importResults.value = []
  importProgress.value = 0
  importUploading.value = false
}

watch([search, categoryFilter, statusFilter], () => {
  router.get(route('items.index'), {
    search: search.value,
    category: categoryFilter.value,
    status: statusFilter.value,
  }, {
    preserveState: true,
    replace: true,
    only: ['items'],
  });
});

async function exportFile(type) {
  Swal.fire({
    title: 'Exporting...',
    text: 'Please wait while the file is being generated.',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  try {
    const url = type === 'excel' ? route('items.export.excel') : route('items.export.pdf');
    const response = await axios.get(url, { responseType: 'blob' });
    const fileName = type === 'excel' ? 'items.xlsx' : 'items.pdf';
    saveAs(response.data, fileName);
    Swal.close();
  } catch (e) {
    Swal.fire('Error', 'Failed to export file.', 'error');
  }
}
</script> 