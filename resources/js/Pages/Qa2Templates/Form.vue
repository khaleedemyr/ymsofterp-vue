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
const categorySearch = ref('');
const subcategorySearch = ref('');
const selectedCategory = ref('');
const selectedSubcategory = ref('');
const selectedSearch = ref('');
const selectedCategorySearch = ref('');
const selectedSubcategorySearch = ref('');
const selectedAreaCategory = ref('');
const selectedAreaSubcategory = ref('');
const draggingParamId = ref(null);
const draggingSelectedIndex = ref(null);

const parameterMap = computed(() => {
  const map = {};
  (props.categories || []).forEach((cat) => {
    (cat.subcategories || []).forEach((sub) => {
      (sub.parameters || []).forEach((param) => {
        map[param.id] = {
          ...param,
          category_key: `${cat.code}__${cat.name}`,
          subcategory_key: `${sub.code}__${sub.name}`,
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

const categoryOptions = computed(() => {
  const map = new Map();
  allParameters.value.forEach((p) => {
    if (!map.has(p.category_key)) {
      map.set(p.category_key, {
        key: p.category_key,
        code: p.category_code,
        name: p.category_name,
      });
    }
  });

  return Array.from(map.values()).sort((a, b) => `${a.code} ${a.name}`.localeCompare(`${b.code} ${b.name}`));
});

const subcategoryOptions = computed(() => {
  const map = new Map();
  allParameters.value.forEach((p) => {
    if (selectedCategory.value && p.category_key !== selectedCategory.value) return;

    if (!map.has(p.subcategory_key)) {
      map.set(p.subcategory_key, {
        key: p.subcategory_key,
        code: p.subcategory_code,
        name: p.subcategory_name,
      });
    }
  });

  return Array.from(map.values()).sort((a, b) => `${a.code} ${a.name}`.localeCompare(`${b.code} ${b.name}`));
});

const selectedParams = computed(() => {
  return form.value.parameter_ids
    .map((id) => parameterMap.value[id])
    .filter(Boolean);
});

const selectedParamsWithIndex = computed(() => {
  return form.value.parameter_ids
    .map((id, index) => ({
      originalIndex: index,
      param: parameterMap.value[id],
    }))
    .filter((item) => !!item.param);
});

const availableParams = computed(() => {
  const keyword = search.value.trim().toLowerCase();
  const categoryKeyword = categorySearch.value.trim().toLowerCase();
  const subcategoryKeyword = subcategorySearch.value.trim().toLowerCase();

  return allParameters.value.filter((p) => {
    if (selectedSet.value.has(p.id)) return false;

    if (selectedCategory.value && p.category_key !== selectedCategory.value) return false;
    if (selectedSubcategory.value && p.subcategory_key !== selectedSubcategory.value) return false;

    if (categoryKeyword) {
      const source = `${p.category_code} ${p.category_name}`.toLowerCase();
      if (!source.includes(categoryKeyword)) return false;
    }

    if (subcategoryKeyword) {
      const source = `${p.subcategory_code} ${p.subcategory_name}`.toLowerCase();
      if (!source.includes(subcategoryKeyword)) return false;
    }

    if (!keyword) return true;
    return p.search_index.includes(keyword);
  });
});

const selectedCategoryOptions = computed(() => {
  const map = new Map();
  selectedParams.value.forEach((p) => {
    if (!map.has(p.category_key)) {
      map.set(p.category_key, {
        key: p.category_key,
        code: p.category_code,
        name: p.category_name,
      });
    }
  });

  return Array.from(map.values()).sort((a, b) => `${a.code} ${a.name}`.localeCompare(`${b.code} ${b.name}`));
});

const selectedSubcategoryOptions = computed(() => {
  const map = new Map();
  selectedParams.value.forEach((p) => {
    if (selectedAreaCategory.value && p.category_key !== selectedAreaCategory.value) return;

    if (!map.has(p.subcategory_key)) {
      map.set(p.subcategory_key, {
        key: p.subcategory_key,
        code: p.subcategory_code,
        name: p.subcategory_name,
      });
    }
  });

  return Array.from(map.values()).sort((a, b) => `${a.code} ${a.name}`.localeCompare(`${b.code} ${b.name}`));
});

const filteredSelectedParams = computed(() => {
  const keyword = selectedSearch.value.trim().toLowerCase();
  const categoryKeyword = selectedCategorySearch.value.trim().toLowerCase();
  const subcategoryKeyword = selectedSubcategorySearch.value.trim().toLowerCase();

  return selectedParamsWithIndex.value.filter((item) => {
    const p = item.param;

    if (selectedAreaCategory.value && p.category_key !== selectedAreaCategory.value) return false;
    if (selectedAreaSubcategory.value && p.subcategory_key !== selectedAreaSubcategory.value) return false;

    if (categoryKeyword) {
      const source = `${p.category_code} ${p.category_name}`.toLowerCase();
      if (!source.includes(categoryKeyword)) return false;
    }

    if (subcategoryKeyword) {
      const source = `${p.subcategory_code} ${p.subcategory_name}`.toLowerCase();
      if (!source.includes(subcategoryKeyword)) return false;
    }

    if (!keyword) return true;
    return p.search_index.includes(keyword);
  });
});

function clearFilters() {
  search.value = '';
  categorySearch.value = '';
  subcategorySearch.value = '';
  selectedCategory.value = '';
  selectedSubcategory.value = '';
}

function clearSelectedFilters() {
  selectedSearch.value = '';
  selectedCategorySearch.value = '';
  selectedSubcategorySearch.value = '';
  selectedAreaCategory.value = '';
  selectedAreaSubcategory.value = '';
}

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

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Search Kategori</label>
                  <input
                    v-model="categorySearch"
                    placeholder="Contoh: CLEANING"
                    class="w-full border rounded-lg px-3 py-2 bg-white text-sm"
                  />
                </div>
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Search Subkategori</label>
                  <input
                    v-model="subcategorySearch"
                    placeholder="Contoh: SERVING"
                    class="w-full border rounded-lg px-3 py-2 bg-white text-sm"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Filter Kategori</label>
                  <select v-model="selectedCategory" class="w-full border rounded-lg px-3 py-2 bg-white text-sm">
                    <option value="">Semua kategori</option>
                    <option v-for="opt in categoryOptions" :key="opt.key" :value="opt.key">
                      {{ opt.code }} - {{ opt.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Filter Subkategori</label>
                  <select v-model="selectedSubcategory" class="w-full border rounded-lg px-3 py-2 bg-white text-sm">
                    <option value="">Semua subkategori</option>
                    <option v-for="opt in subcategoryOptions" :key="opt.key" :value="opt.key">
                      {{ opt.code }} - {{ opt.name }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <button type="button" class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-700" @click="clearFilters">
                  Reset Filter
                </button>
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
                      <div class="mt-2 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 text-[10px] px-2.5 py-1 rounded-full border border-sky-300 bg-sky-50 text-sky-800 font-semibold tracking-wide uppercase shadow-sm">
                          <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                          Cat
                          <span class="normal-case tracking-normal">{{ p.category_code }} - {{ p.category_name }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2.5 py-1 rounded-full border border-indigo-300 bg-indigo-50 text-indigo-800 font-semibold tracking-wide uppercase shadow-sm">
                          <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                          Sub
                          <span class="normal-case tracking-normal">{{ p.subcategory_code }} - {{ p.subcategory_name }}</span>
                        </span>
                      </div>
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

              <div class="mb-3">
                <label class="block text-sm mb-1">Cari Parameter</label>
                <input
                  v-model="selectedSearch"
                  placeholder="Cari code, isi parameter, kategori, subkategori..."
                  class="w-full border rounded-lg px-3 py-2 bg-white"
                />
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Search Kategori</label>
                  <input
                    v-model="selectedCategorySearch"
                    placeholder="Contoh: INFRASTRUCTURE"
                    class="w-full border rounded-lg px-3 py-2 bg-white text-sm"
                  />
                </div>
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Search Subkategori</label>
                  <input
                    v-model="selectedSubcategorySearch"
                    placeholder="Contoh: FACADE"
                    class="w-full border rounded-lg px-3 py-2 bg-white text-sm"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Filter Kategori</label>
                  <select v-model="selectedAreaCategory" class="w-full border rounded-lg px-3 py-2 bg-white text-sm">
                    <option value="">Semua kategori</option>
                    <option v-for="opt in selectedCategoryOptions" :key="opt.key" :value="opt.key">
                      {{ opt.code }} - {{ opt.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs mb-1 text-gray-600">Filter Subkategori</label>
                  <select v-model="selectedAreaSubcategory" class="w-full border rounded-lg px-3 py-2 bg-white text-sm">
                    <option value="">Semua subkategori</option>
                    <option v-for="opt in selectedSubcategoryOptions" :key="opt.key" :value="opt.key">
                      {{ opt.code }} - {{ opt.name }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <button type="button" class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-700" @click="clearSelectedFilters">
                  Reset Filter
                </button>
              </div>

              <div class="space-y-2 max-h-[66vh] overflow-auto pr-1">
                <div
                  v-for="item in filteredSelectedParams"
                  :key="`${item.param.id}-${item.originalIndex}`"
                  class="bg-blue-50 border border-blue-200 rounded-lg p-2"
                  draggable="true"
                  @dragstart="startDragFromSelected(item.param.id, item.originalIndex)"
                  @dragend="clearDragState"
                  @dragover="allowDrop"
                  @drop="dropToSelectedAt(item.originalIndex)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="text-xs text-blue-800 font-semibold">#{{ item.originalIndex + 1 }} {{ item.param.code }}</p>
                      <p class="text-xs text-gray-700">{{ item.param.text }}</p>
                      <div class="mt-2 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 text-[10px] px-2.5 py-1 rounded-full border border-sky-300 bg-sky-50 text-sky-800 font-semibold tracking-wide uppercase shadow-sm">
                          <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                          Cat
                          <span class="normal-case tracking-normal">{{ item.param.category_code }} - {{ item.param.category_name }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2.5 py-1 rounded-full border border-indigo-300 bg-indigo-50 text-indigo-800 font-semibold tracking-wide uppercase shadow-sm">
                          <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                          Sub
                          <span class="normal-case tracking-normal">{{ item.param.subcategory_code }} - {{ item.param.subcategory_name }}</span>
                        </span>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="text-xs px-2 py-1 rounded bg-red-100 text-red-700"
                      @click="removeParam(item.param.id)"
                    >
                      Hapus
                    </button>
                  </div>
                </div>

                <p v-if="selectedParams.length === 0" class="text-xs text-gray-500 py-8 text-center">
                  Drop parameter ke area ini untuk menambahkan ke template.
                </p>
                <p v-else-if="filteredSelectedParams.length === 0" class="text-xs text-gray-500 py-8 text-center">
                  Tidak ada parameter terpilih yang cocok dengan filter.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
