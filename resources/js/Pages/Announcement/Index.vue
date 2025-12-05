<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { debounce } from 'lodash';
import AnnouncementCreateModal from './AnnouncementCreateModal.vue'
import AnnouncementDetailModal from './AnnouncementDetailModal.vue'
import AnnouncementEditModal from './AnnouncementEditModal.vue'
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  announcements: [Array, Object],
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

// Computed property untuk menangani data announcements
const announcementsData = computed(() => {
  if (props.announcements && props.announcements.data) {
    return props.announcements.data;
  }
  return props.announcements || [];
});

// Computed property untuk pagination info
const paginationInfo = computed(() => {
  if (props.announcements && typeof props.announcements === 'object') {
    return {
      from: props.announcements.from || 0,
      to: props.announcements.to || 0,
      total: props.announcements.total || 0,
      links: props.announcements.links || []
    };
  }
  return {
    from: 0,
    to: 0,
    total: 0,
    links: []
  };
});

const debouncedFetch = debounce(() => {
  router.get(route('announcement.index'), filters.value, {
    preserveState: true,
    replace: true,
  });
}, 400);

function onFilterChange() {
  debouncedFetch();
}

function resetFilter() {
  filters.value = {
    search: '',
    startDate: '',
    endDate: ''
  };
  onFilterChange();
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
  debouncedFetch();
  showCreateModal.value = false;
}

function formatDateOnly(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString('id-ID');
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
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bullhorn text-blue-500"></i> Announcement
        </h1>
        <button 
          @click="showCreateModal = true" 
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Announcement
        </button>
      </div>

      <!-- Search & Filter -->
      <form @submit.prevent="onFilterChange" class="flex flex-wrap gap-2 mb-4 items-center">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Cari judul announcement..."
          class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200"
          style="min-width:200px"
        />
        <input
          v-model="filters.startDate"
          type="date"
          class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200"
        />
        <span class="text-gray-400">-</span>
        <input
          v-model="filters.endDate"
          type="date"
          class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-200"
        />
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded font-semibold hover:bg-blue-600 transition">Cari</button>
        <button v-if="filters.search || filters.startDate || filters.endDate" type="button" @click="resetFilter" class="ml-2 bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition">Reset</button>
      </form>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Judul</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Target</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">File</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!announcementsData || !announcementsData.length">
              <td colspan="7" class="text-center py-10 text-blue-300">Tidak ada data Announcement.</td>
            </tr>
            <tr v-for="(announcement, idx) in announcementsData" :key="announcement.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ idx + 1 }}</td>
              <td class="px-6 py-3 font-medium">{{ announcement.title }}</td>
              <td class="px-6 py-3">{{ formatDateOnly(announcement.created_at) }}</td>
              <td class="px-6 py-3">
                <span :class="announcement.status === 'Publish' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ announcement.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex flex-wrap gap-1">
                  <span v-for="t in announcement.targets" :key="t.id" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs">
                    {{ t.target_type }}: {{ t.target_name || t.target_id }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-3">
                <div class="flex flex-wrap gap-2">
                  <span v-for="f in announcement.files" :key="f.id" class="flex items-center text-xs">
                    <i class="fa fa-file mr-1 text-gray-400"></i>
                    <a :href="`/storage/${f.file_path}`" target="_blank" class="text-blue-600 hover:underline">{{ f.file_name }}</a>
                  </span>
                </div>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button 
                    @click="detail(announcement)"
                    class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition"
                  >
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button 
                    @click="edit(announcement)"
                    class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition"
                  >
                    <i class="fa fa-edit mr-1"></i> Edit
                  </button>
                  <button 
                    @click="hapus(announcement.id)" 
                    :disabled="isDeleting"
                    class="inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50"
                  >
                    <i v-if="isDeleting" class="fa fa-spinner fa-spin mr-1"></i>
                    <i v-else class="fa fa-trash mr-1"></i> Delete
                  </button>
                  <button
                    v-if="announcement.status === 'DRAFT'"
                    @click="publishAnnouncement(announcement.id)"
                    :disabled="isPublishing"
                    class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50"
                  >
                    <i v-if="isPublishing" class="fa fa-spinner fa-spin mr-1"></i>
                    <i v-else class="fa fa-check mr-1"></i> Publish
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="announcementsData && announcementsData.length > 0 && paginationInfo.links.length > 0" class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700 dark:text-gray-300">
          Menampilkan {{ paginationInfo.from }} sampai {{ paginationInfo.to }} dari {{ paginationInfo.total }} data
        </div>
        <div class="flex items-center space-x-2">
          <template v-for="link in paginationInfo.links" :key="link.label">
            <Link
              v-if="link.url"
              :href="link.url"
              v-html="link.label"
              :class="[
                'px-3 py-2 text-sm border rounded-md transition-colors cursor-pointer',
                link.active 
                  ? 'bg-indigo-600 text-white border-indigo-600' 
                  : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700'
              ]"
            />
            <span
              v-else
              v-html="link.label"
              class="px-3 py-2 text-sm border rounded-md transition-colors opacity-50 cursor-not-allowed bg-gray-100 text-gray-500 border-gray-300"
            />
          </template>
        </div>
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

