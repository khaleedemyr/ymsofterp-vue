<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  slides: {
    type: Object,
    default: () => ({ data: [] }),
  },
});

const search = ref('');

function handleSearch() {
  router.get('/web-profile/promo-slides', { search: search.value }, {
    preserveState: true,
    replace: true,
  });
}

function thumb(slide) {
  if (slide.image_url) return slide.image_url;
  if (slide.image && slide.image.includes('.')) return `/storage/${slide.image}`;
  return null;
}

async function destroySlide(id) {
  const slide = props.slides.data.find((s) => s.id === id);
  const result = await Swal.fire({
    title: 'Hapus slide?',
    text: `Yakin hapus promo "${slide?.title || 'tanpa judul'}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (result.isConfirmed) {
    router.delete(`/web-profile/promo-slides/${id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Promo slide dihapus.', 'success');
      },
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Promo Slider (Home)">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Promo Slider (Homepage)</h1>
        <Link
          href="/web-profile/promo-slides/create"
          class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          <i class="fa-solid fa-plus mr-2" /> Tambah slide
        </Link>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-sm text-amber-900">
        <p class="font-semibold mb-1">Spesifikasi gambar (disarankan)</p>
        <ul class="list-disc pl-5 space-y-1.5">
          <li>Tampil <strong>full width</strong> di homepage (tanpa kotak samping); hindari banner portrait.</li>
          <li><strong>Ukuran utama:</strong> <code class="rounded bg-amber-100/80 px-1">1920 × 600 px</code> (rasio ± 3,2:1) atau <code class="rounded bg-amber-100/80 px-1">1600 × 500 px</code>.</li>
          <li><strong>Mobile:</strong> konten penting (teks/logo promo) taruh di <strong>tengah ±40% lebar</strong> supaya tetap terbaca saat layar sempit.</li>
          <li><strong>Validasi upload:</strong> minimal ±800×240 px; rasio lebar:tinggi antara ±1,7:1 dan 5,5:1.</li>
          <li>Format JPG/PNG/WEBP, maks. 10MB. Opsional: <strong>Link URL</strong> untuk klik slide.</li>
        </ul>
      </div>

      <div class="mb-4 flex gap-2">
        <TextInput
          v-model="search"
          type="text"
          placeholder="Cari judul..."
          class="flex-1"
          @keyup.enter="handleSearch"
        />
        <PrimaryButton @click="handleSearch">Search</PrimaryButton>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preview</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urutan</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="slide in props.slides.data" :key="slide.id">
              <td class="px-6 py-4">
                <img
                  v-if="thumb(slide)"
                  :src="thumb(slide)"
                  alt=""
                  class="w-40 h-16 object-cover rounded border border-gray-200"
                >
                <span v-else class="text-gray-400 text-sm">—</span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900">{{ slide.title || '—' }}</td>
              <td class="px-6 py-4 text-sm text-blue-700 max-w-xs truncate">
                <a v-if="slide.link_url" :href="slide.link_url" target="_blank" rel="noopener">{{ slide.link_url }}</a>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-6 py-4">
                <span
                  :class="slide.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                >
                  {{ slide.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ slide.order }}</td>
              <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                <Link :href="`/web-profile/promo-slides/${slide.id}/edit`" class="text-blue-600 hover:text-blue-900 mr-3">
                  <i class="fa-solid fa-edit" /> Edit
                </Link>
                <button type="button" class="text-red-600 hover:text-red-900" @click="destroySlide(slide.id)">
                  <i class="fa-solid fa-trash" /> Hapus
                </button>
              </td>
            </tr>
            <tr v-if="!props.slides.data || props.slides.data.length === 0">
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                Belum ada slide promo.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.slides.links && props.slides.links.length > 3" class="mt-4 flex justify-center">
        <div class="flex gap-2">
          <Link
            v-for="link in props.slides.links"
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
    </div>
  </AppLayout>
</template>
