<template>
  <AppLayout title="Buat Push Notification Baru">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell text-purple-500"></i> Buat Push Notification Baru
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <!-- Error Messages -->
      <div v-if="Object.keys(errors).length > 0" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
        <ul class="list-disc list-inside">
          <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
        </ul>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Target Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Target Member <span class="text-red-500">*</span>
            </label>
            <Multiselect
              v-model="selectedMembers"
              :options="memberOptions"
              :multiple="true"
              :searchable="true"
              :loading="searchLoading"
              :internal-search="false"
              :options-limit="20"
              :limit="20"
              :limit-text="count => `dan ${count} member lainnya`"
              placeholder="Cari dan pilih member atau pilih 'All' untuk semua member"
              label="label"
              track-by="email"
              @search-change="searchMembers"
              @select="onMemberSelect"
              @remove="onMemberRemove"
              :class="{ 'border-red-500': errors.txt_target }"
            >
              <template slot="option" slot-scope="props">
                <div class="flex items-center">
                  <div class="flex-1">
                    <div v-if="props.option.isAll" class="font-bold text-purple-600">
                      <i class="fa-solid fa-users mr-2"></i>All - Kirim ke Semua Member
                    </div>
                    <div v-else>
                      <div class="font-medium text-gray-900">{{ props.option.name }}</div>
                      <div class="text-sm text-gray-600">{{ props.option.email }}</div>
                      <div v-if="props.option.telepon" class="text-xs text-gray-500">{{ props.option.telepon }}</div>
                    </div>
                  </div>
                </div>
              </template>
              <template slot="tag" slot-scope="props">
                <span v-if="props.option.isAll" class="multiselect__tag bg-purple-600">
                  <span>All</span>
                  <i class="multiselect__tag-icon" @click.stop="removeMember(props.option)"></i>
                </span>
                <span v-else class="multiselect__tag">
                  <span>{{ props.option.name }}</span>
                  <i class="multiselect__tag-icon" @click.stop="removeMember(props.option)"></i>
                </span>
              </template>
              <template slot="noOptions">
                <div class="text-center py-2 text-gray-500">
                  <i class="fa-solid fa-search mr-2"></i>
                  Ketik minimal 2 karakter untuk mencari member...
                </div>
              </template>
              <template slot="noResult">
                <div class="text-center py-2 text-gray-500">
                  <i class="fa-solid fa-user-slash mr-2"></i>
                  Tidak ada member ditemukan
                </div>
              </template>
            </Multiselect>
            <p class="mt-1 text-sm text-gray-500">
              Pilih member secara individual atau pilih "All" untuk mengirim ke semua member yang memiliki firebase token.
            </p>
            <p v-if="errors.txt_target" class="text-red-500 text-sm mt-1">{{ errors.txt_target }}</p>
          </div>

          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Title <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.txt_title"
              type="text"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              :class="{ 'border-red-500': errors.txt_title }"
              placeholder="Masukkan title notifikasi"
              required
            />
            <p v-if="errors.txt_title" class="text-red-500 text-sm mt-1">{{ errors.txt_title }}</p>
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Body <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="form.txt_body"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              :class="{ 'border-red-500': errors.txt_body }"
              rows="4"
              placeholder="Masukkan isi notifikasi"
              required
            ></textarea>
            <p v-if="errors.txt_body" class="text-red-500 text-sm mt-1">{{ errors.txt_body }}</p>
          </div>

          <!-- Photo Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Foto (Opsional)
            </label>
            <input
              @change="handleFileChange"
              type="file"
              accept="image/*"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
            />
            <p class="mt-1 text-sm text-gray-500">
              Best Resolution 1024x500 px (Max: 2MB)
            </p>
            <div v-if="previewImage" class="mt-4">
              <img :src="previewImage" alt="Preview" class="max-w-xs rounded-lg shadow" />
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end gap-4 pt-6 border-t">
            <button
              type="button"
              @click="goBack"
              class="px-6 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-6 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition disabled:opacity-50"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa fa-paper-plane mr-2"></i>
              {{ loading ? 'Menyimpan...' : 'Simpan & Kirim' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import axios from 'axios';

const props = defineProps({
  errors: Object,
});

const loading = ref(false);
const previewImage = ref(null);
const selectedMembers = ref([]);
const memberOptions = ref([
  { email: 'all', name: 'All', label: 'All - Kirim ke Semua Member', isAll: true }
]);
const searchLoading = ref(false);
const searchTimeout = ref(null);

const form = useForm({
  txt_target: '',
  txt_title: '',
  txt_body: '',
  file_foto: null,
});

// Watch selectedMembers and update form.txt_target
watch(selectedMembers, (newVal) => {
  if (newVal.length === 0) {
    form.txt_target = '';
    return;
  }
  
  // Check if "all" is selected
  const hasAll = newVal.some(m => m.isAll);
  if (hasAll) {
    form.txt_target = 'all';
  } else {
    // Get emails from selected members
    const emails = newVal.map(m => m.email).filter(email => email && email !== 'all');
    form.txt_target = emails.join(',');
  }
}, { deep: true });

async function searchMembers(query) {
  if (!query || query.length < 2) {
    memberOptions.value = [
      { email: 'all', name: 'All', label: 'All - Kirim ke Semua Member', isAll: true }
    ];
    return;
  }

  clearTimeout(searchTimeout.value);
  searchLoading.value = true;

  searchTimeout.value = setTimeout(async () => {
    try {
      const response = await axios.get('/push-notification/search-members', {
        params: { search: query }
      });

      // Add "All" option at the beginning
      memberOptions.value = [
        { email: 'all', name: 'All', label: 'All - Kirim ke Semua Member', isAll: true },
        ...response.data
      ];
    } catch (error) {
      console.error('Error searching members:', error);
      memberOptions.value = [
        { email: 'all', name: 'All', label: 'All - Kirim ke Semua Member', isAll: true }
      ];
    } finally {
      searchLoading.value = false;
    }
  }, 300);
}

function onMemberSelect(option) {
  // If "All" is selected, clear other selections and only keep "All"
  if (option.isAll) {
    selectedMembers.value = [option];
  } else {
    // If selecting a specific member, remove "All" if it exists
    selectedMembers.value = selectedMembers.value.filter(m => !m.isAll);
  }
}

function onMemberRemove(option) {
  // Remove the member from selection
  selectedMembers.value = selectedMembers.value.filter(m => m.email !== option.email);
}

function removeMember(option) {
  onMemberRemove(option);
}

function handleFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire('Error', 'Ukuran file maksimal 2MB', 'error');
      event.target.value = '';
      return;
    }

    // Validate file type
    if (!file.type.startsWith('image/')) {
      Swal.fire('Error', 'File harus berupa gambar', 'error');
      event.target.value = '';
      return;
    }

    form.file_foto = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImage.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function submit() {
  // Validate that at least one member is selected
  if (selectedMembers.value.length === 0 || !form.txt_target) {
    Swal.fire('Error', 'Silakan pilih minimal 1 member atau pilih "All"', 'error');
    loading.value = false;
    return;
  }

  loading.value = true;
  
  form.post('/push-notification', {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire('Berhasil', 'Push notification berhasil dibuat!', 'success');
      router.visit('/push-notification');
    },
    onError: (errors) => {
      console.error('Error:', errors);
      Swal.fire('Error', 'Gagal membuat push notification', 'error');
    },
    onFinish: () => {
      loading.value = false;
    },
  });
}

