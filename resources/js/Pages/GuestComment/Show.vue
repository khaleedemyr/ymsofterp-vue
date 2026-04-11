<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
  form: Object,
  imageUrl: String,
});

const lightboxOpen = ref(false);

function openLightbox() {
  lightboxOpen.value = true;
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lightboxOpen.value = false;
  document.body.style.overflow = '';
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    closeLightbox();
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
      <div class="text-sm text-gray-700 mb-6 space-y-1 border-l-4 border-blue-200 pl-4 py-2 bg-blue-50/50 rounded-r-lg">
        <div>
          <span class="text-gray-500">Pencatat</span>
          · <span class="font-medium">{{ form.creator?.nama_lengkap || '—' }}</span>
        </div>
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
          <p class="text-xs text-gray-500 mt-2 text-center">Klik gambar untuk tampilan besar</p>
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
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/88 p-4 md:p-8"
        role="dialog"
        aria-modal="true"
        aria-label="Pratinjau formulir"
        @click.self="closeLightbox"
      >
        <button
          type="button"
          class="absolute top-3 right-3 md:top-5 md:right-5 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors text-2xl leading-none font-light"
          aria-label="Tutup"
          @click="closeLightbox"
        >
          ×
        </button>
        <img
          :src="imageUrl"
          alt="Formulir"
          class="max-h-full max-w-full object-contain shadow-2xl rounded-lg select-none"
          @click.stop
        />
      </div>
    </Teleport>
  </AppLayout>
</template>
