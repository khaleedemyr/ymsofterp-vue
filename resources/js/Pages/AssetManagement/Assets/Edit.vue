<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Asset
        </h1>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset Code (Read-only) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Asset Code</label>
          <input
            type="text"
            v-model="form.asset_code"
            required
            disabled
            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100"
          />
        </div>

        <!-- Name & Category -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Nama Asset <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              v-model="form.name"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Kategori <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.category_id"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Pilih Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Brand & Model -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
            <div class="flex gap-2">
              <select
                v-model="form.brand_id"
                @change="onBrandChange"
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option :value="null">Pilih Brand</option>
                <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                  {{ brand.name }}
                </option>
                <option value="new">+ Tambah Brand Baru</option>
              </select>
            </div>
            <input
              v-if="showNewBrandInput"
              type="text"
              v-model="newBrandName"
              @blur="createNewBrand"
              placeholder="Masukkan nama brand baru"
              class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
            <input
              v-else-if="!form.brand_id"
              type="text"
              v-model="form.brand"
              placeholder="Atau masukkan brand manual"
              class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
            <input
              type="text"
              v-model="form.model"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Serial Number & Purchase Date -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
            <input
              type="text"
              v-model="form.serial_number"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Pembelian</label>
            <input
              type="date"
              v-model="form.purchase_date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Purchase Price & Supplier -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Pembelian</label>
            <input
              type="number"
              v-model="form.purchase_price"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
            <select
              v-model="form.supplier_id"
              @change="onSupplierChange"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Pilih Supplier</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                {{ supplier.name }}
              </option>
            </select>
            <input
              v-if="!form.supplier_id"
              type="text"
              v-model="form.supplier"
              placeholder="Atau masukkan supplier manual"
              class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Outlet & Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="form.current_outlet_id"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Tidak Terikat Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Status <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.status"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="Active">Active</option>
              <option value="Maintenance">Maintenance</option>
              <option value="Disposed">Disposed</option>
              <option value="Lost">Lost</option>
              <option value="Transfer">Transfer</option>
            </select>
          </div>
        </div>

        <!-- Useful Life & Warranty -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Useful Life (Tahun)</label>
            <input
              type="number"
              v-model="form.useful_life"
              min="1"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Warranty Expiry Date</label>
            <input
              type="date"
              v-model="form.warranty_expiry_date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Existing Photos -->
        <div v-if="existingPhotos.length > 0">
          <label class="block text-sm font-medium text-gray-700 mb-2">Existing Photos</label>
          <div class="grid grid-cols-4 gap-4">
            <div v-for="(photo, index) in existingPhotos" :key="index" class="relative">
              <img :src="`/storage/${photo}`" alt="Photo" class="w-full h-24 object-cover rounded-lg" />
              <button
                type="button"
                @click="removeExistingPhoto(index)"
                class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- New Photos -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tambah Photos</label>
          <input
            type="file"
            @change="handlePhotoChange"
            multiple
            accept="image/*"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
          <div v-if="photoPreviews.length > 0" class="mt-4 grid grid-cols-4 gap-4">
            <div v-for="(preview, index) in photoPreviews" :key="index" class="relative">
              <img :src="preview" alt="Preview" class="w-full h-24 object-cover rounded-lg" />
              <button
                type="button"
                @click="removePhoto(index)"
                class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
          <textarea
            v-model="form.description"
            rows="4"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            :href="`/asset-management/assets/${asset.id}`"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2 disabled:opacity-50"
          >
            <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
            <span>{{ form.processing ? 'Menyimpan...' : 'Simpan' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  asset: Object,
  categories: Array,
  outlets: Array,
  suppliers: Array,
  brands: Array,
});

// Find matching supplier and brand IDs
const findSupplierId = () => {
  if (!props.asset.supplier) return null;
  const supplier = props.suppliers.find(s => s.name === props.asset.supplier);
  return supplier ? supplier.id : null;
};

const findBrandId = () => {
  if (!props.asset.brand) return null;
  const brand = props.brands.find(b => b.name === props.asset.brand);
  return brand ? brand.id : null;
};

const form = useForm({
  asset_code: props.asset.asset_code || '',
  name: props.asset.name || '',
  category_id: props.asset.category_id || '',
  brand: props.asset.brand || '',
  brand_id: findBrandId(),
  model: props.asset.model || '',
  serial_number: props.asset.serial_number || '',
  purchase_date: props.asset.purchase_date || '',
  purchase_price: props.asset.purchase_price || null,
  supplier_id: findSupplierId(),
  supplier: props.asset.supplier || '',
  current_outlet_id: props.asset.current_outlet_id || null,
  status: props.asset.status || 'Active',
  photos: [],
  description: props.asset.description || '',
  useful_life: props.asset.useful_life || null,
  warranty_expiry_date: props.asset.warranty_expiry_date || '',
  keep_existing_photos: true,
});

const existingPhotos = ref(props.asset.photos || []);
const photoPreviews = ref([]);
const photoFiles = ref([]);
const showNewBrandInput = ref(false);
const newBrandName = ref('');
const isLoadingBrand = ref(false);

function handlePhotoChange(event) {
  const files = Array.from(event.target.files);
  photoFiles.value = [...photoFiles.value, ...files];
  
  files.forEach(file => {
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreviews.value.push(e.target.result);
    };
    reader.readAsDataURL(file);
  });
}

function removePhoto(index) {
  photoPreviews.value.splice(index, 1);
  photoFiles.value.splice(index, 1);
}

function removeExistingPhoto(index) {
  existingPhotos.value.splice(index, 1);
  form.keep_existing_photos = existingPhotos.value.length > 0;
}

function onSupplierChange() {
  if (form.supplier_id) {
    const supplier = props.suppliers.find(s => s.id == form.supplier_id);
    if (supplier) {
      form.supplier = supplier.name;
    }
  }
}

function onBrandChange() {
  if (form.brand_id === 'new') {
    showNewBrandInput.value = true;
    form.brand_id = null;
  } else if (form.brand_id) {
    const brand = props.brands.find(b => b.id == form.brand_id);
    if (brand) {
      form.brand = brand.name;
    }
    showNewBrandInput.value = false;
  } else {
    showNewBrandInput.value = false;
  }
}

async function createNewBrand() {
  if (!newBrandName.value || newBrandName.value.trim() === '') {
    showNewBrandInput.value = false;
    return;
  }

  isLoadingBrand.value = true;
  try {
    const response = await axios.post('/asset-management/assets/create-brand', {
      name: newBrandName.value.trim(),
    }, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (response.data?.success) {
      // Add new brand to brands list
      props.brands.push(response.data.data);
      form.brand_id = response.data.data.id;
      form.brand = response.data.data.name;
      newBrandName.value = '';
      showNewBrandInput.value = false;
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal membuat brand baru', 'error');
  } finally {
    isLoadingBrand.value = false;
  }
}

function submit() {
  const formData = new FormData();
  Object.keys(form.data()).forEach(key => {
    if (key !== 'photos') {
      const value = form[key];
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    }
  });
  
  // Add existing photos
  if (form.keep_existing_photos && existingPhotos.value.length > 0) {
    existingPhotos.value.forEach((photo, index) => {
      formData.append(`existing_photos[${index}]`, photo);
    });
  }
  
  // Add new photos
  photoFiles.value.forEach((file, index) => {
    formData.append(`photos[${index}]`, file);
  });

  form.transform(() => formData).put(`/asset-management/assets/${props.asset.id}`, {
    forceFormData: true,
    preserveScroll: true,
  });
}
</script>

