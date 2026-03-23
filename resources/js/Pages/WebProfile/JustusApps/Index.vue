<script setup>
import { ref, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  blocks: { type: Object, default: () => ({ data: [] }) },
  hero_image_url: { type: String, default: null },
  hero_image_path: { type: String, default: null },
  hero_media_type: { type: String, default: null },
  playstore_url: { type: String, default: '' },
  appstore_url: { type: String, default: '' },
});

const page = usePage();
const search = ref('');
const heroFile = ref(null);
const removeHero = ref(false);
const playstoreUrl = ref(props.playstore_url || '');
const appstoreUrl = ref(props.appstore_url || '');

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
  if (flash.error) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonText: 'OK' });
  }
});

function handleSearch() {
  router.get('/web-profile/justus-apps', { search: search.value }, {
    preserveState: true,
    replace: true,
  });
}

function submitSettings() {
  const fd = new FormData();
  if (heroFile.value) fd.append('hero_image', heroFile.value);
  if (removeHero.value) fd.append('remove_hero', '1');
  fd.append('playstore_url', playstoreUrl.value || '');
  fd.append('appstore_url', appstoreUrl.value || '');

  router.post('/web-profile/justus-apps/settings', fd, {
    forceFormData: true,
    onSuccess: () => {
      heroFile.value = null;
      removeHero.value = false;
    },
  });
}

async function destroyBlock(id) {
  const row = props.blocks.data.find((b) => b.id === id);
  const result = await Swal.fire({
    title: 'Hapus blok?',
    text: `Hapus blok "${row?.title || id}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (result.isConfirmed) {
    router.delete(`/web-profile/justus-apps/${id}`, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Berhasil', 'Blok dihapus.', 'success'),
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Justus Apps">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Justus Apps Page</h1>
        <Link href="/web-profile/justus-apps/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fa-solid fa-plus mr-2"></i> Tambah Blok
        </Link>
      </div>

      <div class="bg-white rounded-lg shadow p-6 mb-8 space-y-5">
        <h2 class="text-lg font-semibold text-gray-800">Pengaturan Header & Link Store</h2>
        <div v-if="hero_image_url" class="mb-2">
          <video
            v-if="hero_media_type === 'video'"
            :src="hero_image_url"
            controls
            muted
            playsinline
            class="max-h-56 rounded border border-gray-200"
          />
          <img v-else :src="hero_image_url" alt="Hero" class="max-h-56 rounded border border-gray-200" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <InputLabel value="Link Play Store" />
            <TextInput v-model="playstoreUrl" class="mt-1 w-full" placeholder="https://play.google.com/store/apps/details?id=..." />
          </div>
          <div>
            <InputLabel value="Link App Store" />
            <TextInput v-model="appstoreUrl" class="mt-1 w-full" placeholder="https://apps.apple.com/..." />
          </div>
        </div>

        <div class="flex flex-wrap items-end gap-4">
          <div>
            <InputLabel value="Upload head banner" />
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,.mp4,.webm"
              class="mt-1 block text-sm"
              @change="(e) => { heroFile = e.target.files?.[0] || null; removeHero = false; }"
            />
          </div>
          <label v-if="hero_image_path" class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="removeHero" type="checkbox" />
            Hapus head banner
          </label>
          <PrimaryButton type="button" @click="submitSettings">Simpan Pengaturan</PrimaryButton>
        </div>
      </div>

      <div class="mb-4 flex gap-2">
        <TextInput v-model="search" class="flex-1" placeholder="Cari judul blok..." @keyup.enter="handleSearch" />
        <PrimaryButton @click="handleSearch">Search</PrimaryButton>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Judul</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aktif</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in blocks.data" :key="row.id">
              <td class="px-4 py-2 text-sm">{{ row.sort_order }}</td>
              <td class="px-4 py-2 text-sm">
                <img v-if="row.image_url" :src="row.image_url" class="h-12 w-20 rounded object-cover border" alt="" />
                <span v-else>-</span>
              </td>
              <td class="px-4 py-2 text-sm font-medium">{{ row.title || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ row.is_active ? 'Ya' : 'Tidak' }}</td>
              <td class="px-4 py-2 text-sm text-right">
                <Link :href="`/web-profile/justus-apps/${row.id}/edit`" class="text-blue-600 hover:underline mr-3">Edit</Link>
                <button type="button" class="text-red-600 hover:underline" @click="destroyBlock(row.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!blocks.data || blocks.data.length === 0" class="text-center py-8 text-gray-500">
        Belum ada konten Justus Apps. Klik Tambah Blok.
      </div>

      <div v-if="blocks.links && blocks.links.length > 3" class="mt-4 flex justify-center gap-2">
        <Link
          v-for="link in blocks.links"
          :key="link.label"
          :href="link.url || '#'"
          :class="[
            'px-4 py-2 rounded-lg',
            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : '',
          ]"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

