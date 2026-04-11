<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import VueEasyLightbox from 'vue-easy-lightbox';
import GuestCommentUserAvatar from '@/Components/GuestCommentUserAvatar.vue';

const props = defineProps({
  form: Object,
  imageUrl: String,
  outlets: { type: Array, default: () => [] },
  canChooseOutlet: { type: Boolean, default: true },
  lockedOutlet: { type: Object, default: null },
  ratingOptions: Array,
  readOnly: Boolean,
});

function initialOutletId() {
  if (props.canChooseOutlet) {
    return props.form.id_outlet != null && props.form.id_outlet !== '' ? props.form.id_outlet : '';
  }
  return props.lockedOutlet?.id_outlet ?? '';
}

/** Sama dengan kolom di formulir kertas (English). */
function ratingOptionLabel(opt) {
  if (!opt) return '';
  return opt.charAt(0).toUpperCase() + opt.slice(1);
}

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
  marketing_source: props.form.marketing_source || '',
  id_outlet: initialOutletId(),
  mark_verified: false,
});

const avatarLightboxVisible = ref(false);
const avatarLightboxImages = ref([]);
const avatarLightboxIndex = ref(0);

function openAvatarLightbox({ src }) {
  if (!src) return;
  avatarLightboxImages.value = [src];
  avatarLightboxIndex.value = 0;
  avatarLightboxVisible.value = true;
}

function save() {
  f.transform((data) => {
    const next = {
      ...data,
      id_outlet: data.id_outlet === '' || data.id_outlet === null ? null : data.id_outlet,
    };
    if (!props.canChooseOutlet) {
      next.id_outlet = props.lockedOutlet?.id_outlet ?? null;
    }
    return next;
  }).put(route('guest-comment-forms.update', props.form.id), {
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

      <h1 class="text-2xl font-bold text-gray-800 mb-2">
        {{ readOnly ? 'Detail (terverifikasi)' : 'Verifikasi data guest comment' }}
      </h1>
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
            v-if="readOnly && form.verifier"
            :user="form.verifier"
            size-class="w-11 h-11"
            @preview="openAvatarLightbox"
          />
          <div>
            <span class="text-gray-500">Diverifikasi</span>
            ·
            <template v-if="readOnly">
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
                  <option v-for="opt in ratingOptions" :key="opt" :value="opt">{{ ratingOptionLabel(opt) }}</option>
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
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Staff yang dipuji</label>
              <input v-model="f.praised_staff_name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Outlet (tertulis di form / staff)</label>
              <input v-model="f.praised_staff_outlet" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" placeholder="Contoh: JUSTUS THE PARK" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dari mana mengetahui outlet (marketing)</label>
              <input v-model="f.marketing_source" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly" placeholder="Contoh: Sosial Media — form Tempayan" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Outlet (data master)</label>
              <template v-if="canChooseOutlet">
                <select v-model="f.id_outlet" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :disabled="readOnly">
                  <option value="">— Pilih outlet —</option>
                  <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                </select>
              </template>
              <template v-else>
                <div
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 min-h-[2.5rem] flex items-center"
                >
                  {{ readOnly ? (form.outlet?.nama_outlet || '—') : (lockedOutlet?.nama_outlet || '—') }}
                </div>
                <p v-if="!readOnly && !lockedOutlet" class="text-xs text-amber-700 mt-1">
                  Akun Anda belum memiliki outlet (id_outlet). Hubungi admin.
                </p>
              </template>
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

    <VueEasyLightbox
      :visible="avatarLightboxVisible"
      :imgs="avatarLightboxImages"
      :index="avatarLightboxIndex"
      @hide="avatarLightboxVisible = false"
    />
  </AppLayout>
</template>
