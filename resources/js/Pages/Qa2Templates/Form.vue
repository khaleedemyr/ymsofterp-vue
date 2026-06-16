<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  mode: String,
  template: Object,
  categories: Array,
});

const isEdit = computed(() => props.mode === 'edit');

const form = ref({
  code: props.template?.code || '',
  name: props.template?.name || '',
  audit_type: props.template?.audit_type || '',
  department: props.template?.department || '',
  version: props.template?.version || 1,
  status: props.template?.status || 'A',
  notes: props.template?.notes || '',
  parameter_ids: props.template?.parameter_ids || [],
});

const errors = ref({});
const saving = ref(false);

function toggleParam(id) {
  const idx = form.value.parameter_ids.indexOf(id);
  if (idx >= 0) form.value.parameter_ids.splice(idx, 1);
  else form.value.parameter_ids.push(id);
}

function isChecked(id) {
  return form.value.parameter_ids.includes(id);
}

function selectSubcategory(params, checked) {
  const ids = params.map(p => p.id);
  if (checked) {
    ids.forEach(id => { if (!form.value.parameter_ids.includes(id)) form.value.parameter_ids.push(id); });
  } else {
    form.value.parameter_ids = form.value.parameter_ids.filter(id => !ids.includes(id));
  }
}

function submit() {
  saving.value = true;
  errors.value = {};

  const action = isEdit.value
    ? router.put(route('qa2-templates.update', props.template.id), form.value, options())
    : router.post(route('qa2-templates.store'), form.value, options());

  return action;
}

function options() {
  return {
    onError: (e) => { errors.value = e; },
    onFinish: () => { saving.value = false; },
  };
}

function back() {
  router.visit(route('qa2-templates.index'));
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit QA2 Template' : 'Create QA2 Template'">
    <div class="w-full max-w-[110rem] mx-auto py-8 px-3">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ isEdit ? 'Edit QA2 Template' : 'Create QA2 Template' }}</h1>
        <button class="px-3 py-2 bg-gray-100 rounded-xl" @click="back">Kembali</button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white rounded-2xl shadow p-5 space-y-4 h-fit">
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
            <label class="block text-sm mb-1">Audit Type</label>
            <input v-model="form.audit_type" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm mb-1">Department</label>
            <input v-model="form.department" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm mb-1">Version</label>
            <input v-model.number="form.version" type="number" min="1" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm mb-1">Status</label>
            <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
              <option value="A">Aktif</option>
              <option value="N">Non-Aktif</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Notes</label>
            <textarea v-model="form.notes" rows="3" class="w-full border rounded-xl px-3 py-2"></textarea>
          </div>

          <div class="pt-2 border-t">
            <p class="text-sm text-gray-600">Parameter terpilih: <strong>{{ form.parameter_ids.length }}</strong></p>
            <div v-if="errors.parameter_ids" class="text-sm text-red-600 mt-1">{{ errors.parameter_ids }}</div>
          </div>

          <button :disabled="saving" class="w-full bg-blue-600 text-white py-2 rounded-xl font-semibold disabled:opacity-50" @click="submit">
            {{ saving ? 'Menyimpan...' : 'Simpan Template' }}
          </button>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow p-5">
          <h2 class="text-lg font-bold mb-4">Pilih Parameter Template</h2>
          <div class="space-y-5 max-h-[75vh] overflow-auto pr-2">
            <div v-for="cat in categories" :key="cat.id" class="border rounded-xl p-4">
              <h3 class="font-bold text-blue-800 mb-3">{{ cat.code }} - {{ cat.name }}</h3>

              <div class="space-y-3">
                <div v-for="sub in cat.subcategories" :key="sub.id" class="border rounded-lg p-3 bg-gray-50">
                  <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold text-gray-700">{{ sub.code }} - {{ sub.name }}</p>
                    <div class="space-x-2">
                      <button class="text-xs px-2 py-1 rounded bg-green-100 text-green-700" @click="selectSubcategory(sub.parameters, true)">Pilih Semua</button>
                      <button class="text-xs px-2 py-1 rounded bg-red-100 text-red-700" @click="selectSubcategory(sub.parameters, false)">Clear</button>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label v-for="p in sub.parameters" :key="p.id" class="flex items-start gap-2 bg-white rounded p-2 border hover:border-blue-300">
                      <input type="checkbox" :checked="isChecked(p.id)" @change="toggleParam(p.id)" class="mt-1" />
                      <div>
                        <p class="text-sm font-medium">{{ p.code }} <span class="text-blue-700">(bobot {{ p.weight }})</span></p>
                        <p class="text-xs text-gray-600">{{ p.text }}</p>
                      </div>
                    </label>
                    <p v-if="!sub.parameters?.length" class="text-xs text-gray-400">Tidak ada parameter aktif.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
