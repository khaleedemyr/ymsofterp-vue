<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  form: Object,
  imageUrl: String,
  outlets: Array,
  ratingOptions: Array,
  readOnly: Boolean,
});

const ratingLabels = {
  poor: 'Buruk',
  average: 'Cukup',
  good: 'Baik',
  excellent: 'Sangat baik',
};

const f = useForm({
  rating_service: props.form.rating_service || '',
  rating_food: props.form.rating_food || '',
  rating_beverage: props.form.rating_beverage || '',
  rating_cleanliness: props.form.rating_cleanliness || '',
  rating_staff: props.form.rating_staff || '',
  rating_value: props.form.rating_value || '',
  comment_text: props.form.comment_text || '',
  guest_name: props.form.guest_name || '',
  guest_address: props.form.guest_address || '',
  guest_phone: props.form.guest_phone || '',
  guest_dob: props.form.guest_dob ? String(props.form.guest_dob).slice(0, 10) : '',
  visit_date: props.form.visit_date || '',
  praised_staff_name: props.form.praised_staff_name || '',
  praised_staff_outlet: props.form.praised_staff_outlet || '',
  id_outlet: props.form.id_outlet != null ? props.form.id_outlet : '',
  mark_verified: false,
});

function save() {
  f.transform((data) => ({
    ...data,
    id_outlet: data.id_outlet === '' ? null : data.id_outlet,
  })).put(route('guest-comment-forms.update', props.form.id), {
    preserveScroll: true,
  });
}
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-4 max-w-6xl mx-auto">
      <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-800 rounded-xl">
        {{ $page.props.flash.success }}
      </div>

      <div class="mb-4">
        <Link :href="route('guest-comment-forms.index')" class="text-blue-600 hover:underline text-sm font-semibold">
          ← Daftar
        </Link>
      </div>

      <h1 class="text-2xl font-bold text-gray-800 mb-6">
        {{ readOnly ? 'Detail (terverifikasi)' : 'Verifikasi data guest comment' }}
      </h1>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
          <p class="text-sm font-semibold text-gray-600 mb-2">Foto formulir</p>
          <a :href="imageUrl" target="_blank" rel="noopener" class="block">
            <img :src="imageUrl" alt="Formulir" class="w-full rounded-xl border border-gray-200 max-h-[70vh] object-contain bg-gray-50" />
          </a>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template v-for="key in ['rating_service', 'rating_food', 'rating_beverage', 'rating_cleanliness', 'rating_staff', 'rating_value']" :key="key">
              <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ key.replace('rating_', '').replace('_', ' ') }}</label>
                <select v-model="f[key]" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly">
                  <option value="">—</option>
                  <option v-for="opt in ratingOptions" :key="opt" :value="opt">{{ ratingLabels[opt] || opt }}</option>
                </select>
              </div>
            </template>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Komentar</label>
            <textarea v-model="f.comment_text" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama tamu</label>
              <input v-model="f.guest_name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Telepon</label>
              <input v-model="f.guest_phone" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Alamat</label>
              <input v-model="f.guest_address" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal lahir</label>
              <input v-model="f.guest_dob" type="date" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal kunjungan</label>
              <input v-model="f.visit_date" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" placeholder="bebas teks / tanggal" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Staff yang dipuji</label>
              <input v-model="f.praised_staff_name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Outlet staff</label>
              <input v-model="f.praised_staff_outlet" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Outlet (data master)</label>
              <select v-model="f.id_outlet" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly">
                <option value="">— Pilih outlet —</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
          </div>

          <div v-if="!readOnly" class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <input id="mv" v-model="f.mark_verified" type="checkbox" class="rounded border-gray-300" />
            <label for="mv" class="text-sm font-semibold text-gray-700">Tandai terverifikasi setelah simpan</label>
          </div>

          <div v-if="!readOnly" class="pt-4 flex gap-3">
            <button
              type="button"
              class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-50"
              :disabled="f.processing"
              @click="save"
            >
              {{ f.processing ? 'Menyimpan…' : 'Simpan' }}
            </button>
          </div>

          <div v-if="readOnly" class="pt-4">
            <Link :href="route('guest-comment-forms.index')" class="text-blue-600 font-semibold hover:underline">Kembali ke daftar</Link>
          </div>

          <p v-if="Object.keys(f.errors).length" class="text-sm text-red-600">
            Periksa kembali isian form.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
