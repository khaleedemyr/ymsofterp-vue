<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  careers: { type: Object, required: true },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);

const form = ref({
  careers_title: props.careers.careers_title || 'CAREERS',
  careers_subtitle: props.careers.careers_subtitle || 'Growth Together with Justus Group',
  careers_wording: props.careers.careers_wording || '',
  careers_cta_title: props.careers.careers_cta_title || '',
  careers_primary_button_label: props.careers.careers_primary_button_label || '',
  careers_primary_button_url: props.careers.careers_primary_button_url || '',
  careers_secondary_button_label: props.careers.careers_secondary_button_label || '',
  careers_secondary_button_url: props.careers.careers_secondary_button_url || '',
  careers_card_1_title: props.careers.cards?.[0]?.title || '',
  careers_card_2_title: props.careers.cards?.[1]?.title || '',
  careers_card_3_title: props.careers.cards?.[2]?.title || '',
  careers_card_4_title: props.careers.cards?.[3]?.title || '',
  hero_image: null,
  card_1_image: null,
  card_2_image: null,
  card_3_image: null,
  card_4_image: null,
  remove_hero: false,
  remove_card_1: false,
  remove_card_2: false,
  remove_card_3: false,
  remove_card_4: false,
});

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonText: 'OK' });
  }
  if (flash.error) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonText: 'OK' });
  }
});

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  const textKeys = [
    'careers_title',
    'careers_subtitle',
    'careers_wording',
    'careers_cta_title',
    'careers_primary_button_label',
    'careers_primary_button_url',
    'careers_secondary_button_label',
    'careers_secondary_button_url',
    'careers_card_1_title',
    'careers_card_2_title',
    'careers_card_3_title',
    'careers_card_4_title',
  ];
  textKeys.forEach((k) => fd.append(k, form.value[k] || ''));

  fd.append('remove_hero', form.value.remove_hero ? '1' : '0');
  fd.append('remove_card_1', form.value.remove_card_1 ? '1' : '0');
  fd.append('remove_card_2', form.value.remove_card_2 ? '1' : '0');
  fd.append('remove_card_3', form.value.remove_card_3 ? '1' : '0');
  fd.append('remove_card_4', form.value.remove_card_4 ? '1' : '0');

  if (form.value.hero_image) fd.append('hero_image', form.value.hero_image);
  if (form.value.card_1_image) fd.append('card_1_image', form.value.card_1_image);
  if (form.value.card_2_image) fd.append('card_2_image', form.value.card_2_image);
  if (form.value.card_3_image) fd.append('card_3_image', form.value.card_3_image);
  if (form.value.card_4_image) fd.append('card_4_image', form.value.card_4_image);

  router.post('/web-profile/careers-page/settings', fd, {
    forceFormData: true,
    onError: (e) => {
      errors.value = e || {};
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Web Profile - Careers Page">
    <div class="max-w-5xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan Careers Page</h1>

      <form class="bg-white rounded-lg shadow p-6 space-y-6" @submit.prevent="submit">
        <section class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Head Banner</h2>
          <div>
            <InputLabel value="Judul Hero" />
            <TextInput v-model="form.careers_title" class="mt-1 w-full" />
            <InputError class="mt-1" :message="errors.careers_title" />
          </div>
          <div>
            <InputLabel value="Subtitle Hero" />
            <TextInput v-model="form.careers_subtitle" class="mt-1 w-full" />
            <InputError class="mt-1" :message="errors.careers_subtitle" />
          </div>
          <div v-if="careers.careers_hero_image_url">
            <img :src="careers.careers_hero_image_url" class="max-h-48 w-full rounded border object-cover" alt="" />
          </div>
          <div>
            <InputLabel value="Gambar Hero (rekomendasi 1920x1080)" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => { form.hero_image = e.target.files?.[0] || null; form.remove_hero = false; }" />
            <label v-if="careers.careers_hero_image_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.remove_hero" type="checkbox" />
              Hapus gambar hero
            </label>
            <InputError class="mt-1" :message="errors.hero_image" />
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">Wording</h2>
          <div>
            <InputLabel value="Isi wording (di bawah hero)" />
            <textarea v-model="form.careers_wording" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
            <InputError class="mt-1" :message="errors.careers_wording" />
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">Cards</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="idx in [1, 2, 3, 4]" :key="idx" class="rounded border p-4 space-y-3">
              <h3 class="font-semibold text-gray-700">Card {{ idx }}</h3>
              <div>
                <InputLabel :value="`Judul Card ${idx}`" />
                <TextInput v-model="form[`careers_card_${idx}_title`]" class="mt-1 w-full" />
                <InputError class="mt-1" :message="errors[`careers_card_${idx}_title`]" />
              </div>
              <div v-if="careers.cards?.[idx - 1]?.image_url">
                <img :src="careers.cards[idx - 1].image_url" class="h-32 w-full rounded border object-cover" alt="" />
              </div>
              <div>
                <InputLabel :value="`Gambar Card ${idx}`" />
                <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => { form[`card_${idx}_image`] = e.target.files?.[0] || null; form[`remove_card_${idx}`] = false; }" />
                <label v-if="careers.cards?.[idx - 1]?.image_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="form[`remove_card_${idx}`]" type="checkbox" />
                  Hapus gambar card
                </label>
                <InputError class="mt-1" :message="errors[`card_${idx}_image`]" />
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">CTA + Tombol Loker</h2>
          <div>
            <InputLabel value="Judul CTA" />
            <TextInput v-model="form.careers_cta_title" class="mt-1 w-full" />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Label Tombol 1" />
              <TextInput v-model="form.careers_primary_button_label" class="mt-1 w-full" />
            </div>
            <div>
              <InputLabel value="URL Tombol 1" />
              <TextInput v-model="form.careers_primary_button_url" class="mt-1 w-full" />
              <InputError class="mt-1" :message="errors.careers_primary_button_url" />
            </div>
            <div>
              <InputLabel value="Label Tombol 2" />
              <TextInput v-model="form.careers_secondary_button_label" class="mt-1 w-full" />
            </div>
            <div>
              <InputLabel value="URL Tombol 2" />
              <TextInput v-model="form.careers_secondary_button_url" class="mt-1 w-full" />
              <InputError class="mt-1" :message="errors.careers_secondary_button_url" />
            </div>
          </div>
        </section>

        <div class="flex justify-end">
          <PrimaryButton :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Pengaturan Careers' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
