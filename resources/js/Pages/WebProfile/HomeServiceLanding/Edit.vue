<script setup>
import { ref, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

/** Sama dengan Home Block: max:102400 KB → 100 MB */
const MAX_VIDEO_KB = 102400;
const MAX_VIDEO_BYTES = MAX_VIDEO_KB * 1024;
const ALLOWED_VIDEO_EXT = ['mp4', 'webm'];

const props = defineProps({
  landing: { type: Object, required: true },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);

function emptyBlock(flipText) {
  return {
    title: '',
    body: '',
    video_url: '',
    caption: '',
    text_on_left: flipText !== false,
    video_path: null,
    video_storage_url: null,
    remove_uploaded_video: false,
    _videoFile: null,
  };
}

function fromLandingBlocks(raw) {
  const list = Array.isArray(raw) ? raw : [];
  if (list.length === 0) {
    return [emptyBlock(true)];
  }
  return list.map((b, idx) => ({
    title: b.title || '',
    body: b.body || '',
    video_url: b.video_url || '',
    caption: b.caption || '',
    text_on_left: b.text_on_left !== false,
    video_path: b.video_path || null,
    video_storage_url: b.video_storage_url || null,
    remove_uploaded_video: false,
    _videoFile: null,
  }));
}

const heroTitle = ref(props.landing.hero_title || '');
const heroSubtitle = ref(props.landing.hero_subtitle || '');
const blocks = ref(fromLandingBlocks(props.landing.content_blocks));

const collageKeepPaths = ref([]);
const collageNewFiles = ref([]);
const galleryCardFile = ref(null);
const menuCardFile = ref(null);
const removeGalleryCard = ref(false);
const removeMenuCard = ref(false);

const galleryLabel = ref(props.landing.gallery_card_label || 'BOOKING & RESERVATION');
const galleryUrl = ref(props.landing.gallery_card_url || '/reservation');
const menuLabel = ref(props.landing.menu_card_label || 'MENU');
const menuUrl = ref(props.landing.menu_card_url || '');
const ctaLabel = ref(props.landing.cta_label || '');
const ctaUrl = ref(props.landing.cta_url || '');

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function validateVideoFile(file) {
  const ext = file.name.split('.').pop()?.toLowerCase() || '';
  if (!ALLOWED_VIDEO_EXT.includes(ext)) {
    return {
      ok: false,
      message: 'Format video harus <strong>MP4</strong> atau <strong>WEBM</strong>.',
    };
  }
  if (file.size > MAX_VIDEO_BYTES) {
    return {
      ok: false,
      message: `Ukuran maksimal <strong>100 MB</strong> (sesuai batas server).<br>File Anda: <strong>${formatFileSize(file.size)}</strong>`,
    };
  }
  return { ok: true };
}

function onBlockVideoChange(block, event) {
  const input = event.target;
  const file = input.files?.[0] || null;
  block.remove_uploaded_video = false;

  if (!file) {
    block._videoFile = null;
    return;
  }

  const result = validateVideoFile(file);
  if (!result.ok) {
    Swal.fire({
      icon: 'error',
      title: 'File tidak valid',
      html: result.message,
      confirmButtonText: 'OK',
    });
    input.value = '';
    block._videoFile = null;
    return;
  }

  block._videoFile = file;
}

function addBlock() {
  blocks.value.push(emptyBlock(blocks.value.length % 2 === 0));
}

function removeBlock(idx) {
  blocks.value.splice(idx, 1);
  if (blocks.value.length === 0) {
    blocks.value.push(emptyBlock(true));
  }
}

function onCollageNewChange(e) {
  collageNewFiles.value = Array.from(e.target.files || []);
}

function removeCollagePath(path) {
  collageKeepPaths.value = collageKeepPaths.value.filter((p) => p !== path);
}

function submit() {
  for (let i = 0; i < blocks.value.length; i += 1) {
    const b = blocks.value[i];
    if (b._videoFile) {
      const check = validateVideoFile(b._videoFile);
      if (!check.ok) {
        Swal.fire({ icon: 'error', title: 'File tidak valid', html: check.message });
        return;
      }
    }
  }

  isSubmitting.value = true;
  errors.value = {};

  const payload = blocks.value.map((b) => ({
    title: b.title || '',
    body: b.body || '',
    video_url: b.video_url || '',
    caption: b.caption || '',
    text_on_left: !!b.text_on_left,
    video_path: b.video_path || null,
  }));

  const fd = new FormData();
  fd.append('hero_title', heroTitle.value || '');
  fd.append('hero_subtitle', heroSubtitle.value || '');
  fd.append('content_blocks_json', JSON.stringify(payload));
  fd.append('collage_keep_json', JSON.stringify(collageKeepPaths.value));
  fd.append('gallery_card_label', galleryLabel.value || '');
  fd.append('gallery_card_url', galleryUrl.value || '');
  fd.append('menu_card_label', menuLabel.value || '');
  fd.append('menu_card_url', menuUrl.value || '');
  fd.append('cta_label', ctaLabel.value || '');
  fd.append('cta_url', ctaUrl.value || '');
  fd.append('remove_gallery_card', removeGalleryCard.value ? '1' : '0');
  fd.append('remove_menu_card', removeMenuCard.value ? '1' : '0');

  blocks.value.forEach((b, idx) => {
    fd.append(`remove_block_video_${idx}`, b.remove_uploaded_video ? '1' : '0');
    if (b._videoFile) {
      fd.append(`block_video_${idx}`, b._videoFile);
    }
  });

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
      blocks.value.forEach((b) => {
        b._videoFile = null;
        b.remove_uploaded_video = false;
      });
    },
    onError: (e) => {
      errors.value = e;
      const msgs = Object.values(e || {}).flat().filter(Boolean);
      if (msgs.length) {
        Swal.fire({
          icon: 'error',
          title: 'Gagal menyimpan',
          html: msgs.join('<br>'),
        });
      }
    },
  });
}

