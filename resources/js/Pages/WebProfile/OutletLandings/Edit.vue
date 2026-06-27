<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlet: { type: Object, required: true },
  landing: { type: Object, required: true },
  brandGalleryImages: { type: Array, default: () => [] },
  justusKunestWebUrl: { type: String, default: '' },
  previewKey: { type: String, default: '' },
});

const page = usePage();

const form = ref({
  slug: props.landing.slug || '',
  is_active: props.landing.is_active ?? false,
  headline: props.landing.headline || '',
  intro_paragraph: props.landing.intro_paragraph || '',
  secondary_paragraph: props.landing.secondary_paragraph || '',
  book_now_label: props.landing.book_now_label || 'BOOK NOW',
  see_map_label: props.landing.see_map_label || 'SEE MAP',
});

const heroFile = ref(null);
const logoFile = ref(null);
const removeHero = ref(false);
const removeLogo = ref(false);
const saving = ref(false);

const mediaSpecs = [
  {
    label: 'Logo',
    web: '512 × 512 px',
    mobile: '512 × 512 px',
    ratio: '1:1',
    note: 'PNG/WebP transparan. Tampil bulat di landing.',
  },
  {
    label: 'Hero Banner',
    web: '1920 × 900 px',
    mobile: '1080 × 720 px',
    ratio: '16:9 – 3:2',
    note: 'Landscape lebar. Area penting di tengah (object-cover).',
  },
];

watch(
  () => page.props.flash?.success,
  (message) => {
    if (message) {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: message, confirmButtonText: 'OK' });
    }
  },
  { immediate: true },
);

const previewSlug = computed(() => String(form.value.slug || props.landing.slug || '').trim());

function buildPreviewUrl(draft = false) {
  const base = String(props.justusKunestWebUrl || '').replace(/\/$/, '');
  const slug = previewSlug.value;
  if (!base || !slug) return null;
  let url = `${base}/outlets/${encodeURIComponent(slug)}`;
  if (draft) {
    if (!props.previewKey) return null;
    url += `?preview=${encodeURIComponent(props.previewKey)}`;
  }
  return url;
}

function openPreview(draft = false) {
  let url = draft ? props.landing.preview_draft_url : props.landing.preview_live_url;

  if (draft && previewSlug.value !== String(props.landing.slug || '').trim()) {
    url = buildPreviewUrl(true);
  }

  if (!url) {
    const missing = !String(props.justusKunestWebUrl || '').trim()
      ? 'Isi URL Justus Kunest Web di halaman Daftar Outlet Landing → Simpan Pengaturan.'
      : (draft && !props.previewKey)
        ? 'Isi Preview Key di pengaturan (Daftar Outlet Landing) untuk preview draft.'
        : draft
          ? 'Simpan landing page terlebih dahulu sebelum preview draft.'
          : 'Landing belum siap tampil (aktif + hero + headline/intro).';
    Swal.fire({ icon: 'info', title: 'Preview tidak tersedia', text: missing });
    return;
  }
  window.open(url, '_blank', 'noopener,noreferrer');
}

function submit() {
  saving.value = true;
  const fd = new FormData();
  fd.append('_method', 'PUT');
  Object.entries(form.value).forEach(([key, val]) => {
    if (key === 'is_active') {
      fd.append(key, form.value.is_active ? '1' : '0');
    } else {
      fd.append(key, val ?? '');
    }
  });
  if (heroFile.value) fd.append('hero_image', heroFile.value);
  if (logoFile.value) fd.append('logo_override', logoFile.value);
  if (removeHero.value) fd.append('remove_hero', '1');
  if (removeLogo.value) fd.append('remove_logo', '1');

  router.post(`/web-profile/outlet-landings/${props.outlet.id}`, fd, {
    forceFormData: true,
    onFinish: () => { saving.value = false; },
    onError: (errors) => {
      const msg = Object.values(errors || {}).flat().join('\n') || 'Gagal menyimpan.';
      Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
    },
  });
}
</script>

