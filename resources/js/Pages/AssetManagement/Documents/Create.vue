<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file-upload text-blue-500"></i> Upload Document
        </h1>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Asset <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.asset_id"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Pilih Asset</option>
            <option v-for="asset in assets" :key="asset.id" :value="asset.id">
              {{ asset.asset_code }} - {{ asset.name }}
            </option>
          </select>
        </div>

        <!-- Document Type & Name -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Document Type <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.document_type"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Pilih Tipe</option>
              <option value="Invoice">Invoice</option>
              <option value="Warranty">Warranty</option>
              <option value="Manual">Manual</option>
              <option value="Maintenance Record">Maintenance Record</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Document Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              v-model="form.document_name"
              required
              placeholder="Masukkan nama document"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- File -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            File <span class="text-red-500">*</span>
          </label>
          <input
            type="file"
            @change="handleFileChange"
            required
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
          <p class="text-xs text-gray-500 mt-1">Format: PDF, Images (JPG, PNG), Documents (DOC, DOCX, XLS, XLSX), Max 10MB</p>
          <div v-if="filePreview" class="mt-4">
            <p class="text-sm text-gray-700">Selected file: <span class="font-medium">{{ filePreview.name }}</span></p>
            <p class="text-xs text-gray-500">Size: {{ formatFileSize(filePreview.size) }}</p>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Masukkan deskripsi document..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            href="/asset-management/documents"
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
            <span>{{ form.processing ? 'Uploading...' : 'Upload' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  assets: Array,
});

const form = useForm({
  asset_id: '',
  document_type: '',
  document_name: '',
  file: null,
  description: '',
});

const filePreview = ref(null);

function handleFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    filePreview.value = file;
    form.file = file;
  }
}

function formatFileSize(bytes) {
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(2)} ${units[unitIndex]}`;
}

function submit() {
  const formData = new FormData();
  formData.append('asset_id', form.asset_id);
  formData.append('document_type', form.document_type);
  formData.append('document_name', form.document_name);
  formData.append('file', form.file);
  formData.append('description', form.description || '');

  form.transform(() => formData).post('/asset-management/documents', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      router.visit('/asset-management/documents');
    },
  });
}
</script>