watch(
  () => props.landing.content_blocks,
  (raw) => {
    blocks.value = fromLandingBlocks(raw);
  },
);

watch(
  () => props.landing.collage_images,
  (imgs) => {
    collageKeepPaths.value = (imgs || []).map((c) => c.path);
  },
  { immediate: true },
);

watch(
  () => props.landing.hero_title,
  (v) => {
    heroTitle.value = v || '';
  },
);

watch(
  () => props.landing.hero_subtitle,
  (v) => {
    heroSubtitle.value = v || '';
  },
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
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Paragraf pertama&#10;&#10;Paragraf kedua"
              />
              <InputError class="mt-1" :message="errors.hero_subtitle" />
            </div>
          </div>
        </section>

        <section>
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Blok teks &amp; video (bergantian)</h2>
            <button type="button" class="text-sm font-medium text-blue-600 hover:underline" @click="addBlock">
              + Tambah blok
            </button>
          </div>
          <p class="mb-4 text-sm text-gray-600">
            Pola input mengikuti <strong>Home Block</strong>: judul opsional, isi teks, unggah video MP4/WEBM atau isi URL YouTube/tautan file, plus caption di atas video.
          </p>

          <div v-for="(block, idx) in blocks" :key="idx" class="relative mb-6">
            <div class="mx-auto max-w-3xl rounded-lg bg-white p-6 shadow space-y-4">
              <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-3">
                <h3 class="text-sm font-semibold text-gray-700">Blok {{ idx + 1 }}</h3>
                <button type="button" class="text-sm text-red-600 hover:underline" @click="removeBlock(idx)">Hapus</button>
              </div>

              <div v-if="block.video_storage_url" class="text-sm text-gray-600">
                <p class="mb-2">Video unggahan saat ini:</p>
                <video :src="block.video_storage_url" controls class="max-h-48 w-full rounded border border-gray-200" />
              </div>

              <div>
                <InputLabel value="Judul (opsional)" />
                <TextInput v-model="block.title" type="text" class="mt-1 w-full" />
              </div>

              <div>
                <InputLabel value="Isi teks" />
                <textarea v-model="block.body" rows="5" class="mt-1 w-full rounded-md border-gray-300" />
              </div>

              <div>
                <InputLabel value="Video (mp4/webm) — unggah atau ganti" />
                <input
                  type="file"
                  accept="video/mp4,video/webm,.mp4,.webm"
                  class="mt-1 block w-full text-sm"
                  @change="onBlockVideoChange(block, $event)"
                />
                <p class="mt-1 text-xs text-gray-500">
                  Format: MP4 / WEBM. Maksimal <strong>100 MB</strong> (lebih besar akan ditolak sebelum upload).
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  Justus Nest: kotak tampilan <strong>16:9</strong> (lebar penuh blok); video di-zoom memenuhi kotak sehingga pinggir bisa terpotong. Disarankan ekspor
                  <strong>1920×1080</strong> atau minimal <strong>1280×720</strong>, rasio 16:9; subjek penting di tengah frame.
                </p>
                <label v-if="block.video_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="block.remove_uploaded_video" type="checkbox" class="rounded border-gray-300" />
                  Hapus video unggahan (pakai URL saja jika diisi)
                </label>
              </div>

              <div>
                <InputLabel value="Video URL (YouTube / tautan MP4) — jika tidak pakai unggahan" />
                <TextInput v-model="block.video_url" type="text" class="mt-1 w-full" placeholder="https://..." />
              </div>

              <div>
                <InputLabel value="Caption (teks di atas video)" />
                <textarea
                  v-model="block.caption"
                  rows="3"
                  class="mt-1 w-full rounded-md border-gray-300"
                  placeholder="Contoh: VIDEO Grilling During Event"
                />
              </div>

              <div class="flex items-center gap-2">
                <input :id="`text-left-${idx}`" v-model="block.text_on_left" type="checkbox" class="rounded border-gray-300" />
                <label :for="`text-left-${idx}`" class="text-sm text-gray-700">
                  Teks di kiri (video di kanan). Matikan untuk membalik.
                </label>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="mb-2 text-lg font-semibold text-gray-800">Collage gambar</h2>
          <p class="mb-4 text-sm text-gray-600">Centang gambar yang tetap dipakai; unggah tambahan di bawah. Urutan: lama (yang dipertahankan) lalu file baru.</p>
          <div class="mb-4 flex flex-wrap gap-3">
            <div
              v-for="c in landing.collage_images || []"
              v-show="collageKeepPaths.includes(c.path)"
              :key="c.path"
              class="relative"
            >
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
          <h2 class="mb-4 text-lg font-semibold text-gray-800">Kartu Booking &amp; Menu</h2>
          <div class="grid gap-8 md:grid-cols-2">
            <div>
              <h3 class="font-medium text-gray-700">Booking &amp; Reservation</h3>
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
                <InputLabel value="Link URL (default: /reservation)" />
                <TextInput v-model="galleryUrl" type="text" class="mt-1 block w-full" placeholder="/reservation" />
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
