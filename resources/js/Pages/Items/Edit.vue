<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i> Edit Item
        </h1>
        <Link
          :href="route('items.index')"
          class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-800">Basic Information</h2>
              
              <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select
                  v-model="form.category_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                >
                  <option value="">Select Category</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Sub Category</label>
                <select
                  v-model="form.sub_category_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                >
                  <option value="">Select Sub Category</option>
                  <option v-for="subCategory in subCategories" :key="subCategory.id" :value="subCategory.id">
                    {{ subCategory.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Warehouse Division ID</label>
                <input
                  type="text"
                  v-model="form.warehouse_division_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">SKU</label>
                <input
                  type="text"
                  v-model="form.sku"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select
                  v-model="form.type"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                >
                  <option value="product">Product</option>
                  <option value="service">Service</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input
                  type="text"
                  v-model="form.name"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                />
              </div>
            </div>

            <!-- Additional Information -->
            <div class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-800">Additional Information</h2>

              <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea
                  v-model="form.description"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                ></textarea>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Specification</label>
                <textarea
                  v-model="form.specification"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                ></textarea>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Minimum Stock</label>
                <input
                  type="number"
                  v-model="form.min_stock"
                  min="0"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select
                  v-model="form.status"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>

            <!-- Unit Information -->
            <div class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-800">Unit Information</h2>

              <div>
                <label class="block text-sm font-medium text-gray-700">Small Unit</label>
                <select
                  v-model="form.small_unit_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  required
                >
                  <option value="">Select Small Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">
                    {{ unit.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Medium Unit</label>
                <select
                  v-model="form.medium_unit_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                  <option value="">Select Medium Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">
                    {{ unit.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Large Unit</label>
                <select
                  v-model="form.large_unit_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                  <option value="">Select Large Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">
                    {{ unit.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Medium Conversion Quantity</label>
                <input
                  type="number"
                  v-model="form.medium_conversion_qty"
                  min="0"
                  step="0.01"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Small Conversion Quantity</label>
                <input
                  type="number"
                  v-model="form.small_conversion_qty"
                  min="0"
                  step="0.01"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Images -->
            <div class="space-y-4">
              <h2 class="text-lg font-semibold text-gray-800">Images</h2>
              <div>
                <label class="block text-sm font-medium text-gray-700">Upload Images</label>
                <input
                  type="file"
                  @change="handleImageUpload"
                  multiple
                  accept="image/*"
                  class="mt-1 block w-full"
                />
              </div>
              <div class="grid grid-cols-3 gap-4">
                <!-- Existing Images -->
                <div v-for="image in item.images" :key="image.id" class="relative">
                  <img :src="'/storage/' + image.path" class="w-full h-32 object-cover rounded-lg" />
                  <button
                    @click="removeExistingImage(image.id)"
                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"
                  >
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
                <!-- New Images -->
                <div v-for="(image, index) in previewImages" :key="index" class="relative">
                  <img :src="image" class="w-full h-32 object-cover rounded-lg" />
                  <button
                    @click="removeImage(index)"
                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"
                  >
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <button
              type="submit"
              class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2"
              :disabled="form.processing"
            >
              <i class="fa-solid fa-save"></i>
              Update Item
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  categories: {
    type: Array,
    required: true
  },
  subCategories: {
    type: Array,
    required: true
  },
  units: {
    type: Array,
    required: true
  }
});

const form = useForm({
  category_id: props.item.category_id,
  sub_category_id: props.item.sub_category_id,
  warehouse_division_id: props.item.warehouse_division_id,
  sku: props.item.sku,
  type: props.item.type,
  name: props.item.name,
  description: props.item.description,
  specification: props.item.specification,
  small_unit_id: props.item.small_unit_id,
  medium_unit_id: props.item.medium_unit_id,
  large_unit_id: props.item.large_unit_id,
  medium_conversion_qty: props.item.medium_conversion_qty,
  small_conversion_qty: props.item.small_conversion_qty,
  min_stock: props.item.min_stock,
  status: props.item.status,
  images: [],
  deleted_images: []
});

const previewImages = ref([]);

const handleImageUpload = (event) => {
  const files = event.target.files;
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    form.images.push(file);
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImages.value.push(e.target.result);
    };
    reader.readAsDataURL(file);
  }
};

const removeImage = (index) => {
  form.images.splice(index, 1);
  previewImages.value.splice(index, 1);
};

const removeExistingImage = (imageId) => {
  form.deleted_images.push(imageId);
};

const submit = () => {
  form.put(route('items.update', props.item.id), {
    onSuccess: () => {
      form.reset();
      previewImages.value = [];
    }
  });
};
</script> 