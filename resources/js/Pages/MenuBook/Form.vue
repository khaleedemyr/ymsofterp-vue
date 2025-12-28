<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6 pb-4 border-b border-amber-200">
      <h2 class="text-3xl font-serif text-amber-900">
        <i class="fa-solid fa-upload mr-3 text-amber-600"></i>
        {{ props.editingPage ? 'Edit Page' : 'Add New Page' }}
      </h2>
      <button
        @click="$emit('close')"
        class="w-10 h-10 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
      >
        <i class="fa-solid fa-times"></i>
      </button>
    </div>

    <form @submit.prevent="submitForm" class="space-y-6">
      <!-- Image Upload -->
      <div>
        <label class="block text-sm font-semibold text-amber-800 mb-2">
          <i class="fa-solid fa-image mr-2"></i>
          Menu Page Image *
        </label>
        <div class="mt-2">
          <div
            v-if="!previewImage && !form?.image"
            @click="triggerFileInput"
            class="border-2 border-dashed border-amber-300 rounded-xl p-8 text-center cursor-pointer hover:border-amber-500 hover:bg-amber-50 transition-all duration-300"
          >
            <i class="fa-solid fa-cloud-arrow-up text-4xl text-amber-400 mb-4"></i>
            <p class="text-amber-700 font-medium">Click to upload image</p>
            <p class="text-sm text-amber-500 mt-2">PNG, JPG, GIF up to 10MB</p>
          </div>
          <div v-else class="relative">
            <img
              :src="previewImage || getImageUrl(form?.image)"
              alt="Preview"
              class="w-full h-auto rounded-xl shadow-lg max-h-96 object-contain bg-amber-50"
            />
            <button
              type="button"
              @click="removeImage"
              class="absolute top-2 right-2 w-10 h-10 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-110"
            >
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            @change="handleImageChange"
            class="hidden"
          />
          <div v-if="errors.image" class="mt-2 text-sm text-red-600">{{ errors.image }}</div>
        </div>
      </div>

      <!-- Page Order -->
      <div>
        <label class="block text-sm font-semibold text-amber-800 mb-2">
          <i class="fa-solid fa-sort-numeric-up mr-2"></i>
          Page Order
        </label>
        <input
          v-model.number="form.page_order"
          type="number"
          min="1"
          class="w-full px-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
          placeholder="Auto (will be last)"
        />
        <p class="mt-1 text-sm text-amber-600">Leave empty to add at the end</p>
      </div>

      <!-- Menu Items Selection -->
      <div>
        <label class="block text-sm font-semibold text-amber-800 mb-2">
          <i class="fa-solid fa-utensils mr-2"></i>
          Menu Items on This Page
        </label>
        <div class="relative mb-2">
          <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-amber-600"></i>
          <input
            v-model="itemSearch"
            type="text"
            placeholder="Search items..."
            class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
          />
        </div>
        <div class="border-2 border-amber-200 rounded-xl p-4 max-h-64 overflow-y-auto bg-white">
          <div v-if="filteredItems.length === 0" class="text-center text-amber-500 py-4">
            <i class="fa-solid fa-inbox text-2xl mb-2"></i>
            <p>No items found</p>
          </div>
          <div v-else class="space-y-2">
            <label
              v-for="item in filteredItems"
              :key="item.id"
              class="flex items-center p-3 rounded-lg hover:bg-amber-50 cursor-pointer transition-all duration-200 border border-transparent hover:border-amber-200"
            >
              <input
                type="checkbox"
                :value="item.id"
                v-model="form.item_ids"
                class="w-5 h-5 text-amber-600 border-amber-300 rounded focus:ring-amber-500"
              />
              <div class="ml-3 flex-1">
                <p class="font-medium text-amber-900">{{ item.name }}</p>
                <p class="text-sm text-amber-600">
                  {{ item.category?.name }}
                  <span v-if="item.sub_category"> / {{ item.sub_category?.name }}</span>
                </p>
              </div>
            </label>
          </div>
        </div>
        <p class="mt-2 text-sm text-amber-600">
          Selected: {{ form.item_ids?.length || 0 }} item(s)
        </p>
      </div>

      <!-- Categories Selection -->
      <div>
        <label class="block text-sm font-semibold text-amber-800 mb-2">
          <i class="fa-solid fa-tags mr-2"></i>
          Categories & Sub Categories
        </label>
        <div class="space-y-3">
            <div
              v-for="(category, index) in form.categories"
            :key="index"
            class="flex gap-3 items-end p-4 bg-amber-50 rounded-xl border border-amber-200"
          >
            <div class="flex-1">
              <label class="block text-sm font-medium text-amber-700 mb-1">Category</label>
              <select
                v-model="category.category_id"
                class="w-full px-4 py-2 rounded-lg border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
              >
                <option value="">Select Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>
            <div class="flex-1">
              <label class="block text-sm font-medium text-amber-700 mb-1">Sub Category (Optional)</label>
              <select
                v-model="category.sub_category_id"
                :disabled="!category.category_id"
                class="w-full px-4 py-2 rounded-lg border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white disabled:bg-amber-100 disabled:cursor-not-allowed"
              >
                <option value="">Select Sub Category</option>
                <option
                  v-for="subCat in getSubCategoriesByCategory(category.category_id)"
                  :key="subCat.id"
                  :value="subCat.id"
                >
                  {{ subCat.name }}
                </option>
              </select>
            </div>
            <button
              type="button"
              @click="removeCategory(index)"
              class="w-10 h-10 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-all duration-300 hover:scale-110"
              :disabled="form.categories?.length === 1"
            >
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
          <button
            type="button"
            @click="addCategory"
            class="w-full py-2 rounded-lg border-2 border-dashed border-amber-300 hover:border-amber-500 text-amber-700 hover:text-amber-900 transition-all duration-300 bg-amber-50 hover:bg-amber-100"
          >
            <i class="fa-solid fa-plus mr-2"></i>
            Add Category
          </button>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-4 pt-4 border-t border-amber-200">
        <button
          type="button"
          @click="$emit('close')"
          class="flex-1 py-3 rounded-xl border-2 border-amber-300 text-amber-700 hover:bg-amber-50 font-semibold transition-all duration-300"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="processing"
          class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white font-semibold shadow-lg transform hover:scale-105 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
        >
          <span v-if="processing">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i>
            Processing...
          </span>
          <span v-else>
            <i class="fa-solid fa-check mr-2"></i>
            {{ props.editingPage ? 'Update Page' : 'Add Page' }}
          </span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  menuBookId: {
    type: Number,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  subCategories: {
    type: Array,
    default: () => [],
  },
  editingPage: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'success']);

