<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  template: { type: Object, default: null },
  formData: Object,
});

const isEdit = computed(() => !!props.template?.id);

const jabatanOptions = computed(() => props.formData?.jabatans ?? []);
const keyStrategyOptions = computed(() => props.formData?.keyStrategies ?? []);
const parameterOptions = computed(() => props.formData?.parameters ?? []);

const form = useForm({
  code: '',
  name: '',
  description: '',
  template_status: 'draft',
  status: 'A',
  scoring_rules: { exceeding_min: 100, meeting_min: 85, below_max: 85 },
  jabatan_ids: [],
  strategies: [],
});

const selectedJabatans = computed({
  get() {
    return jabatanOptions.value.filter((j) => form.jabatan_ids.includes(j.id_jabatan));
  },
  set(value) {
    form.jabatan_ids = (value || []).map((j) => j.id_jabatan);
  },
});

function initFromTemplate() {
  if (!props.template) {
    form.strategies = [];
    form.jabatan_ids = [];
    form.scoring_rules = { ...(props.formData?.defaultScoringRules || {}) };
    return;
  }
  form.code = props.template.code;
  form.name = props.template.name;
  form.description = props.template.description || '';
  form.template_status = props.template.template_status;
  form.status = props.template.status;
  form.scoring_rules = { ...(props.template.scoring_rules || props.formData?.defaultScoringRules || {}) };
  form.jabatan_ids = [...(props.template.jabatan_ids || [])];
  form.strategies = (props.template.strategies || []).map((s) => ({
    id: s.id,
    kpi_key_strategy_id: s.kpi_key_strategy_id,
    weight_percent: s.weight_percent,
    sort_order: s.sort_order,
    items: (s.items || []).map((item) => ({
      id: item.id,
      name: item.name,
      description: item.description || '',
      weight_percent: item.weight_percent,
      target_value: item.target_value || '',
      target_direction: item.target_direction || 'higher_better',
      frequency: item.frequency || 'monthly',
      formula: item.formula || '',
      scoring_levels: item.scoring_levels,
      sort_order: item.sort_order,
      parameter_ids: [...(item.parameter_ids || [])],
    })),
  }));

  form.strategies.forEach((s) => s.items?.forEach((item) => {
    if (item.parameter_ids?.length > 1) {
      item.parameter_ids = [item.parameter_ids[0]];
    }
    syncItemFromParameter(item);
  }));
}

initFromTemplate();

const strategyWeightTotal = computed(() =>
  form.strategies.reduce((sum, s) => sum + Number(s.weight_percent || 0), 0).toFixed(2)
);

const itemWeightTotal = computed(() => {
  let total = 0;
  form.strategies.forEach((s) => s.items?.forEach((i) => { total += Number(i.weight_percent || 0); }));
  return total.toFixed(2);
});

function addStrategy() {
  form.strategies.push({
    kpi_key_strategy_id: '',
    weight_percent: 0,
    sort_order: form.strategies.length,
    items: [],
  });
}

function removeStrategy(index) {
  form.strategies.splice(index, 1);
}

function addItem(strategyIndex) {
  form.strategies[strategyIndex].items.push({
    name: '',
    description: '',
    weight_percent: 0,
    target_value: '',
    target_direction: 'higher_better',
    frequency: 'monthly',
    formula: '',
    parameter_ids: [],
    sort_order: form.strategies[strategyIndex].items.length,
  });
}

function removeItem(strategyIndex, itemIndex) {
  form.strategies[strategyIndex].items.splice(itemIndex, 1);
}

function keyStrategyName(id) {
  return props.formData?.keyStrategies?.find((k) => k.id === id)?.name || '';
}

function formatKeyStrategyLabel(ks) {
  return ks ? `${ks.code} — ${ks.name}` : '';
}

function formatParameterLabel(param) {
  return param ? `${param.code} — ${param.name} (${param.source_type})` : '';
}

function findKeyStrategy(id) {
  if (!id) return null;
  return keyStrategyOptions.value.find((k) => k.id === id) || null;
}

function setKeyStrategy(strategy, option) {
  strategy.kpi_key_strategy_id = option?.id ?? '';
}

function formatDirectionLabel(value) {
  return props.formData?.targetDirections?.find((d) => d.value === value)?.label || value || '—';
}

function formatFrequencyLabel(value) {
  return props.formData?.frequencies?.find((f) => f.value === value)?.label || value || '—';
}

function getSelectedParameter(item) {
  const id = item.parameter_ids?.[0];
  if (!id) return null;
  return parameterOptions.value.find((p) => p.id === id) || null;
}

function syncItemFromParameter(item, { resetTarget = false } = {}) {
  const param = getSelectedParameter(item);
  if (!param) {
    item.name = '';
    item.formula = '';
    item.target_value = '';
    item.target_direction = 'higher_better';
    item.frequency = 'monthly';
    return;
  }
  item.name = param.name;
  item.formula = param.formula || '';
  item.target_direction = param.target_direction || 'higher_better';
  if (resetTarget || !String(item.target_value || '').trim()) {
    item.target_value = param.target_value || '';
  }
  if (resetTarget || !String(item.frequency || '').trim()) {
    item.frequency = param.frequency || 'monthly';
  }
}

