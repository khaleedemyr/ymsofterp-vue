<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import VueEasyLightbox from 'vue-easy-lightbox';
import GuestCommentUserAvatar from '@/Components/GuestCommentUserAvatar.vue';

const props = defineProps({
  form: Object,
  imageUrl: String,
});

const lightboxOpen = ref(false);
const zoom = ref(1);
const lbNatural = ref({ w: 0, h: 0 });

const avatarLightboxVisible = ref(false);
const avatarLightboxImages = ref([]);
const avatarLightboxIndex = ref(0);

function openAvatarLightbox({ src }) {
  if (!src) return;
  avatarLightboxImages.value = [src];
  avatarLightboxIndex.value = 0;
  avatarLightboxVisible.value = true;
}

const MIN_ZOOM = 0.25;
const MAX_ZOOM = 6;

const lbImgStyle = computed(() => {
  if (!lbNatural.value.w) {
    return { maxWidth: '95vw', maxHeight: '90vh', width: 'auto', height: 'auto' };
  }
  const w = Math.round(lbNatural.value.w * zoom.value);
  return {
    width: `${w}px`,
    height: 'auto',
    maxWidth: 'none',
    display: 'block',
  };
});

function resetLightboxZoom() {
  zoom.value = 1;
  lbNatural.value = { w: 0, h: 0 };
}

function openLightbox() {
  resetLightboxZoom();
  lightboxOpen.value = true;
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lightboxOpen.value = false;
  document.body.style.overflow = '';
  resetLightboxZoom();
}

function onLbImageLoad(e) {
  const el = e.target;
  lbNatural.value = { w: el.naturalWidth, h: el.naturalHeight };
}

function zoomIn() {
  zoom.value = Math.min(MAX_ZOOM, Math.round((zoom.value + 0.2) * 100) / 100);
}

function zoomOut() {
  zoom.value = Math.max(MIN_ZOOM, Math.round((zoom.value - 0.2) * 100) / 100);
}

function zoomReset() {
  zoom.value = 1;
}

function onLightboxWheel(e) {
  if (!e.ctrlKey && !e.metaKey) {
    return;
  }
  e.preventDefault();
  const delta = e.deltaY > 0 ? -0.12 : 0.12;
  zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round((zoom.value + delta) * 100) / 100));
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    closeLightbox();
    return;
  }
  if (!lightboxOpen.value) return;
  if (e.key === '+' || e.key === '=') {
    e.preventDefault();
    zoomIn();
  }
  if (e.key === '-' || e.key === '_') {
    e.preventDefault();
    zoomOut();
  }
  if (e.key === '0') {
    e.preventDefault();
    zoomReset();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});

