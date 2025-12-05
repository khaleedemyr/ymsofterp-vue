<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  groups: Object, // { data, links, meta }
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const debouncedSearch = debounce(() => {
  router.get('/video-tutorial-groups', {
    search: search.value,
    status: status.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/video-tutorial-groups/create');
}

function openEdit(group) {
  router.visit(`/video-tutorial-groups/${group.id}/edit`);
}

function openShow(group) {
  router.visit(`/video-tutorial-groups/${group.id}`);
}

async function hapus(group) {
  const result = await Swal.fire({
    title: 'Hapus Group Video Tutorial?',
    text: `Yakin ingin menghapus group "${group.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('video-tutorial-groups.destroy', group.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Group video tutorial berhasil dihapus!', 'success'),
  });
}

async function toggleStatus(group) {
  const statusText = group.status === 'A' ? 'dinonaktifkan' : 'diaktifkan';
  router.patch(route('video-tutorial-groups.toggle-status', group.id), {}, {
    onSuccess: () => Swal.fire('Berhasil', `Group video tutorial berhasil ${statusText}!`, 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([status], () => {
  router.get('/video-tutorial-groups', {
    search: search.value,
    status: status.value,
  }, { preserveState: true, replace: true });
});
</script>

<template>
  <AppLayout title="Data Group Video Tutorial">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-folder text-green-500"></i> Data Group Video Tutorial
        </h1>
        <div class="flex gap-2">
          <button @click="router.visit('/video-tutorials')" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-video mr-2"></i> Video Tutorial
          </button>
          <button @click="openCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Group
          </button>
        </div>
      </div>
      
      <div class="mb-4 flex gap-4">
        <select v-model="status" class="form-input rounded-xl">
          <option value="">Semua Status</option>
          <option value="A">Active</option>
          <option value="N">Inactive</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nama group, deskripsi..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-green-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Group</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Deskripsi</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jumlah Video</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Video Aktif</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal Dibuat</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="group in groups.data" :key="group.id" class="hover:bg-green-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-semibold text-gray-900">{{ group.name }}</td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900">{{ group.description || '-' }}</div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {{ group.videos_count }} video
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {{ group.active_videos_count }} video
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <button @click="toggleStatus(group)" :class="[
                  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition',
                  group.status === 'A' 
                    ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                    : 'bg-red-100 text-red-800 hover:bg-red-200'
                ]">
                  {{ group.status_text }}
                </button>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                {{ group.creator?.name || '-' }}
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                {{ new Date(group.created_at).toLocaleDateString('id-ID') }}
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(group)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(group)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(group)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="groups.data.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data group video tutorial</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="mt-4 flex justify-end">
        <nav v-if="groups.links && groups.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in groups.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-green-600 text-white' : 'bg-white text-green-700 hover:bg-green-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template> 