function setSelectedParameter(item, param) {
  const previousId = item.parameter_ids?.[0] ?? null;
  item.parameter_ids = param ? [param.id] : [];
  syncItemFromParameter(item, { resetTarget: param?.id !== previousId });
}

function submit() {
  let missingParameters = false;
  form.strategies.forEach((s) => {
    s.items?.forEach((item) => {
      if (!item.parameter_ids?.length) missingParameters = true;
    });
  });

  if (missingParameters) {
    Swal.fire('Validasi', 'Setiap KPI wajib memilih 1 parameter.', 'warning');
    return;
  }
  if (form.strategies.length === 0) {
    Swal.fire('Validasi', 'Minimal 1 Key Strategy diperlukan.', 'warning');
    return;
  }
  const opts = {
    onSuccess: () => Swal.fire('Berhasil', 'Template KPI disimpan.', 'success'),
    onError: (errors) => {
      const msg = errors.strategies || Object.values(errors)[0];
      Swal.fire('Validasi', msg, 'error');
    },
  };
  if (isEdit.value) {
    form.put(route('kpi-templates.update', props.template.id), opts);
  } else {
    form.post(route('kpi-templates.store'), opts);
  }
}

function cancel() {
  router.visit(route('kpi-templates.index'));
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit KPI Template' : 'Buat KPI Template'">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-4 mb-6">
        <button class="text-gray-500" @click="cancel"><i class="fa-solid fa-arrow-left"></i></button>
        <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit' : 'Buat' }} KPI Template</h1>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Kode Template *</label>
            <input v-model="form.code" class="mt-1 w-full rounded-lg border-gray-300" placeholder="KPI_OUTLET_MGR_v1" />
            <div v-if="form.errors.code" class="text-xs text-red-500">{{ form.errors.code }}</div>
          </div>
          <div>
            <label class="text-sm font-medium">Nama Template *</label>
            <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300" />
            <div v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</div>
          </div>
          <div class="md:col-span-2">
            <label class="text-sm font-medium">Deskripsi</label>
            <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="text-sm font-medium">Status Template</label>
            <select v-model="form.template_status" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>

        <!-- Jabatan bridging -->
        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-lg mb-1">Assign Jabatan</h2>
          <p class="text-sm text-gray-600 mb-3">Pilih jabatan yang memakai template ini. Ketik untuk mencari nama jabatan.</p>
          <Multiselect
            v-model="selectedJabatans"
            :options="jabatanOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            :searchable="true"
            :preserve-search="true"
            :show-labels="false"
            :hide-selected="true"
            placeholder="Ketik nama jabatan..."
            label="nama_jabatan"
            track-by="id_jabatan"
            class="kpi-template-multiselect"
          >
            <template #noResult>
              <span class="px-2 py-1 text-sm text-gray-500">Jabatan tidak ditemukan</span>
            </template>
            <template #noOptions>
              <span class="px-2 py-1 text-sm text-gray-500">Tidak ada data jabatan</span>
            </template>
          </Multiselect>
          <p v-if="selectedJabatans.length" class="text-xs text-gray-500 mt-2">
            {{ selectedJabatans.length }} jabatan dipilih
          </p>
        </div>

        <!-- Weight summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="rounded-xl p-4 border" :class="strategyWeightTotal == 100 ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
            <div class="text-sm text-gray-600">Total Bobot Key Strategy</div>
            <div class="text-2xl font-bold">{{ strategyWeightTotal }}%</div>
            <div class="text-xs" :class="strategyWeightTotal == 100 ? 'text-green-700' : 'text-amber-700'">Target: 100%</div>
          </div>
          <div class="rounded-xl p-4 border" :class="itemWeightTotal == 100 ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
            <div class="text-sm text-gray-600">Total Bobot KPI (semua item)</div>
            <div class="text-2xl font-bold">{{ itemWeightTotal }}%</div>
            <div class="text-xs" :class="itemWeightTotal == 100 ? 'text-green-700' : 'text-amber-700'">Target: 100%</div>
          </div>
        </div>

        <!-- Strategies -->
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <h2 class="font-bold text-lg">Key Strategy & KPI Items</h2>
            <button type="button" class="text-sm bg-rose-100 text-rose-700 px-3 py-1.5 rounded-lg" @click="addStrategy">+ Key Strategy</button>
          </div>

          <div v-for="(strategy, sIdx) in form.strategies" :key="sIdx" class="bg-white rounded-2xl shadow border border-rose-100">
            <div class="bg-rose-50 px-4 py-3 flex flex-wrap gap-3 items-end">
              <div class="flex-1 min-w-[240px]">
                <label class="text-xs font-medium text-gray-600">Key Strategy</label>
                <Multiselect
                  :model-value="findKeyStrategy(strategy.kpi_key_strategy_id)"
                  :options="keyStrategyOptions"
                  :searchable="true"
                  :allow-empty="true"
                  :show-labels="false"
                  :custom-label="formatKeyStrategyLabel"
                  placeholder="Ketik kode atau nama key strategy..."
                  label="name"
                  track-by="id"
                  class="kpi-template-multiselect mt-1"
                  @update:model-value="(val) => setKeyStrategy(strategy, val)"
                >
                  <template #noResult>
                    <span class="px-2 py-1 text-xs text-gray-500">Key strategy tidak ditemukan</span>
                  </template>
                </Multiselect>
              </div>
              <div class="w-28">
                <label class="text-xs font-medium text-gray-600">Bobot %</label>
                <input v-model.number="strategy.weight_percent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <button type="button" class="text-red-500 px-2 py-2" @click="removeStrategy(sIdx)"><i class="fa-solid fa-trash"></i></button>
            </div>

            <div class="p-4 space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-700">KPI Items — {{ keyStrategyName(strategy.kpi_key_strategy_id) }}</span>
                <button type="button" class="text-xs text-rose-600" @click="addItem(sIdx)">+ KPI</button>
              </div>

              <div v-for="(item, iIdx) in strategy.items" :key="iIdx" class="border rounded-xl p-4 bg-gray-50/50 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div class="md:col-span-2">
                    <label class="text-xs font-medium">Parameter KPI *</label>
                    <p class="text-xs text-gray-500 mb-1">Pilih parameter — nama &amp; formula mengikuti master. Target &amp; frequency bisa disesuaikan per template/jabatan.</p>
                    <Multiselect
                      :model-value="getSelectedParameter(item)"
                      :options="parameterOptions"
                      :searchable="true"
                      :allow-empty="true"
                      :show-labels="false"
                      :custom-label="formatParameterLabel"
                      placeholder="Ketik kode atau nama parameter..."
                      label="name"
                      track-by="id"
                      class="kpi-template-multiselect mt-1"
                      @update:model-value="(val) => setSelectedParameter(item, val)"
                    >
                      <template #noResult>
                        <span class="px-2 py-1 text-xs text-gray-500">Parameter tidak ditemukan</span>
                      </template>
                    </Multiselect>
                  </div>
                  <div>
                    <label class="text-xs font-medium">Bobot % *</label>
                    <input v-model.number="item.weight_percent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                  </div>
                  <div class="md:col-span-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 rounded-lg bg-white border border-gray-200 p-3 text-sm">
                    <div>
                      <span class="text-xs text-gray-500 block">Nama KPI</span>
                      <span class="font-medium text-gray-800">{{ item.name || '—' }}</span>
                    </div>
                    <div>
                      <label class="text-xs text-gray-500 block">Target</label>
                      <input
                        v-model="item.target_value"
                        type="text"
                        placeholder="Min. 3 Products"
                        class="mt-0.5 w-full rounded-lg border-gray-300 text-sm font-medium text-gray-800"
                      />
                    </div>
                    <div>
                      <span class="text-xs text-gray-500 block">Direction</span>
                      <span class="font-medium text-gray-800">{{ formatDirectionLabel(item.target_direction) }}</span>
                    </div>
                    <div>
                      <span class="text-xs text-gray-500 block">Frequency</span>
                      <span class="font-medium text-gray-800">{{ formatFrequencyLabel(item.frequency) }}</span>
                    </div>
                    <div class="sm:col-span-2 md:col-span-4">
                      <span class="text-xs text-gray-500 block">Formula</span>
                      <span class="font-mono text-gray-800">{{ item.formula || '—' }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex justify-end">
                  <button type="button" class="text-xs text-red-500" @click="removeItem(sIdx, iIdx)">Hapus KPI</button>
                </div>
              </div>

              <p v-if="!strategy.items?.length" class="text-sm text-gray-400 text-center py-4">Belum ada KPI item.</p>
            </div>
          </div>
        </div>

        <div class="flex gap-3 sticky bottom-4 bg-white/90 backdrop-blur p-4 rounded-2xl shadow-lg border">
          <button type="button" class="px-6 py-3 rounded-xl border" @click="cancel">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-6 py-3 rounded-xl bg-rose-600 text-white font-semibold disabled:opacity-50">
            {{ form.processing ? 'Menyimpan…' : 'Simpan Template' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<style scoped>
.kpi-template-multiselect :deep(.multiselect__tags) {
  min-height: 38px;
  border-radius: 0.5rem;
  border-color: #d1d5db;
  padding-top: 6px;
}

.kpi-template-multiselect :deep(.multiselect__input),
.kpi-template-multiselect :deep(.multiselect__single),
.kpi-template-multiselect :deep(.multiselect__placeholder) {
  font-size: 0.875rem;
}

.kpi-template-multiselect :deep(.multiselect__option--highlight) {
  background: #e11d48;
}

.kpi-template-multiselect :deep(.multiselect__option--selected) {
  background: #ffe4e6;
  color: #9f1239;
  font-weight: 500;
}

.kpi-template-multiselect :deep(.multiselect__tag) {
  background: #e11d48;
  margin-bottom: 4px;
  font-size: 0.75rem;
}

.kpi-template-multiselect :deep(.multiselect__tag-icon:hover) {
  background: #be123c;
}

.kpi-template-multiselect :deep(.multiselect__content-wrapper) {
  border-radius: 0.5rem;
  z-index: 60;
}
</style>