const fileInput = ref(null);
const itemSearch = ref('');
const previewImage = ref(null);
const processing = ref(false);
const errors = ref({});

// Initialize form as reactive object to prevent undefined errors
const form = reactive({
  image: null,
  page_order: null,
  item_ids: [],
  categories: [{ category_id: '', sub_category_id: '' }],
});

const filteredItems = computed(() => {
  if (!itemSearch.value) {
    return props.items || [];
  }
  const search = itemSearch.value.toLowerCase();
  return (props.items || []).filter(item =>
    item.name?.toLowerCase().includes(search) ||
    item.sku?.toLowerCase().includes(search)
  );
});

const getSubCategoriesByCategory = (categoryId) => {
  if (!categoryId) return [];
  return (props.subCategories || []).filter(sub => sub.category_id === parseInt(categoryId));
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    form.image = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImage.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const removeImage = () => {
  form.image = null;
  previewImage.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const addCategory = () => {
  form.categories.push({ category_id: '', sub_category_id: '' });
};

const removeCategory = (index) => {
  if (form.categories.length > 1) {
    form.categories.splice(index, 1);
  }
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return null;
  try {
    // Jika File object, gunakan object URL
    if (imagePath instanceof File) {
      return URL.createObjectURL(imagePath);
    }
    // Jika sudah full URL, return langsung
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      return imagePath;
    }
    // Jika sudah dimulai dengan /storage/, return langsung
    if (imagePath.startsWith('/storage/')) {
      return imagePath;
    }
    // Jika path relatif, tambahkan /storage/
    return `/storage/${imagePath}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
};

const submitForm = () => {
  if (!props.menuBookId) {
    errors.value = { menuBookId: 'Menu Book ID is required' };
    return;
  }

  if (!form.image && !props.editingPage) {
    errors.value = { image: 'Image is required' };
    return;
  }

  processing.value = true;
  errors.value = {};
  
  const formData = new FormData();
  
  formData.append('menu_book_id', props.menuBookId);
  if (form.image) {
    formData.append('image', form.image);
  }
  if (form.page_order) {
    formData.append('page_order', form.page_order);
  }
  if (form.item_ids && form.item_ids.length > 0) {
    formData.append('item_ids', JSON.stringify(form.item_ids));
  }
  if (form.categories && form.categories.length > 0) {
    formData.append('categories', JSON.stringify(form.categories.filter(c => c.category_id)));
  }

  const url = props.editingPage
    ? `/menu-book/page/${props.editingPage.id}`
    : `/menu-book/${props.menuBookId}/page`;

  // Add method spoofing for PUT
  if (props.editingPage) {
    formData.append('_method', 'PUT');
  }

  const config = {
    forceFormData: true,
    onSuccess: () => {
      emit('success');
    },
    onError: (inertiaErrors) => {
      // Inertia.js onError receives validation errors directly
      // Format: { field1: ['error1'], field2: ['error2'] }
      if (inertiaErrors) {
        errors.value = inertiaErrors;
      } else {
        errors.value = { general: 'An error occurred. Please try again.' };
      }
      processing.value = false;
    },
    onFinish: () => {
      processing.value = false;
    },
  };

  router.post(url, formData, config);
};

// Initialize form if editing
watch(() => props.editingPage, (page) => {
  if (page) {
    form.page_order = page.page_order;
    form.item_ids = page.items?.map(item => item.id) || [];
    form.categories = page.categories?.map(cat => ({
      category_id: cat.id,
      sub_category_id: cat.pivot?.sub_category_id || null,
    })) || [{ category_id: '', sub_category_id: '' }];
    if (page.image) {
      previewImage.value = getImageUrl(page.image);
    }
  } else {
    // Reset form when not editing
    form.image = null;
    form.page_order = null;
    form.item_ids = [];
    form.categories = [{ category_id: '', sub_category_id: '' }];
    previewImage.value = null;
  }
}, { immediate: true });
</script>

