<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  mode: String,
  parameter: Object,
  categories: Array,
});

const isEdit = computed(() => props.mode === 'edit');

const subcategoryOptions = computed(() => {
  const rows = [];
  for (const c of props.categories || []) {
    for (const s of c.subcategories || []) {
      rows.push({
        id: s.id,
        label: `${c.code} - ${c.name} / ${s.code} - ${s.name}`,
      });
    }
  }
  return rows;
});

const form = ref({
  subcategory_id: props.parameter?.subcategory_id || '',
  code: props.parameter?.code || '',
  parameter_text: props.parameter?.parameter_text || '',
  weight: props.parameter?.weight ?? 10,
  sort_order: props.parameter?.sort_order || 1,
  status: props.parameter?.status || 'A',
});

const errors = ref({});
const saving = ref(false);

function submit() {
  saving.value = true;
  errors.value = {};

  if (isEdit.value) {
    router.put(route('qa2-parameters.update', props.parameter.id), form.value, options());
  } else {
    router.post(route('qa2-parameters.store'), form.value, options());
  }
}

function options() {
  return {
    onError: (e) => { errors.value = e; },
    onFinish: () => { saving.value = false; },
  };
}

function back() {
  router.visit(route('qa2-parameters.index'));
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit QA2 Parameter' : 'Create QA2 Parameter'">
    <div class="w-full max-w-4xl mx-auto py-8 px-3">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ isEdit ? 'Edit QA2 Parameter' : 'Create QA2 Parameter' }}</h1>
        <button class="px-3 py-2 bg-gray-100 rounded-xl" @click="back">Kembali</button>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-4">
        <div>
          <label class="block text-sm mb-1">Category / Subcategory</label>
          <select v-model="form.subcategory_id" class="w-full border rounded-xl px-3 py-2">
            <option value="" disabled>Pilih subcategory</option>
            <option v-for="s in subcategoryOptions" :key="s.id" :value="s.id">{{ s.label }}</option>
          </select>
          <div v-if="errors.subcategory_id" class="text-sm text-red-600 mt-1">{{ errors.subcategory_id }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Code</label>
          <input v-model="form.code" class="w-full border rounded-xl px-3 py-2" />
          <div v-if="errors.code" class="text-sm text-red-600 mt-1">{{ errors.code }}</div>
        </div>

        <div>
          <label class="block text-sm mb-1">Parameter Text</label>
          <textarea v-model="form.parameter_text" rows="4" class="w-full border rounded-xl px-3 py-2"></textarea>
          <div v-if="errors.parameter_text" class="text-sm text-red-600 mt-1">{{ errors.parameter_text }}</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Weight</label>
            <input v-model.number="form.weight" type="number" min="0" step="0.01" class="w-full border rounded-xl px-3 py-2" />
            <div v-if="errors.weight" class="text-sm text-red-600 mt-1">{{ errors.weight }}</div>
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
        </div>

        <button :disabled="saving" class="w-full bg-blue-600 text-white py-2 rounded-xl font-semibold disabled:opacity-50" @click="submit">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