/** Sama dengan formulir kertas: Poor, Average, Good, Excellent */
function r(v) {
  if (!v) return '—';
  const s = String(v).toLowerCase();
  if (['poor', 'average', 'good', 'excellent'].includes(s)) {
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  return v;
}
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-4 max-w-6xl mx-auto">
      <div class="mb-4 flex flex-wrap gap-4 items-center justify-between">
        <Link :href="route('guest-comment-forms.index')" class="text-blue-600 hover:underline text-sm font-semibold">
          ← Daftar
        </Link>
        <Link
          v-if="form.status !== 'verified'"
          :href="route('guest-comment-forms.verify', form.id)"
          class="text-sm font-bold text-amber-700 hover:underline"
        >
          Buka verifikasi
        </Link>
      </div>

      <h1 class="text-2xl font-bold text-gray-800 mb-2">Guest comment #{{ form.id }}</h1>
      <p class="text-sm text-gray-600 mb-2">
        Status:
        <span class="font-semibold" :class="form.status === 'verified' ? 'text-green-700' : 'text-amber-700'">
          {{ form.status === 'verified' ? 'Terverifikasi' : 'Menunggu verifikasi' }}
        </span>
      </p>
      <div class="text-sm text-gray-700 mb-6 space-y-3 border-l-4 border-blue-200 pl-4 py-3 bg-blue-50/50 rounded-r-lg">
        <div class="flex gap-3 items-start">
          <GuestCommentUserAvatar
            v-if="form.creator"
            :user="form.creator"
            size-class="w-11 h-11"
            @preview="openAvatarLightbox"
          />
          <div>
            <span class="text-gray-500">Pencatat</span>
            · <span class="font-medium">{{ form.creator?.nama_lengkap || '—' }}</span>
          </div>
        </div>
        <div class="flex gap-3 items-start">
          <GuestCommentUserAvatar
            v-if="form.status === 'verified' && form.verifier"
            :user="form.verifier"
            size-class="w-11 h-11"
            @preview="openAvatarLightbox"
          />
          <div>
            <span class="text-gray-500">Diverifikasi</span>
            ·
            <template v-if="form.status === 'verified'">
              <span class="font-medium">{{ form.verifier?.nama_lengkap || '—' }}</span>
              <span v-if="form.verified_at" class="text-gray-600">
                · {{ new Date(form.verified_at).toLocaleString('id-ID') }}
              </span>
            </template>
            <span v-else class="text-gray-400">Belum diverifikasi</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
          <button
            type="button"
            class="block w-full text-left rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
            @click="openLightbox"
          >
            <img
              :src="imageUrl"
              alt="Formulir — klik untuk perbesar"
              class="w-full rounded-xl border border-gray-200 max-h-[70vh] object-contain bg-gray-50 cursor-zoom-in hover:opacity-95 transition-opacity"
            />
          </button>
          <p class="text-xs text-gray-500 mt-2 text-center">Klik gambar untuk lightbox — Ctrl+scroll atau tombol ± untuk zoom</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 space-y-3 text-sm">
          <div><span class="text-gray-500">Service</span> · {{ r(form.rating_service) }}</div>
          <div><span class="text-gray-500">Makanan</span> · {{ r(form.rating_food) }}</div>
          <div><span class="text-gray-500">Minuman</span> · {{ r(form.rating_beverage) }}</div>
          <div><span class="text-gray-500">Kebersihan</span> · {{ r(form.rating_cleanliness) }}</div>
          <div><span class="text-gray-500">Staff</span> · {{ r(form.rating_staff) }}</div>
          <div><span class="text-gray-500">Nilai</span> · {{ r(form.rating_value) }}</div>
          <hr class="my-2" />
          <div><span class="text-gray-500">Komentar</span><br><span class="text-gray-900 whitespace-pre-wrap">{{ form.comment_text || '—' }}</span></div>
          <div><span class="text-gray-500">Nama</span> · {{ form.guest_name || '—' }}</div>
          <div><span class="text-gray-500">Alamat</span> · {{ form.guest_address || '—' }}</div>
          <div><span class="text-gray-500">Telepon</span> · {{ form.guest_phone || '—' }}</div>
          <div><span class="text-gray-500">Tgl lahir</span> · {{ form.guest_dob || '—' }}</div>
          <div><span class="text-gray-500">Kunjungan</span> · {{ form.visit_date || '—' }}</div>
          <div><span class="text-gray-500">Staff dipuji</span> · {{ form.praised_staff_name || '—' }}</div>
          <div><span class="text-gray-500">Outlet</span> · {{ form.outlet?.nama_outlet || '—' }}</div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        class="fixed inset-0 z-[200] flex flex-col bg-black/88"
        role="dialog"
        aria-modal="true"
        aria-label="Pratinjau formulir"
        @click.self="closeLightbox"
      >
        <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2 md:px-4 bg-black/40 text-white">
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg bg-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/25"
              aria-label="Perkecil"
              @click.stop="zoomOut"
            >
              −
            </button>
            <span class="min-w-[3.5rem] text-center text-sm tabular-nums">{{ Math.round(zoom * 100) }}%</span>
            <button
              type="button"
              class="rounded-lg bg-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/25"
              aria-label="Perbesar"
              @click.stop="zoomIn"
            >
              +
            </button>
            <button
              type="button"
              class="rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold hover:bg-white/25"
              @click.stop="zoomReset"
            >
              Reset
            </button>
          </div>
          <p class="hidden sm:block text-xs text-white/70 text-right">Ctrl+scroll · tombol ± · + − 0</p>
          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-xl leading-none hover:bg-white/20"
            aria-label="Tutup"
            @click.stop="closeLightbox"
          >
            ×
          </button>
        </div>

        <div
          class="min-h-0 flex-1 overflow-auto p-4 flex items-start justify-center"
          @wheel="onLightboxWheel"
        >
          <img
            :key="props.imageUrl"
            :src="imageUrl"
            alt="Formulir"
            class="shadow-2xl rounded-lg select-none"
            :style="lbImgStyle"
            draggable="false"
            @click.stop
            @load="onLbImageLoad"
          />
        </div>
      </div>
    </Teleport>

    <VueEasyLightbox
      :visible="avatarLightboxVisible"
      :imgs="avatarLightboxImages"
      :index="avatarLightboxIndex"
      @hide="avatarLightboxVisible = false"
    />
  </AppLayout>
</template>