function goBack() {
  router.visit('/push-notification');
}
</script>

<style scoped>
/* Custom styling for multiselect */
::v-deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.75rem;
}

::v-deep(.multiselect__tags) {
  min-height: 42px;
  border: 1px solid #d1d5db;
  border-radius: 0.75rem;
  padding: 8px 40px 0 8px;
}

::v-deep(.multiselect__tags:focus-within) {
  border-color: #a855f7;
  box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.2);
}

::v-deep(.multiselect__input) {
  border: none;
  padding: 0;
  margin: 0;
  min-height: 30px;
}

::v-deep(.multiselect__placeholder) {
  color: #9ca3af;
  padding-top: 8px;
  margin-bottom: 0;
}

::v-deep(.multiselect__single) {
  padding-top: 8px;
  margin-bottom: 0;
}

::v-deep(.multiselect__content-wrapper) {
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  margin-top: 4px;
}

::v-deep(.multiselect__option) {
  padding: 12px;
  min-height: auto;
}

::v-deep(.multiselect__option--highlight) {
  background: #f3e8ff;
  color: #7c3aed;
}

::v-deep(.multiselect__option--selected) {
  background: #ede9fe;
  color: #6d28d9;
  font-weight: 500;
}

::v-deep(.multiselect__tag) {
  background: #7c3aed;
  color: white;
  border-radius: 0.5rem;
  padding: 4px 8px;
  margin: 2px;
}

::v-deep(.multiselect__tag-icon) {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  width: 18px;
  height: 18px;
  line-height: 18px;
  margin-left: 4px;
}

::v-deep(.multiselect__tag-icon:hover) {
  background: rgba(255, 255, 255, 0.5);
}

::v-deep(.multiselect__tag.bg-purple-600) {
  background: #9333ea !important;
}

::v-deep(.multiselect--active .multiselect__tags) {
  border-color: #a855f7;
  box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.2);
}

/* Error state */
.border-red-500 ::v-deep(.multiselect__tags) {
  border-color: #ef4444;
}

.border-red-500 ::v-deep(.multiselect__tags:focus-within) {
  border-color: #ef4444;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}
</style>


