<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  package: { type: Object, required: true },
  brands: { type: Array, default: () => [] },
});

const form = ref({
  web_profile_brand_id: props.package.web_profile_brand_id,
  title: props.package.title,
  price_label: props.package.price_label || '',
  body_html: props.package.body_html || '',
  sort_order: props.package.sort_order,
  is_active: props.package.is_active,
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  errors.value = {};
  router.put(`/web-profile/home-service-packages/${props.package.id}`, {
    web_profile_brand_id: form.value.web_profile_brand_id,
    title: form.value.title,
    price_label: form.value.price_label || null,
    body_html: form.value.body_html || null,
    sort_order: form.value.sort_order,
    is_active: form.value.is_active,
  }, {
    onError: (e) => {
      errors.value = e || {};
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Diperbarui', confirmButtonText: 'OK' });
    },
  });
}
</script>

<template>
  <AppLayout title="Edit Paket Home Service">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Paket Home Service</h1>

      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div>
          <InputLabel for="brand" value="Brand *" />
          <select
            id="brand"
            v-model="form.web_profile_brand_id"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            required
          >
            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.title }}</option>
          </select>
          <InputError class="mt-1" :message="errors.web_profile_brand_id" />
        </div>

        <div>
          <InputLabel for="title" value="Nama paket *" />
          <TextInput id="title" v-model="form.title" class="mt-1 block w-full" />
          <InputError class="mt-1" :message="errors.title" />
        </div>

        <div>
          <InputLabel for="price_label" value="Label harga" />
          <TextInput id="price_label" v-model="form.price_label" class="mt-1 block w-full" />
        </div>

        <div>
          <InputLabel for="sort_order" value="Urutan" />
          <TextInput id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-full" />
        </div>

        <div>
          <InputLabel for="body_html" value="Detail menu (teks biasa)" />
          <textarea
            id="body_html"
            v-model="form.body_html"
            rows="12"
            class="mt-1 block w-full rounded-md border-gray-300 text-sm"
            placeholder="Contoh:
SALAD BAR
Curly lettuce, romaine lettuce, lolorosa

DRESSING
Caesar dressing and thousand island"
          />
          <p class="mt-2 text-xs text-gray-500">
            Tidak perlu HTML. Cukup tulis per baris; baris kosong jadi pemisah antar bagian.
            Untuk bold, pakai format <code>**teks**</code> (contoh: <code>**SALAD BAR**</code>).
          </p>
        </div>

        <div class="flex items-center gap-2">
          <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
          <label for="is_active" class="text-sm text-gray-700">Aktif</label>
        </div>

        <div class="flex gap-3 pt-4">
          <PrimaryButton :disabled="isSubmitting" @click="submit">Simpan</PrimaryButton>
          <button
            type="button"
            class="px-4 py-2 border rounded"
            @click="router.visit('/web-profile/home-service-packages')"
          >
            Kembali
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
