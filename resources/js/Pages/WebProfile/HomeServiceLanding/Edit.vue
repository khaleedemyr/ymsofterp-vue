<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  landing: { type: Object, required: true },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);

const heroTitle = ref(props.landing.hero_title || '');
const heroSubtitle = ref(props.landing.hero_subtitle || '');
const blocks = ref(
  (props.landing.content_blocks || []).length
    ? JSON.parse(JSON.stringify(props.landing.content_blocks))
    : [
        {
          title: '',
          body: '',
          video_url: '',
          text_on_left: true,
        },
      ],
);

const collageKeepPaths = ref([]);
const collageNewFiles = ref([]);
const galleryCardFile = ref(null);
const menuCardFile = ref(null);
const removeGalleryCard = ref(false);
const removeMenuCard = ref(false);

const galleryLabel = ref(props.landing.gallery_card_label || 'GALLERY');
const galleryUrl = ref(props.landing.gallery_card_url || '');
const menuLabel = ref(props.landing.menu_card_label || 'MENU');
const menuUrl = ref(props.landing.menu_card_url || '');
const ctaLabel = ref(props.landing.cta_label || '');
const ctaUrl = ref(props.landing.cta_url || '');

const collagePreview = computed(() => props.landing.collage_images || []);

function addBlock() {
  blocks.value.push({
    title: '',
    body: '',
    video_url: '',
    text_on_left: blocks.value.length % 2 === 0,
  });
}

function removeBlock(idx) {
  blocks.value.splice(idx, 1);
}

function onCollageNewChange(e) {
  collageNewFiles.value = Array.from(e.target.files || []);
}

function removeCollagePath(path) {
  collageKeepPaths.value = collageKeepPaths.value.filter((p) => p !== path);
}

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  fd.append('hero_title', heroTitle.value || '');
  fd.append('hero_subtitle', heroSubtitle.value || '');
  fd.append('content_blocks_json', JSON.stringify(blocks.value));
  fd.append('collage_keep_json', JSON.stringify(collageKeepPaths.value));
  fd.append('gallery_card_label', galleryLabel.value || '');
  fd.append('gallery_card_url', galleryUrl.value || '');
  fd.append('menu_card_label', menuLabel.value || '');
  fd.append('menu_card_url', menuUrl.value || '');
  fd.append('cta_label', ctaLabel.value || '');
  fd.append('cta_url', ctaUrl.value || '');
  fd.append('remove_gallery_card', removeGalleryCard.value ? '1' : '0');
  fd.append('remove_menu_card', removeMenuCard.value ? '1' : '0');

  collageNewFiles.value.forEach((file) => {
    fd.append('collage_new[]', file);
  });

  if (galleryCardFile.value) {
    fd.append('gallery_card_image', galleryCardFile.value);
  }
  if (menuCardFile.value) {
    fd.append('menu_card_image', menuCardFile.value);
  }

  router.post('/web-profile/home-service-landing/update', fd, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      isSubmitting.value = false;
      galleryCardFile.value = null;
      menuCardFile.value = null;
      collageNewFiles.value = [];
      removeGalleryCard.value = false;
      removeMenuCard.value = false;
    },
    onError: (e) => {
      errors.value = e;
    },
  });
}

watch(
  () => props.landing.collage_images,
  (imgs) => {
    collageKeepPaths.value = (imgs || []).map((c) => c.path);
  },
  { immediate: true },
);

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
});
</script>

