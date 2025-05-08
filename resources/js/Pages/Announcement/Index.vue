<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { debounce } from 'lodash';
import AnnouncementCreateModal from './AnnouncementCreateModal.vue'
import AnnouncementDetailModal from './AnnouncementDetailModal.vue'
import AnnouncementEditModal from './AnnouncementEditModal.vue'
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  announcements: Array,
  users: Array,
  jabatans: Array,
  divisis: Array,
  levels: Array,
  outlets: Array,
});

const filters = ref({
  search: '',
  startDate: '',
  endDate: ''
});

const showCreateModal = ref(false)
const showDetailModal = ref(false);
const showEditModal = ref(false);
const selectedAnnouncement = ref(null);

const form = useForm({
  title: '',
  content: '',
  image: null,
  files: [],
  targets: [],
});

const isPublishing = ref(false);
const isDeleting = ref(false);

const debouncedFetch = debounce(() => {
  router.get(route('announcement.index'), filters.value, {
    preserveState: true,
    replace: true,
  });
}, 400);

function onFilterChange() {
  debouncedFetch();
}

async function hapus(id) {
  const result = await Swal.fire({
    title: 'Hapus Announcement?',
    text: "Apakah Anda yakin ingin menghapus pengumuman ini?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) return;

  isDeleting.value = true;
  try {
    await router.delete(route('announcement.destroy', id));
    await Swal.fire({
      title: 'Berhasil!',
      text: 'Pengumuman berhasil dihapus!',
      icon: 'success',
      confirmButtonColor: '#3085d6',
    });
  } catch (e) {
    await Swal.fire({
      title: 'Gagal!',
      text: 'Gagal menghapus pengumuman: ' + (e.response?.data?.error || e.message),
      icon: 'error',
      confirmButtonColor: '#3085d6',
    });
  } finally {
    isDeleting.value = false;
  }
}

function detail(announcement) {
  selectedAnnouncement.value = announcement;
  showDetailModal.value = true;
}

function edit(announcement) {
  selectedAnnouncement.value = announcement;
  showEditModal.value = true;
}

function onEditSuccess() {
  debouncedFetch();
  showEditModal.value = false;
}

function onCreateSuccess() {
  // opsional: fetch ulang data
}

function formatDateOnly(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  // return d.toLocaleDateString('id-ID'); // DD/MM/YYYY
  return d.toISOString().slice(0, 10); // YYYY-MM-DD
}

async function publishAnnouncement(id) {
  const result = await Swal.fire({
    title: 'Publish Announcement?',
    text: "Apakah Anda yakin ingin mempublish pengumuman ini?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Publish!',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) return;

  isPublishing.value = true;
  try {
    await axios.post(route('announcement.publish', id));
    debouncedFetch();
    await Swal.fire({
      title: 'Berhasil!',
      text: 'Pengumuman berhasil dipublish dan notifikasi dikirim!',
      icon: 'success',
      confirmButtonColor: '#3085d6',
    });
  } catch (e) {
    await Swal.fire({
      title: 'Gagal!',
      text: 'Gagal publish: ' + (e.response?.data?.error || e.message),
      icon: 'error',
      confirmButtonColor: '#3085d6',
    });
  } finally {
    isPublishing.value = false;
  }
}
</script>

<template>
  <AppLayout title="Announcement">
    <div>
      <h1 class="text-3xl font-bold mb-6">📢 Announcement</h1>
      <button @click="showCreateModal = true" class="btn btn-primary mb-6">+ Buat Announcement</button>
      <!-- FILTER BAR -->
      <div class="flex flex-wrap gap-3 mb-6 items-end bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl px-4 py-4 shadow-md">
        <input
          v-model="filters.search"
          @input="onFilterChange"
          type="text"
          placeholder="Cari Judul Announcement..."
          class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition w-56 bg-white shadow-sm"
        />
        <input
          v-model="filters.startDate"
          @change="onFilterChange"
          type="date"
          class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition bg-white shadow-sm"
        />
        <span class="text-gray-400">-</span>
        <input
          v-model="filters.endDate"
          @change="onFilterChange"
          type="date"
          class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition bg-white shadow-sm"
        />
      </div>
      <!-- END FILTER BAR -->

      <div class="bg-white rounded-2xl shadow p-0 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="bg-blue-50">
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Judul</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Target</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">File</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="props.announcements.length === 0">
              <td colspan="6" class="text-center py-12 text-gray-400 text-base font-medium">
                Tidak ada data announcement.
              </td>
            </tr>
            <tr v-for="a in props.announcements" :key="a.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-3 font-medium">{{ a.title }}</td>
              <td class="px-4 py-3 text-xs text-gray-500">{{ formatDateOnly(a.created_at) }}</td>
              <td class="px-4 py-3 text-xs">
                <span :class="a.status === 'Publish' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs font-semibold">
                  {{ a.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span v-for="t in a.targets" :key="t.id" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs">
                    {{ t.target_type }}: {{ t.target_name || t.target_id }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <span v-for="f in a.files" :key="f.id" class="flex items-center text-xs">
                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <a :href="`/storage/${f.file_path}`" target="_blank" class="text-blue-600 underline">{{ f.file_name }}</a>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button 
                    @click="detail(a)"
                    class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-700 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Detail
                  </button>
                  <button 
                    @click="hapus(a.id)" 
                    :disabled="isDeleting"
                    class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ isDeleting ? 'Menghapus...' : 'Hapus' }}
                  </button>
                  <button
                    v-if="a.status === 'DRAFT'"
                    @click="publishAnnouncement(a.id)"
                    :disabled="isPublishing"
                    class="inline-flex items-center btn btn-xs bg-green-100 text-green-700 hover:bg-green-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg v-if="isPublishing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-green-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    {{ isPublishing ? 'Publishing...' : 'Publish' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AnnouncementCreateModal
        v-if="showCreateModal"
        :show="showCreateModal"
        :users="users"
        :jabatans="jabatans"
        :divisis="divisis"
        :levels="levels"
        :outlets="outlets"
        @close="showCreateModal = false"
        @success="onCreateSuccess"
      />

      <AnnouncementDetailModal
        v-if="showDetailModal"
        :show="showDetailModal"
        :announcement="selectedAnnouncement"
        @close="showDetailModal = false"
      />

      <AnnouncementEditModal
        v-if="showEditModal"
        :show="showEditModal"
        :announcement="selectedAnnouncement"
        :users="users"
        :jabatans="jabatans"
        :divisis="divisis"
        :levels="levels"
        :outlets="outlets"
        @close="showEditModal = false"
        @success="onEditSuccess"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: all 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>

