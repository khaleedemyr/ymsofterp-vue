<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  mode: String,
  subcategory: Object,
  categories: Array,
});

const isEdit = computed(() => props.mode === 'edit');

const form = ref({
  category_id: props.subcategory?.category_id || '',
  code: props.subcategory?.code || '',
  name: props.subcategory?.name || '',
  sort_order: props.subcategory?.sort_order || 1,
  status: props.subcategory?.status || 'A',
});

const errors = ref({});
const saving = ref(false);

function submit() {
  saving.value = true;
  errors.value = {};

  if (isEdit.value) {
    router.put(route('qa2-subcategories.update', props.subcategory.id), form.value, options());
  } else {
    router.post(route('qa2-subcategories.store'), form.value, options());
  }
}

function options() {
  return {
    onError: (e) => { errors.value = e; },
    onFinish: () => { saving.value = false; },
  };
}

function back() {
  router.visit(route('qa2-subcategories.index'));
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit QA2 Subcategory' : 'Create QA2 Subcategory'">
    <div class="w-full max-w-3xl mx-auto py-8 px-3">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ isEdit ? 'Edit QA2 Subcategory' : 'Create QA2 Subcategory' }}</h1>
        <button class="px-3 py-2 bg-gray-100 rounded-xl" @click="back">Kembali</button>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <div>
          <label class="block text-sm mb-1">Category</label>
          <select v-model="form.category_id" class="w-full border rounded-xl px-3 py-2">
            <option value="" disabled>Pilih category</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
          </select>
          <div v-if="errors.category_id" class="text-sm text-red-600 mt-1">{{ errors.category_id }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Code</label>
          <input v-model="form.code" class="w-full border rounded-xl px-3 py-2" />
          <div v-if="errors.code" class="text-sm text-red-600 mt-1">{{ errors.code }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Nama</label>
          <input v-model="form.name" class="w-full border rounded-xl px-3 py-2" />
          <div v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Sort Order</label>
          <input v-model.number="form.sort_order" type="number" min="1" class="w-full border rounded-xl px-3 py-2" />
          <div v-if="errors.sort_order" class="text-sm text-red-600 mt-1">{{ errors.sort_order }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Status</label>
          <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
            <option value="A">Aktif</option>
            <option value="N">Non-Aktif</option>
          </select>
        </div>

        <button :disabled="saving" class="w-full bg-blue-600 text-white py-2 rounded-xl font-semibold disabled:opacity-50" @click="submit">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
