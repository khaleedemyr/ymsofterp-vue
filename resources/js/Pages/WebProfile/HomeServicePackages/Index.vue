<script setup>
import { ref, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  packages: { type: Object, default: () => ({ data: [] }) },
  hero_image_url: { type: String, default: null },
  hero_image_path: { type: String, default: null },
  hero_title: { type: String, default: '' },
  hero_subtitle: { type: String, default: '' },
});

const page = usePage();
const search = ref('');
const heroFile = ref(null);
const removeHero = ref(false);
const heroIsVideo = ref(false);
const heroTitle = ref(props.hero_title || '');
const heroSubtitle = ref(props.hero_subtitle || '');

onMounted(() => {
  const flash = page.props.flash || {};
  heroIsVideo.value = /\.(mp4|webm)(\?|$)/i.test(String(props.hero_image_url || ''));
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
  if (flash.error) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonText: 'OK' });
  }
});

function handleSearch() {
  router.get('/web-profile/home-service-packages', { search: search.value }, {
    preserveState: true,
    replace: true,
  });
}

function submitHero() {
  if (!heroFile.value && !removeHero.value) {
    Swal.fire({ icon: 'info', title: 'Pilih file baru (gambar/video) atau centang hapus latar.' });
    return;
  }
  const fd = new FormData();
  if (heroFile.value) {
    fd.append('hero_image', heroFile.value);
  }
  fd.append('hero_title', heroTitle.value || '');
  fd.append('hero_subtitle', heroSubtitle.value || '');
  if (removeHero.value) {
    fd.append('remove_hero', '1');
  }
  router.post('/web-profile/home-service-packages/hero', fd, {
    forceFormData: true,
    onSuccess: () => {
      heroFile.value = null;
      removeHero.value = false;
    },
  });
}

async function destroyPkg(id) {
  const row = props.packages.data.find((p) => p.id === id);
  const result = await Swal.fire({
    title: 'Hapus paket?',
    text: `Hapus "${row?.title || id}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (result.isConfirmed) {
    router.delete(`/web-profile/home-service-packages/${id}`, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Berhasil', 'Paket dihapus.', 'success'),
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Home Service">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Home Service Menu (Web)</h1>
        <div class="flex flex-wrap gap-2">
          <Link
            href="/web-profile/home-service-landing"
            class="bg-rose-600 text-white px-4 py-2 rounded-lg hover:bg-rose-700 text-sm"
          >
            <i class="fa-solid fa-layer-group mr-2"></i> Landing page
          </Link>
          <Link
            href="/web-profile/home-service-packages/create"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            <i class="fa-solid fa-plus mr-2"></i> Tambah Paket
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Media latar header (halaman Home Service)</h2>
        <p class="text-sm text-gray-600 mb-4">Opsional. Bisa gambar atau video; ditampilkan di belakang judul &amp; logo brand di website.</p>
        <div class="grid gap-4 mb-4 md:grid-cols-2">
          <div>
            <InputLabel value="Title" />
            <TextInput v-model="heroTitle" class="mt-1 w-full" placeholder="HOME SERVICE" />
          </div>
          <div>
            <InputLabel value="Subtitle" />
            <textarea
              v-model="heroSubtitle"
              rows="3"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
              placeholder="daily canteen dining ...&#10;Guided by efficiency ..."
            />
          </div>
        </div>
        <div v-if="hero_image_url" class="mb-4">
          <video
            v-if="heroIsVideo"
            :src="hero_image_url"
            class="max-h-48 rounded border border-gray-200"
            controls
            playsinline
            preload="metadata"
          />
          <img v-else :src="hero_image_url" alt="Hero" class="max-h-48 rounded border border-gray-200" />
        </div>
        <div class="flex flex-wrap items-end gap-4">
          <div>
            <InputLabel value="Upload media baru" />
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,.mp4,.webm"
              class="mt-1 block text-sm"
              @change="(e) => { heroFile = e.target.files?.[0] || null; removeHero = false; }"
            />
            <p class="mt-2 max-w-3xl text-xs text-gray-500">
              Justus Nest: gambar memenuhi area header (lebar penuh layar, tinggi mengikuti blok judul &amp; logo) dengan crop dari tengah; ada overlay gelap di atasnya.
              Disarankan landscape <strong>1920×1080</strong> atau <strong>2560×1440</strong> (retina), minimal lebar <strong>1920 px</strong>; titik fokus di tengah frame.
              Untuk video: MP4/WEBM landscape 16:9. Batas upload media <strong>50 MB</strong>.
            </p>
          </div>
          <label v-if="hero_image_path" class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="removeHero" type="checkbox" />
            Hapus gambar latar
          </label>
          <PrimaryButton type="button" @click="submitHero">Simpan latar</PrimaryButton>
        </div>
      </div>

      <div class="mb-4 flex gap-2">
        <TextInput v-model="search" class="flex-1" placeholder="Cari nama paket..." @keyup.enter="handleSearch" />
        <PrimaryButton @click="handleSearch">Search</PrimaryButton>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Brand</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aktif</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in packages.data" :key="row.id">
              <td class="px-4 py-2 text-sm">{{ row.sort_order }}</td>
              <td class="px-4 py-2 text-sm">{{ row.brand?.title || '-' }}</td>
              <td class="px-4 py-2 text-sm font-medium">{{ row.title }}</td>
              <td class="px-4 py-2 text-sm">{{ row.price_label || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ row.is_active ? 'Ya' : 'Tidak' }}</td>
              <td class="px-4 py-2 text-sm text-right">
                <Link
                  :href="`/web-profile/home-service-packages/${row.id}/edit`"
                  class="text-blue-600 hover:underline mr-3"
                >Edit</Link>
                <button type="button" class="text-red-600 hover:underline" @click="destroyPkg(row.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!packages.data || packages.data.length === 0" class="text-center py-8 text-gray-500">
        Belum ada paket. Klik Tambah Paket — tombol brand di website hanya muncul jika brand punya paket aktif.
      </div>

      <div v-if="packages.links && packages.links.length > 3" class="mt-4 flex justify-center gap-2">
        <Link
          v-for="link in packages.links"
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
