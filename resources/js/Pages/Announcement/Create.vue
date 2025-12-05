<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  users: Array,
  jabatans: Array,
  divisis: Array,
  levels: Array,
  outlets: Array,
});

const tab = ref('user');
const selectedTargets = ref({
  user: [],
  jabatan: [],
  divisi: [],
  level: [],
  outlet: [],
});

const form = useForm({
  title: '',
  image: null,
  files: [],
  targets: [],
});

function handleSubmit() {
  // Gabungkan semua target ke form.targets
  form.targets = [];
  Object.entries(selectedTargets.value).forEach(([type, arr]) => {
    arr.forEach(id => form.targets.push({ type, id }));
  });

  form.post(route('announcement.store'), {
    forceFormData: true,
  });
}

function getTabIcon(tab) {
  const icons = {
    user: 'fa fa-user',
    jabatan: 'fa fa-id-badge',
    divisi: 'fa fa-building',
    level: 'fa fa-layer-group',
    outlet: 'fa fa-store'
  };
  return icons[tab] || 'fa fa-users';
}

function getTabLabel(tab) {
  const labels = {
    user: 'User',
    jabatan: 'Jabatan',
    divisi: 'Divisi',
    level: 'Level',
    outlet: 'Outlet'
  };
  return labels[tab] || tab;
}

function getTotalSelectedTargets() {
  return Object.values(selectedTargets.value).reduce((total, arr) => total + arr.length, 0);
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/announcement')" class="text-blue-500 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali ke Announcement
        </button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-bullhorn text-blue-500"></i> Buat Announcement
        </h1>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <form @submit.prevent="handleSubmit">
          <!-- Judul -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-heading mr-2 text-blue-500"></i>Judul Announcement
            </label>
            <input 
              v-model="form.title" 
              type="text"
              required
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              placeholder="Masukkan judul announcement..."
            />
          </div>

          <!-- Header Image -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-image mr-2 text-blue-500"></i>Header Image (Opsional)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition-colors">
              <input 
                type="file" 
                @change="e => form.image = e.target.files[0]" 
                accept="image/*"
                class="hidden"
                id="imageInput"
              />
              <label for="imageInput" class="cursor-pointer">
                <i class="fa fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                <p class="text-gray-600">Klik untuk memilih gambar atau drag & drop</p>
                <p class="text-sm text-gray-500 mt-1">PNG, JPG, GIF hingga 5MB</p>
              </label>
            </div>
            <div v-if="form.image" class="mt-2 text-sm text-green-600">
              <i class="fa fa-check mr-1"></i>File dipilih: {{ form.image.name }}
            </div>
          </div>

          <!-- Lampiran File -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-paperclip mr-2 text-blue-500"></i>Lampiran File (Opsional)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition-colors">
              <input 
                type="file" 
                multiple 
                @change="e => form.files = Array.from(e.target.files)"
                class="hidden"
                id="filesInput"
              />
              <label for="filesInput" class="cursor-pointer">
                <i class="fa fa-folder-open text-4xl text-gray-400 mb-2"></i>
                <p class="text-gray-600">Klik untuk memilih file atau drag & drop</p>
                <p class="text-sm text-gray-500 mt-1">Bisa memilih multiple file</p>
              </label>
            </div>
            <div v-if="form.files.length > 0" class="mt-2">
              <p class="text-sm text-green-600 mb-2">
                <i class="fa fa-check mr-1"></i>{{ form.files.length }} file dipilih:
              </p>
              <div class="space-y-1">
                <div v-for="(file, index) in form.files" :key="index" class="text-sm text-gray-600 bg-gray-50 px-3 py-1 rounded">
                  <i class="fa fa-file mr-2"></i>{{ file.name }}
                </div>
              </div>
            </div>
          </div>

          <!-- Pilih Target -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-users mr-2 text-blue-500"></i>Pilih Target
            </label>
            
            <!-- Tab Navigation -->
            <div class="flex flex-wrap gap-2 mb-4">
              <button 
                v-for="t in ['user','jabatan','divisi','level','outlet']" 
                :key="t"
                type="button"
                :class="[
                  'px-4 py-2 rounded-lg font-medium transition-all',
                  tab === t 
                    ? 'bg-blue-500 text-white shadow-lg' 
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                ]"
                @click="tab = t"
              >
                <i :class="getTabIcon(t)" class="mr-2"></i>
                {{ getTabLabel(t) }}
              </button>
            </div>

            <!-- Tab Content -->
            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
              <div v-if="tab === 'user'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Pilih User:</label>
                <select v-model="selectedTargets.user" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
                </select>
              </div>
              
              <div v-else-if="tab === 'jabatan'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Pilih Jabatan:</label>
                <select v-model="selectedTargets.jabatan" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option v-for="j in props.jabatans" :key="j.id_jabatan" :value="j.id_jabatan">{{ j.nama_jabatan }}</option>
                </select>
              </div>
              
              <div v-else-if="tab === 'divisi'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Pilih Divisi:</label>
                <select v-model="selectedTargets.divisi" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option v-for="d in props.divisis" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
                </select>
              </div>
              
              <div v-else-if="tab === 'level'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Pilih Level:</label>
                <select v-model="selectedTargets.level" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option v-for="l in props.levels" :key="l.id" :value="l.id">{{ l.nama_level }}</option>
                </select>
              </div>
              
              <div v-else-if="tab === 'outlet'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Pilih Outlet:</label>
                <select v-model="selectedTargets.outlet" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                </select>
              </div>
            </div>

            <!-- Selected Targets Summary -->
            <div v-if="getTotalSelectedTargets() > 0" class="mt-3 p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-700">
                <i class="fa fa-info-circle mr-1"></i>
                Total target dipilih: <span class="font-semibold">{{ getTotalSelectedTargets() }}</span>
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
            <button 
              type="button" 
              @click="$inertia.visit('/announcement')"
              class="px-6 py-3 text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 transition-all font-medium"
            >
              <i class="fa fa-times mr-2"></i>Batal
            </button>
            <button 
              type="submit" 
              :disabled="form.processing"
              class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl hover:shadow-lg transition-all font-medium disabled:opacity-50"
            >
              <i v-if="form.processing" class="fa fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa fa-paper-plane mr-2"></i>
              {{ form.processing ? 'Menyimpan...' : 'Kirim Announcement' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
