<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  announcement: Object,
  users: Array,
  jabatans: Array,
  divisis: Array,
  levels: Array,
  outlets: Array,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  title: props.announcement?.title || '',
  content: props.announcement?.content || '',
  image: null,
  files: [],
  targets: props.announcement?.targets?.map(t => ({
    type: t.target_type,
    id: t.target_id
  })) || [],
});

// Debug: Log form data
console.log('Form initialized:', {
  title: form.title,
  content: form.content,
  targets: form.targets
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  
  // Debug: Log form data before submit
  console.log('Form data before submit:', {
    title: form.title,
    content: form.content,
    targets: form.targets,
    hasTitle: !!form.title,
    titleLength: form.title?.length || 0
  });
  
  // Client-side validation
  if (!form.title || form.title.trim() === '') {
    Swal.fire({
      title: 'Gagal!',
      text: 'Judul harus diisi!',
      icon: 'error',
      confirmButtonColor: '#3085d6',
    });
    isSubmitting.value = false;
    return;
  }
  
  if (!form.targets || form.targets.length === 0) {
    Swal.fire({
      title: 'Gagal!',
      text: 'Minimal harus ada satu target!',
      icon: 'error',
      confirmButtonColor: '#3085d6',
    });
    isSubmitting.value = false;
    return;
  }
  
  try {
    await form.put(route('announcement.update', props.announcement.id), {
      onSuccess: () => {
        Swal.fire({
          title: 'Berhasil!',
          text: 'Pengumuman berhasil diupdate!',
          icon: 'success',
          confirmButtonColor: '#3085d6',
        });
        emit('success');
        closeModal();
      },
      onError: (errors) => {
        Swal.fire({
          title: 'Gagal!',
          text: Object.values(errors)[0],
          icon: 'error',
          confirmButtonColor: '#3085d6',
        });
      }
    });
  } finally {
    isSubmitting.value = false;
  }
}

function closeModal() {
  emit('close');
}

function addTarget() {
  form.targets.push({ type: 'user', id: '' });
}

function removeTarget(index) {
  form.targets.splice(index, 1);
}

function handleFileChange(e) {
  form.files = Array.from(e.target.files);
}

function handleImageChange(e) {
  form.image = e.target.files[0];
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M12 20h9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16.5 3.5a2.121 2.121 0 113 3L7 19.5 3 21l1.5-4L16.5 3.5z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">Edit Announcement</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Judul *</label>
            <input type="text" v-model="form.title" required
                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                   placeholder="Masukkan judul pengumuman">
            <div v-if="!form.title || form.title.trim() === ''" class="text-red-500 text-sm mt-1">
              Judul harus diisi
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Content</label>
            <textarea v-model="form.content" rows="4"
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Gambar</label>
            <input type="file" @change="handleImageChange" accept="image/*"
                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">File Tambahan</label>
            <input type="file" @change="handleFileChange" multiple
                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
          </div>
          <div>
            <div class="flex justify-between items-center">
              <label class="block text-sm font-medium text-gray-700">Target</label>
              <button type="button" @click="addTarget"
                      class="text-sm text-blue-600 hover:text-blue-800">
                + Tambah Target
              </button>
            </div>
            <div v-for="(target, index) in form.targets" :key="index" class="mt-2 flex gap-2">
              <select v-model="target.type"
                      class="focus:ring-blue-500 focus:border-blue-500 block w-1/3 shadow-sm sm:text-sm border-gray-300 rounded-md">
                <option value="user">User</option>
                <option value="jabatan">Jabatan</option>
                <option value="divisi">Divisi</option>
                <option value="level">Level</option>
                <option value="outlet">Outlet</option>
              </select>
              <select v-model="target.id"
                      class="focus:ring-blue-500 focus:border-blue-500 block w-2/3 shadow-sm sm:text-sm border-gray-300 rounded-md">
                <option value="">Pilih {{ target.type }}</option>
                <option v-if="target.type === 'user'" v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.nama_lengkap }}
                </option>
                <option v-if="target.type === 'jabatan'" v-for="jabatan in jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">
                  {{ jabatan.nama_jabatan }}
                </option>
                <option v-if="target.type === 'divisi'" v-for="divisi in divisis" :key="divisi.id" :value="divisi.id">
                  {{ divisi.nama_divisi }}
                </option>
                <option v-if="target.type === 'level'" v-for="level in levels" :key="level.id" :value="level.id">
                  {{ level.nama_level }}
                </option>
                <option v-if="target.type === 'outlet'" v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <button type="button" @click="removeTarget(index)"
                      class="text-red-600 hover:text-red-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
        <button type="button"
                @click="submit"
                :disabled="isSubmitting"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
        </button>
        <button type="button"
                @click="closeModal"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
          Batal
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px);}
  to { opacity: 1; transform: translateY(0);}
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 