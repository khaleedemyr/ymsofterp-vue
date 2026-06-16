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
const search = ref('');
const draggingParamId = ref(null);
const draggingSelectedIndex = ref(null);

const parameterMap = computed(() => {
  const map = {};
  (props.categories || []).forEach((cat) => {
    (cat.subcategories || []).forEach((sub) => {
      (sub.parameters || []).forEach((param) => {
        map[param.id] = {
          ...param,
          category_code: cat.code,
          category_name: cat.name,
          subcategory_code: sub.code,
          subcategory_name: sub.name,
          search_index: `${param.code} ${param.text} ${cat.code} ${cat.name} ${sub.code} ${sub.name}`.toLowerCase(),
        };
      });
    });
  });

  return map;
});

const allParameters = computed(() => {
  return Object.values(parameterMap.value).sort((a, b) => {
    const aCode = a.code || '';
    const bCode = b.code || '';
    return aCode.localeCompare(bCode);
  });
});

const selectedSet = computed(() => new Set(form.value.parameter_ids));

const selectedParams = computed(() => {
  return form.value.parameter_ids
    .map((id) => parameterMap.value[id])
    .filter(Boolean);
});

const availableParams = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return allParameters.value.filter((p) => {
    if (selectedSet.value.has(p.id)) return false;
    if (!keyword) return true;
    return p.search_index.includes(keyword);
  });
});

function addParam(id) {
  if (!form.value.parameter_ids.includes(id)) {
    form.value.parameter_ids.push(id);
  }
}

function removeParam(id) {
  form.value.parameter_ids = form.value.parameter_ids.filter((x) => x !== id);
}

function moveParamToIndex(id, targetIndex) {
  const list = [...form.value.parameter_ids];
  const fromIndex = list.indexOf(id);
  if (fromIndex === -1) return;

  list.splice(fromIndex, 1);

  const safeIndex = Math.max(0, Math.min(targetIndex, list.length));
  list.splice(safeIndex, 0, id);

  form.value.parameter_ids = list;
}

function insertParamAtIndex(id, targetIndex) {
  const list = [...form.value.parameter_ids];
  const existingIndex = list.indexOf(id);

  if (existingIndex >= 0) {
    list.splice(existingIndex, 1);
  }

  const safeIndex = Math.max(0, Math.min(targetIndex, list.length));
  list.splice(safeIndex, 0, id);
  form.value.parameter_ids = list;
}

function startDragFromAvailable(id) {
  draggingParamId.value = id;
  draggingSelectedIndex.value = null;
}

function startDragFromSelected(id, index) {
  draggingParamId.value = id;
  draggingSelectedIndex.value = index;
}

function allowDrop(event) {
  event.preventDefault();
}

function dropToSelectedAt(index) {
  const id = draggingParamId.value;
  if (!id) return;

  if (draggingSelectedIndex.value !== null) {
    moveParamToIndex(id, index);
  } else {
    insertParamAtIndex(id, index);
  }

  clearDragState();
}

function dropToSelectedEnd() {
  const id = draggingParamId.value;
  if (!id) return;

  if (draggingSelectedIndex.value !== null) {
    moveParamToIndex(id, form.value.parameter_ids.length);
  } else {
    addParam(id);
  }

  clearDragState();
}

function clearDragState() {
  draggingParamId.value = null;
  draggingSelectedIndex.value = null;
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
          <h2 class="text-lg font-bold mb-4">Builder Parameter Template</h2>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="border rounded-xl p-3 bg-gray-50">
              <div class="mb-3">
                <label class="block text-sm mb-1">Cari Parameter</label>
                <input
                  v-model="search"
                  placeholder="Cari code, isi parameter, kategori, subkategori..."
                  class="w-full border rounded-lg px-3 py-2 bg-white"
                />
              </div>

              <p class="text-xs text-gray-600 mb-2">
                Hasil: <strong>{{ availableParams.length }}</strong> parameter (drag ke area kanan)
              </p>

              <div class="space-y-2 max-h-[66vh] overflow-auto pr-1">
                <div
                  v-for="p in availableParams"
                  :key="p.id"
                  class="bg-white border rounded-lg p-2 cursor-grab active:cursor-grabbing"
                  draggable="true"
                  @dragstart="startDragFromAvailable(p.id)"
                  @dragend="clearDragState"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="text-sm font-semibold text-gray-800">
                        {{ p.code }}
                        <span class="text-blue-700 font-medium">(bobot {{ p.weight }})</span>
                      </p>
                      <p class="text-xs text-gray-600">{{ p.text }}</p>
                      <p class="text-[11px] text-gray-500 mt-1">
                        {{ p.category_code }} / {{ p.subcategory_code }}
                      </p>
                    </div>
                    <button
                      type="button"
                      class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700"
                      @click="addParam(p.id)"
                    >
                      Tambah
                    </button>
                  </div>
                </div>
                <p v-if="availableParams.length === 0" class="text-xs text-gray-500 py-2">
                  Tidak ada parameter yang cocok.
                </p>
              </div>
            </div>

            <div
              class="border-2 border-dashed rounded-xl p-3 min-h-[18rem]"
              @dragover="allowDrop"
              @drop="dropToSelectedEnd"
            >
              <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-gray-800">Area Template Terpilih</p>
                <p class="text-xs text-gray-600">{{ form.parameter_ids.length }} parameter</p>
              </div>

              <div class="space-y-2 max-h-[66vh] overflow-auto pr-1">
                <div
                  v-for="(p, index) in selectedParams"
                  :key="`${p.id}-${index}`"
                  class="bg-blue-50 border border-blue-200 rounded-lg p-2"
                  draggable="true"
                  @dragstart="startDragFromSelected(p.id, index)"
                  @dragend="clearDragState"
                  @dragover="allowDrop"
                  @drop="dropToSelectedAt(index)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="text-xs text-blue-800 font-semibold">#{{ index + 1 }} {{ p.code }}</p>
                      <p class="text-xs text-gray-700">{{ p.text }}</p>
                      <p class="text-[11px] text-gray-500 mt-1">{{ p.category_code }} / {{ p.subcategory_code }}</p>
                    </div>
                    <button
                      type="button"
                      class="text-xs px-2 py-1 rounded bg-red-100 text-red-700"
                      @click="removeParam(p.id)"
                    >
                      Hapus
                    </button>
                  </div>
                </div>

                <p v-if="selectedParams.length === 0" class="text-xs text-gray-500 py-8 text-center">
                  Drop parameter ke area ini untuk menambahkan ke template.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
