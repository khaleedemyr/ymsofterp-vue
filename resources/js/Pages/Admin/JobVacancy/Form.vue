<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl p-8 relative animate-fadeIn max-h-[92vh] overflow-y-auto">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <h2 class="text-xl font-bold mb-4 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-briefcase"></i> {{ form.id ? 'Edit' : 'Tambah' }} Lowongan
      </h2>
      <form @submit.prevent="submitForm" enctype="multipart/form-data">
        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/50 p-4">
          <h3 class="text-sm font-bold text-blue-800 mb-3">Info Posisi & Rekrutmen</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
              <label class="block font-semibold mb-1">Posisi/Jabatan</label>
              <input v-model="form.position" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
            </div>
            <div>
              <label class="block font-semibold mb-1">Area Penempatan</label>
              <input v-model="form.location" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
            </div>
            <div>
              <label class="block font-semibold mb-1">Kategori Lowongan</label>
              <select v-model="form.job_scope" required class="w-full border rounded px-3 py-2" :disabled="isLoading">
                <option value="outlet">Outlet</option>
                <option value="head_office">Head Office</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block font-semibold mb-1">PIC Rekrutmen</label>
              <Multiselect
                v-model="selectedPics"
                :options="userOptions"
                :multiple="true"
                :searchable="true"
                :internal-search="false"
                :loading="userSearchLoading"
                :disabled="isLoading"
                label="label"
                track-by="id"
                placeholder="Cari & pilih user PIC..."
                select-label=""
                deselect-label=""
                selected-label=""
                @search-change="searchUsers"
              />
            </div>
            <div>
              <label class="block font-semibold mb-1">Kebutuhan (orang)</label>
              <input
                v-model.number="form.headcount_needed"
                type="number"
                min="0"
                class="w-full border rounded px-3 py-2"
                :disabled="isLoading || form.is_hold"
              />
            </div>
            <div class="flex items-end pb-2">
              <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input v-model="form.is_hold" type="checkbox" class="rounded border-gray-300" :disabled="isLoading" />
                HOLD (posisi ditunda)
              </label>
            </div>
            <div>
              <label class="block font-semibold mb-1">Tgl Mulai Pencarian</label>
              <input v-model="form.search_start_date" type="date" class="w-full border rounded px-3 py-2" :disabled="isLoading" />
            </div>
            <div>
              <label class="block font-semibold mb-1">Tgl Target Fulfill</label>
              <input v-model="form.target_fulfill_date" type="date" class="w-full border rounded px-3 py-2" :disabled="isLoading" />
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="block font-semibold mb-1">Deskripsi Pekerjaan</label>
          <textarea
            v-model="form.description"
            required
            class="w-full border rounded px-3 py-2 min-h-[120px] resize-y"
            rows="4"
            :disabled="isLoading"
          />
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Kualifikasi/Persyaratan</label>
          <textarea
            v-model="form.requirements"
            class="w-full border rounded px-3 py-2 min-h-[100px] resize-y"
            rows="4"
            :disabled="isLoading"
          />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block font-semibold mb-1">Tanggal Penutupan</label>
            <input type="date" v-model="form.closing_date" required class="w-full border rounded px-3 py-2" :disabled="isLoading" />
          </div>
          <div>
            <label class="block font-semibold mb-1">Status</label>
            <select v-model="form.is_active" class="w-full border rounded px-3 py-2" :disabled="isLoading">
              <option :value="1">Aktif</option>
              <option :value="0">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="block font-semibold mb-1">Banner/Logo</label>
          <input type="file" @change="onFileChange" accept="image/*" class="w-full" :disabled="isLoading" />
          <div v-if="previewBanner" class="mt-2">
            <img :src="previewBanner" class="w-40 h-24 object-cover rounded shadow" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300" :disabled="isLoading">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 flex items-center gap-2" :disabled="isLoading">
            <span v-if="isLoading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
            {{ isLoading ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { jaToastError, jaToastSuccess } from '@/Composables/useJustAcademyUi';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({ job: Object });
const emit = defineEmits(['close', 'saved']);

const emptyForm = () => ({
  id: null,
  position: '',
  description: '',
  requirements: '',
  location: '',
  job_scope: 'outlet',
  closing_date: '',
  is_active: 1,
  banner: null,
  headcount_needed: null,
  is_hold: false,
  search_start_date: '',
  target_fulfill_date: '',
});

const form = ref(emptyForm());
const selectedPics = ref([]);
const userOptions = ref([]);
const userSearchLoading = ref(false);
const previewBanner = ref('');
const isLoading = ref(false);

function toDateInput(value) {
  if (!value) return '';
  if (typeof value === 'string' && value.length >= 10) return value.slice(0, 10);
  return '';
}

function mapUserOption(user) {
  return {
    id: user.id,
    nama_lengkap: user.nama_lengkap,
    email: user.email,
    label: user.label || `${user.nama_lengkap} (${user.email})`,
  };
}

async function searchUsers(query) {
  userSearchLoading.value = true;
  try {
    const { data } = await axios.get('/admin/job-vacancy/users/search', { params: { q: query || '' } });
    const selectedIds = new Set(selectedPics.value.map((u) => u.id));
    const fetched = (data || []).map(mapUserOption);
    userOptions.value = [
      ...selectedPics.value,
      ...fetched.filter((u) => !selectedIds.has(u.id)),
    ];
  } finally {
    userSearchLoading.value = false;
  }
}

function applyJobToForm(val) {
  if (!val) {
    form.value = emptyForm();
    selectedPics.value = [];
    previewBanner.value = '';
    searchUsers('');
    return;
  }

  const cfg = val.recruitment_config || {};
  form.value = {
    id: val.id,
    position: val.position,
    description: val.description,
    requirements: val.requirements || '',
    location: val.location,
    job_scope: val.job_scope || 'outlet',
    closing_date: toDateInput(val.closing_date),
    is_active: val.is_active,
    banner: null,
    headcount_needed: cfg.headcount_needed ?? null,
    is_hold: Boolean(cfg.is_hold),
    search_start_date: toDateInput(cfg.search_start_date),
    target_fulfill_date: toDateInput(cfg.target_fulfill_date),
  };
  selectedPics.value = (val.pics || []).map(mapUserOption);
  userOptions.value = [...selectedPics.value];
  previewBanner.value = val.banner ? `/storage/${val.banner}` : '';
  searchUsers('');
}

watch(() => props.job, applyJobToForm, { immediate: true });

function onFileChange(e) {
  const file = e.target.files[0];
  form.value.banner = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (ev) => { previewBanner.value = ev.target.result; };
    reader.readAsDataURL(file);
  } else if (props.job?.banner) {
    previewBanner.value = `/storage/${props.job.banner}`;
  } else {
    previewBanner.value = '';
  }
}

async function submitForm() {
  try {
    isLoading.value = true;

    const data = new FormData();
    data.append('position', form.value.position);
    data.append('description', form.value.description);
    data.append('requirements', form.value.requirements || '');
    data.append('location', form.value.location);
    data.append('job_scope', form.value.job_scope);
    data.append('closing_date', form.value.closing_date);
    data.append('is_active', form.value.is_active ? 1 : 0);
    data.append('headcount_needed', form.value.headcount_needed ?? '');
    data.append('is_hold', form.value.is_hold ? 1 : 0);
    data.append('search_start_date', form.value.search_start_date || '');
    data.append('target_fulfill_date', form.value.target_fulfill_date || '');
    selectedPics.value.forEach((user) => data.append('pic_user_ids[]', user.id));
    if (form.value.banner) data.append('banner', form.value.banner);

    let url = '/admin/job-vacancy';
    let method = 'post';
    if (form.value.id) {
      url += `/${form.value.id}`;
      method = 'post';
      data.append('_method', 'PUT');
    }

    await axios({ method, url, data, headers: { 'Content-Type': 'multipart/form-data' } });

    await jaToastSuccess(
      form.value.id ? 'Lowongan berhasil diperbarui!' : 'Lowongan berhasil ditambahkan!',
    );

    emit('saved');
    emit('close');
  } catch (error) {
    await jaToastError(
      error.response?.data?.message || 'Terjadi kesalahan saat menyimpan lowongan.',
    );
  } finally {
    isLoading.value = false;
  }
}
</script>

<style scoped>
:deep(.multiselect) {
  min-height: 42px;
}
:deep(.multiselect__tags) {
  min-height: 42px;
  padding-top: 8px;
}
</style>
