<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  form: Object,
  imageUrl: String,
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
      <p class="text-sm text-gray-600 mb-6">
        Status:
        <span class="font-semibold" :class="form.status === 'verified' ? 'text-green-700' : 'text-amber-700'">
          {{ form.status === 'verified' ? 'Terverifikasi' : 'Menunggu verifikasi' }}
        </span>
        <span v-if="form.verified_at" class="ml-2">
          · {{ new Date(form.verified_at).toLocaleString('id-ID') }}
          <span v-if="form.verifier"> · {{ form.verifier.nama_lengkap }}</span>
        </span>
      </p>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
          <a :href="imageUrl" target="_blank" rel="noopener" class="block">
            <img :src="imageUrl" alt="Formulir" class="w-full rounded-xl border border-gray-200 max-h-[70vh] object-contain bg-gray-50" />
          </a>
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
          <div v-if="form.creator" class="text-xs text-gray-400 pt-2">Dicatat oleh {{ form.creator.nama_lengkap }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
