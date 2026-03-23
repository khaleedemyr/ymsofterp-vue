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
  about: { type: Object, required: true },
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);

const form = ref({
  about_title: props.about.about_title || '',
  about_subtitle: props.about.about_subtitle || '',
  about_our_story_content: props.about.about_our_story_content || '',
  about_brand_philosophy_quote: props.about.about_brand_philosophy_quote || '',
  about_brand_philosophy_content: props.about.about_brand_philosophy_content || '',
  about_vision_title: props.about.about_vision_title || 'VISION',
  about_vision_content: props.about.about_vision_content || '',
  about_mission_title: props.about.about_mission_title || 'MISSION',
  about_mission_content: props.about.about_mission_content || '',
  hero_image: null,
  logo_image: null,
  profile_image: null,
  remove_hero: false,
  remove_logo: false,
  remove_profile: false,
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
  fd.append('about_title', form.value.about_title || '');
  fd.append('about_subtitle', form.value.about_subtitle || '');
  fd.append('about_our_story_content', form.value.about_our_story_content || '');
  fd.append('about_brand_philosophy_quote', form.value.about_brand_philosophy_quote || '');
  fd.append('about_brand_philosophy_content', form.value.about_brand_philosophy_content || '');
  fd.append('about_vision_title', form.value.about_vision_title || 'VISION');
  fd.append('about_vision_content', form.value.about_vision_content || '');
  fd.append('about_mission_title', form.value.about_mission_title || 'MISSION');
  fd.append('about_mission_content', form.value.about_mission_content || '');
  fd.append('remove_hero', form.value.remove_hero ? '1' : '0');
  fd.append('remove_logo', form.value.remove_logo ? '1' : '0');
  fd.append('remove_profile', form.value.remove_profile ? '1' : '0');
  if (form.value.hero_image) fd.append('hero_image', form.value.hero_image);
  if (form.value.logo_image) fd.append('logo_image', form.value.logo_image);
  if (form.value.profile_image) fd.append('profile_image', form.value.profile_image);

  router.post('/web-profile/about-page/settings', fd, {
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
  <AppLayout title="Web Profile - About Page">
    <div class="max-w-5xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Pengaturan About Page</h1>

      <form class="bg-white rounded-lg shadow p-6 space-y-6" @submit.prevent="submit">
        <section class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Hero</h2>
          <div>
            <InputLabel value="Judul Hero" />
            <TextInput v-model="form.about_title" class="mt-1 w-full" />
            <InputError class="mt-1" :message="errors.about_title" />
          </div>
          <div>
            <InputLabel value="Subtitle Hero" />
            <TextInput v-model="form.about_subtitle" class="mt-1 w-full" />
            <InputError class="mt-1" :message="errors.about_subtitle" />
          </div>
          <div v-if="about.about_hero_image_url">
            <img :src="about.about_hero_image_url" class="max-h-48 w-full rounded border object-cover" alt="" />
          </div>
          <div>
            <InputLabel value="Gambar Hero (rekomendasi 1920x1080)" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => { form.hero_image = e.target.files?.[0] || null; form.remove_hero = false; }" />
            <label v-if="about.about_hero_image_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.remove_hero" type="checkbox" />
              Hapus gambar hero
            </label>
            <InputError class="mt-1" :message="errors.hero_image" />
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">Section: About Justus Group</h2>
          <div>
            <InputLabel value="Konten" />
            <textarea v-model="form.about_our_story_content" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
            <InputError class="mt-1" :message="errors.about_our_story_content" />
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">Section: Brand Philosophy</h2>
          <div>
            <InputLabel value="Quote" />
            <TextInput v-model="form.about_brand_philosophy_quote" class="mt-1 w-full" />
          </div>
          <div>
            <InputLabel value="Konten" />
            <textarea v-model="form.about_brand_philosophy_content" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
          </div>
          <div v-if="about.about_logo_image_url">
            <img :src="about.about_logo_image_url" class="max-h-48 w-full rounded border object-cover" alt="" />
          </div>
          <div>
            <InputLabel value="Gambar Logo Section (rekomendasi 1400x500)" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => { form.logo_image = e.target.files?.[0] || null; form.remove_logo = false; }" />
            <label v-if="about.about_logo_image_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.remove_logo" type="checkbox" />
              Hapus gambar logo section
            </label>
            <InputError class="mt-1" :message="errors.logo_image" />
          </div>
        </section>

        <section class="space-y-4 border-t border-gray-200 pt-6">
          <h2 class="text-lg font-semibold text-gray-800">Section: Vision & Mission</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Judul Vision" />
              <TextInput v-model="form.about_vision_title" class="mt-1 w-full" />
            </div>
            <div>
              <InputLabel value="Judul Mission" />
              <TextInput v-model="form.about_mission_title" class="mt-1 w-full" />
            </div>
          </div>
          <div>
            <InputLabel value="Konten Vision" />
            <textarea v-model="form.about_vision_content" rows="4" class="mt-1 w-full rounded-md border-gray-300"></textarea>
          </div>
          <div>
            <InputLabel value="Konten Mission" />
            <textarea v-model="form.about_mission_content" rows="4" class="mt-1 w-full rounded-md border-gray-300"></textarea>
          </div>
          <div v-if="about.about_profile_image_url">
            <img :src="about.about_profile_image_url" class="max-h-64 rounded border object-cover" alt="" />
          </div>
          <div>
            <InputLabel value="Foto Profile (rekomendasi 900x1200)" />
            <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => { form.profile_image = e.target.files?.[0] || null; form.remove_profile = false; }" />
            <label v-if="about.about_profile_image_path" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.remove_profile" type="checkbox" />
              Hapus foto profile
            </label>
            <InputError class="mt-1" :message="errors.profile_image" />
          </div>
        </section>

        <div class="flex justify-end">
          <PrimaryButton :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Pengaturan About' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