<template>
  <AppLayout :title="`Landing: ${outlet.name}`">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <Link href="/web-profile/outlet-landings" class="text-sm text-gray-500 hover:text-gray-700">&larr; Daftar Outlet</Link>
          <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ outlet.name }}</h1>
          <p class="text-sm text-gray-500 mt-1">Landing page Justus Kunest web</p>
          <div class="mt-2 flex flex-wrap gap-2 text-xs">
            <span
              class="px-2 py-1 rounded font-semibold"
              :class="landing.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ landing.is_published ? 'Siap tampil di web' : 'Belum siap (butuh aktif + hero + headline/intro)' }}
            </span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-900 text-sm"
            @click="openPreview(true)"
          >
            <i class="fa-solid fa-eye"></i> Preview Draft
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm disabled:opacity-50"
            :disabled="!landing.is_published"
            @click="openPreview(false)"
          >
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Preview Live
          </button>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <h2 class="font-semibold text-gray-800">Pengaturan Umum</h2>
          <div class="flex items-center gap-3">
            <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
            <label for="is_active" class="text-sm text-gray-700">Aktifkan landing page</label>
          </div>
          <div>
            <InputLabel value="Slug URL" />
            <TextInput v-model="form.slug" class="mt-1 w-full" placeholder="justus-steakhouse-alam-sutera" />
            <p class="text-xs text-gray-500 mt-1">URL web: /outlets/{{ form.slug || 'slug' }}</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <h2 class="font-semibold text-gray-800">Konten</h2>
          <div>
            <InputLabel value="Headline" />
            <TextInput v-model="form.headline" class="mt-1 w-full" />
          </div>
          <div>
            <InputLabel value="Paragraf Intro (pisahkan paragraf dengan baris kosong)" />
            <textarea v-model="form.intro_paragraph" rows="5" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <InputLabel value="Paragraf Setelah Hero" />
            <textarea v-model="form.secondary_paragraph" rows="4" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <h2 class="font-semibold text-gray-800">Media</h2>

          <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4 text-sm">
            <p class="font-semibold text-indigo-900 mb-2">Rekomendasi dimensi (web & mobile)</p>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs text-left">
                <thead>
                  <tr class="text-indigo-800 border-b border-indigo-200">
                    <th class="py-2 pr-3 font-semibold">Aset</th>
                    <th class="py-2 pr-3 font-semibold">Web</th>
                    <th class="py-2 pr-3 font-semibold">Mobile</th>
                    <th class="py-2 pr-3 font-semibold">Rasio</th>
                    <th class="py-2 font-semibold">Catatan</th>
                  </tr>
                </thead>
                <tbody class="text-indigo-950">
                  <tr v-for="spec in mediaSpecs" :key="spec.label" class="border-b border-indigo-100 last:border-0">
                    <td class="py-2 pr-3 font-medium whitespace-nowrap">{{ spec.label }}</td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ spec.web }}</td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ spec.mobile }}</td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ spec.ratio }}</td>
                    <td class="py-2 text-indigo-800">{{ spec.note }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-indigo-700 mt-2">Format: JPG, PNG, WebP. Maks. hero 10 MB.</p>
          </div>

          <div>
            <InputLabel value="Logo (opsional, override logo outlet)" />
            <p class="text-xs text-gray-500 mt-0.5">Rekomendasi: 512 × 512 px (1:1)</p>
            <img v-if="landing.logo_override_url && !removeLogo && !logoFile" :src="landing.logo_override_url" alt="" class="mt-2 h-20 w-20 rounded-full object-cover" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" @change="logoFile = $event.target.files?.[0] || null" />
            <label v-if="landing.logo_override_url" class="mt-2 flex items-center gap-2 text-sm text-gray-600">
              <input v-model="removeLogo" type="checkbox" class="rounded border-gray-300" /> Hapus logo override
            </label>
          </div>
          <div>
            <InputLabel value="Hero Banner *" />
            <p class="text-xs text-gray-500 mt-0.5">Rekomendasi: 1920 × 900 px (web) / 1080 × 720 px (mobile), landscape</p>
            <img v-if="landing.hero_image_url && !removeHero && !heroFile" :src="landing.hero_image_url" alt="" class="mt-2 max-h-48 w-full rounded-lg object-cover" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" @change="heroFile = $event.target.files?.[0] || null" />
            <label v-if="landing.hero_image_url" class="mt-2 flex items-center gap-2 text-sm text-gray-600">
              <input v-model="removeHero" type="checkbox" class="rounded border-gray-300" /> Hapus hero
            </label>
          </div>
          <div>
            <InputLabel value="Galeri Foto" />
            <p class="text-xs text-gray-500 mt-0.5">
              Diambil otomatis dari galeri Brand outlet (Member Apps Settings). Urutan mengikuti sort order brand.
            </p>
            <div v-if="brandGalleryImages.length" class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-3">
              <img
                v-for="(url, idx) in brandGalleryImages"
                :key="`${idx}-${url}`"
                :src="url"
                alt=""
                class="h-28 w-full rounded-lg object-cover"
              />
            </div>
            <p v-else class="mt-2 text-sm text-amber-700">
              Belum ada foto galeri di brand outlet ini.
            </p>
            <Link
              href="/admin/member-apps-settings"
              class="mt-3 inline-flex text-sm text-indigo-600 hover:underline"
            >
              Kelola galeri di Member Apps Settings → Brand
            </Link>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <h2 class="font-semibold text-gray-800">Lokasi & CTA</h2>
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dari Master Outlet (tbl_data_outlet)</p>
            <div>
              <div class="text-gray-500 text-xs mb-1">Alamat</div>
              <div class="text-gray-800 whitespace-pre-line">{{ outlet.address || '—' }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs mb-1">Koordinat</div>
              <div class="text-gray-800">
                <span v-if="outlet.lat && outlet.long">{{ outlet.lat }}, {{ outlet.long }}</span>
                <span v-else class="text-amber-700">Lat/long belum diisi di master outlet</span>
              </div>
            </div>
            <div v-if="outlet.map_url">
              <div class="text-gray-500 text-xs mb-1">Google Map (lat/long)</div>
              <a :href="outlet.map_url" target="_blank" rel="noreferrer" class="text-indigo-600 hover:underline break-all">{{ outlet.map_url }}</a>
            </div>
            <p class="text-xs text-gray-500">Ubah alamat/koordinat di menu Master Outlet jika perlu diperbarui.</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Label Book Now" />
              <TextInput v-model="form.book_now_label" class="mt-1 w-full" />
            </div>
            <div>
              <InputLabel value="Label See Map" />
              <TextInput v-model="form.see_map_label" class="mt-1 w-full" />
            </div>
          </div>
          <p class="text-xs text-gray-500">Book Now otomatis mengarah ke halaman reservasi dengan outlet terpilih.</p>
        </div>

        <div class="flex justify-end gap-3">
          <Link href="/web-profile/outlet-landings" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="saving" class="px-6 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