<template>
  <AppLayout title="Web Profile - Home Service Landing">
    <div class="mx-auto max-w-5xl px-4 py-8">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Home Service — Landing (Website)</h1>
          <p class="mt-1 text-sm text-gray-600">
            Hero teks, blok bergantian, collage, kartu Gallery/Menu, dan CTA. Gambar hero utama tetap di
            <Link href="/web-profile/home-service-packages" class="text-blue-600 underline">Home Service Menu</Link>.
          </p>
        </div>
        <Link
          href="/web-profile/home-service-packages"
          class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Paket per brand
        </Link>
      </div>

      <form class="space-y-10" @submit.prevent="submit">
        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-4 text-lg font-semibold text-gray-800">Hero (teks)</h2>
          <div class="space-y-4">
            <div>
              <InputLabel value="Judul" />
              <TextInput v-model="heroTitle" type="text" class="mt-1 block w-full" placeholder="HOME SERVICE" />
              <InputError class="mt-1" :message="errors.hero_title" />
            </div>
            <div>
              <InputLabel value="Subtitle / deskripsi (beberapa baris)" />
              <textarea
                v-model="heroSubtitle"
                rows="5"
                class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Paragraf pertama&#10;&#10;Paragraf kedua"
              />
              <InputError class="mt-1" :message="errors.hero_subtitle" />
            </div>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Blok teks &amp; video (bergantian)</h2>
            <button type="button" class="text-sm text-blue-600 hover:underline" @click="addBlock">+ Tambah blok</button>
          </div>
          <div v-for="(block, idx) in blocks" :key="idx" class="mb-6 rounded border border-gray-200 p-4">
            <div class="mb-2 flex justify-between">
              <span class="text-sm font-medium text-gray-600">Blok {{ idx + 1 }}</span>
              <button type="button" class="text-sm text-red-600 hover:underline" @click="removeBlock(idx)">Hapus</button>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2">
                <InputLabel :value="`Judul blok ${idx + 1}`" />
                <TextInput v-model="block.title" type="text" class="mt-1 block w-full" />
              </div>
              <div class="md:col-span-2">
                <InputLabel value="Isi" />
                <textarea
                  v-model="block.body"
                  rows="4"
                  class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm"
                />
              </div>
              <div class="md:col-span-2">
                <InputLabel value="Video URL (YouTube / MP4 embed)" />
                <TextInput v-model="block.video_url" type="text" class="mt-1 block w-full" placeholder="https://..." />
              </div>
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="block.text_on_left" type="checkbox" class="rounded border-gray-300" />
                Teks di kiri (video di kanan). Matikan untuk membalik.
              </label>
            </div>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-2 text-lg font-semibold text-gray-800">Collage gambar</h2>
          <p class="mb-4 text-sm text-gray-600">Centang gambar yang tetap dipakai; unggah tambahan di bawah. Urutan: lama (yang dipertahankan) lalu file baru.</p>
          <div class="mb-4 flex flex-wrap gap-3">
            <div v-for="c in collagePreview" v-show="collageKeepPaths.includes(c.path)" :key="c.path" class="relative">
              <img :src="c.url" alt="" class="h-24 w-32 rounded border object-cover" />
              <button
                type="button"
                class="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-0.5 text-xs text-white"
                @click="removeCollagePath(c.path)"
              >
                ×
              </button>
            </div>
          </div>
          <div>
            <InputLabel value="Tambah gambar (boleh banyak)" />
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block text-sm" @change="onCollageNewChange" />
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-4 text-lg font-semibold text-gray-800">Kartu Gallery &amp; Menu</h2>
          <div class="grid gap-8 md:grid-cols-2">
            <div>
              <h3 class="font-medium text-gray-700">Gallery</h3>
              <div v-if="landing.gallery_card_image_url" class="mt-2">
                <img :src="landing.gallery_card_image_url" alt="" class="max-h-40 rounded border" />
              </div>
              <label v-if="landing.gallery_card_image_path" class="mt-2 flex items-center gap-2 text-sm">
                <input v-model="removeGalleryCard" type="checkbox" class="rounded border-gray-300" />
                Hapus gambar
              </label>
              <div class="mt-2">
                <InputLabel value="Gambar baru" />
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="mt-1 block text-sm"
                  @change="(e) => (galleryCardFile = e.target.files?.[0] || null)"
                />
              </div>
              <div class="mt-3">
                <InputLabel value="Label" />
                <TextInput v-model="galleryLabel" type="text" class="mt-1 block w-full" />
              </div>
              <div class="mt-3">
                <InputLabel value="Link URL" />
                <TextInput v-model="galleryUrl" type="text" class="mt-1 block w-full" placeholder="https://..." />
              </div>
            </div>
            <div>
              <h3 class="font-medium text-gray-700">Menu (biasanya ke halaman paket)</h3>
              <div v-if="landing.menu_card_image_url" class="mt-2">
                <img :src="landing.menu_card_image_url" alt="" class="max-h-40 rounded border" />
              </div>
              <label v-if="landing.menu_card_image_path" class="mt-2 flex items-center gap-2 text-sm">
                <input v-model="removeMenuCard" type="checkbox" class="rounded border-gray-300" />
                Hapus gambar
              </label>
              <div class="mt-2">
                <InputLabel value="Gambar baru" />
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="mt-1 block text-sm"
                  @change="(e) => (menuCardFile = e.target.files?.[0] || null)"
                />
              </div>
              <div class="mt-3">
                <InputLabel value="Label" />
                <TextInput v-model="menuLabel" type="text" class="mt-1 block w-full" />
              </div>
              <div class="mt-3">
                <InputLabel value="Link URL (kosongkan untuk default /home-service/menu di website)" />
                <TextInput v-model="menuUrl" type="text" class="mt-1 block w-full" placeholder="/home-service/menu" />
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-4 text-lg font-semibold text-gray-800">Tombol CTA</h2>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <InputLabel value="Teks tombol" />
              <TextInput v-model="ctaLabel" type="text" class="mt-1 block w-full" placeholder="BOOKING & RESERVATION" />
            </div>
            <div>
              <InputLabel value="Link URL" />
              <TextInput v-model="ctaUrl" type="text" class="mt-1 block w-full" placeholder="/reservation" />
            </div>
          </div>
        </section>

        <div class="flex gap-3">
          <PrimaryButton type="submit" :disabled="isSubmitting">Simpan</PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
