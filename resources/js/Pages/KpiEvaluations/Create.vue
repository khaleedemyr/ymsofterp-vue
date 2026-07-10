<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLoading } from '@/Composables/useLoading';

const props = defineProps({
  employees: Array,
  outlets: Array,
  erpScopeOptions: Array,
  defaultPeriod: String,
});

const { startProgressSimulation, finishProgress, failProgress } = useLoading();

const selectedEmployee = ref(null);
const periodMonth = ref(props.defaultPeriod);
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const regionalScopeHint = ref('');

const form = useForm({
  user_id: null,
  period_month: props.defaultPeriod,
  erp_data_scope: 'single_outlet',
  erp_scope_outlet_ids: [],
});

const selectedSingleOutlet = ref(null);
const selectedMultipleOutlets = ref([]);

const showSinglePicker = computed(() => form.erp_data_scope === 'single_outlet');
const showMultiplePicker = computed(() => form.erp_data_scope === 'multiple_outlets');

function applyEmployeeOutletFallback(emp) {
  if (!emp?.id_outlet || form.erp_data_scope !== 'single_outlet') return;
  const outlet = props.outlets.find((o) => o.id === emp.id_outlet);
  if (outlet) {
    selectedSingleOutlet.value = outlet;
    form.erp_scope_outlet_ids = [outlet.id];
  }
}

function applyErpScopeSuggestion(suggestion) {
  if (!suggestion?.erp_scope_outlet_ids?.length) return false;

  form.erp_data_scope = suggestion.erp_data_scope;
  const ids = suggestion.erp_scope_outlet_ids.map((id) => Number(id));
  form.erp_scope_outlet_ids = ids;

  if (suggestion.erp_data_scope === 'single_outlet') {
    selectedSingleOutlet.value = props.outlets.find((o) => o.id === ids[0]) ?? null;
    selectedMultipleOutlets.value = [];
  } else if (suggestion.erp_data_scope === 'multiple_outlets') {
    selectedMultipleOutlets.value = props.outlets.filter((o) => ids.includes(o.id));
    selectedSingleOutlet.value = null;
  }

  const areaLabel = suggestion.regional_area ? ` (${suggestion.regional_area})` : '';
  const outletLabel = suggestion.outlet_names?.length
    ? suggestion.outlet_names.join(', ')
    : `${ids.length} outlet`;
  regionalScopeHint.value = `Outlet diisi otomatis dari Regional Management${areaLabel}: ${outletLabel}`;

  return true;
}

function applyTemplateErpScope(template) {
  if (!template?.erp_data_scope || template.erp_data_scope === 'all_outlets') return;

  form.erp_data_scope = template.erp_data_scope;

  if (template.erp_data_scope === 'employee_outlet' || template.erp_data_scope === 'all_outlets') {
    form.erp_scope_outlet_ids = [];
    selectedSingleOutlet.value = null;
    selectedMultipleOutlets.value = [];
    return;
  }

  const templateOutletIds = (template.erp_scope_outlet_ids ?? []).map((id) => Number(id));
  if (template.erp_data_scope === 'single_outlet' && templateOutletIds.length > 0) {
    form.erp_scope_outlet_ids = [templateOutletIds[0]];
    selectedSingleOutlet.value = props.outlets.find((o) => o.id === templateOutletIds[0]) ?? null;
    selectedMultipleOutlets.value = [];
    return;
  }

  if (template.erp_data_scope === 'multiple_outlets' && templateOutletIds.length > 0) {
    form.erp_scope_outlet_ids = templateOutletIds;
    selectedMultipleOutlets.value = props.outlets.filter((o) => templateOutletIds.includes(o.id));
    selectedSingleOutlet.value = null;
  }
}

watch(selectedEmployee, (emp) => {
  form.user_id = emp?.id ?? null;
  regionalScopeHint.value = '';
  loadPreview();
});

watch(periodMonth, (val) => {
  form.period_month = val;
  loadPreview();
});

watch(() => form.erp_data_scope, (scope) => {
  if (scope === 'employee_outlet' || scope === 'all_outlets') {
    form.erp_scope_outlet_ids = [];
    selectedSingleOutlet.value = null;
    selectedMultipleOutlets.value = [];
  }
  if (scope === 'single_outlet' && !form.erp_scope_outlet_ids.length && selectedEmployee.value?.id_outlet) {
    applyEmployeeOutletFallback(selectedEmployee.value);
  }
  if (scope === 'multiple_outlets' && !form.erp_scope_outlet_ids.length) {
    selectedMultipleOutlets.value = [];
    form.erp_scope_outlet_ids = [];
  }
});

watch(selectedSingleOutlet, (outlet) => {
  form.erp_scope_outlet_ids = outlet ? [outlet.id] : [];
});

watch(selectedMultipleOutlets, (list) => {
  form.erp_scope_outlet_ids = list.map((o) => o.id);
});

async function loadPreview() {
  preview.value = null;
  previewError.value = '';
  regionalScopeHint.value = '';
  if (!form.user_id || !form.period_month) return;

  previewLoading.value = true;
  startProgressSimulation('Memuat preview template...', {
    estimatedMs: 8000,
    steps: [
      { at: 30, message: 'Mencari template KPI jabatan...' },
      { at: 65, message: 'Memvalidasi periode evaluasi...' },
    ],
  });

  try {
    const { data } = await axios.get(route('kpi-evaluations.preview-employee'), {
      params: { user_id: form.user_id, period_month: form.period_month },
    });
    preview.value = data;

    if (applyErpScopeSuggestion(data.erp_scope_suggestion)) {
      // Regional Management outlet list takes priority.
    } else if (data.template) {
      const tplScope = data.template.erp_data_scope;
      if (tplScope && tplScope !== 'all_outlets') {
        applyTemplateErpScope(data.template);
      } else {
        form.erp_data_scope = 'single_outlet';
        applyEmployeeOutletFallback(selectedEmployee.value);
      }
      if (form.erp_data_scope === 'single_outlet' && !form.erp_scope_outlet_ids.length) {
        applyEmployeeOutletFallback(selectedEmployee.value);
      }
    } else if (selectedEmployee.value) {
      form.erp_data_scope = 'single_outlet';
      applyEmployeeOutletFallback(selectedEmployee.value);
    }

    if (!data.template) {
      previewError.value = data.template_hint
        || 'Tidak ada template KPI aktif untuk jabatan karyawan ini. Publish template terlebih dahulu.';
    }
    await finishProgress('Preview selesai.');
  } catch (e) {
    previewError.value = e.response?.data?.message || 'Gagal memuat preview.';
    failProgress('Gagal memuat preview.');
  } finally {
    previewLoading.value = false;
  }
}

function submit() {
  startProgressSimulation('Membuat draft evaluasi...', {
    estimatedMs: 20000,
    steps: [
      { at: 25, message: 'Menyiapkan parameter evaluasi...' },
      { at: 55, message: 'Menyimpan draft ke database...' },
    ],
  });
  form.post(route('kpi-evaluations.store'), {
    onFinish: () => finishProgress('Draft evaluasi dibuat.'),
    onError: () => failProgress('Gagal membuat draft evaluasi.'),
  });
}

function back() {
  router.visit(route('kpi-evaluations.index'));
}
</script>

<template>
  <AppLayout title="Buat KPI Evaluation">
    <div class="max-w-3xl mx-auto py-8 px-2">
      <div class="flex items-center gap-4 mb-6">
        <button class="text-gray-500" @click="back"><i class="fa-solid fa-arrow-left"></i></button>
        <div>
          <h1 class="text-2xl font-bold">Buat Evaluasi KPI</h1>
          <p class="text-sm text-gray-600">Pilih karyawan, periode, dan scope data ERP.</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 space-y-5">
        <div>
          <label class="block text-sm font-medium mb-1">Karyawan</label>
          <Multiselect
            v-model="selectedEmployee"
            :options="employees"
            label="label"
            track-by="id"
            placeholder="Cari karyawan..."
            :searchable="true"
            :allow-empty="true"
            :show-labels="false"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Periode Evaluasi (Bulan)</label>
          <input v-model="periodMonth" type="month" class="w-full rounded-xl border-gray-300" />
          <p class="text-xs text-gray-500 mt-1">
            KPI dihitung <strong>back month</strong>: pilih Juli 2026 → data parameter dari <strong>Juni 2026</strong> (1–30 Juni).
            Parameter terkait attendance memakai periode payroll (contoh: 26 Mei – 25 Juni).
          </p>
        </div>

        <div class="border rounded-xl p-4 bg-indigo-50/40 space-y-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Scope Data ERP</label>
            <p class="text-xs text-gray-500 mb-2">Dari outlet mana data revenue, COGS, ticket, dll diambil &amp; dijumlahkan.</p>
            <select v-model="form.erp_data_scope" class="w-full rounded-lg border-gray-300">
              <option v-for="opt in erpScopeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <div v-if="showSinglePicker">
            <label class="block text-sm font-medium mb-1">Pilih Outlet</label>
            <Multiselect
              v-model="selectedSingleOutlet"
              :options="outlets"
              label="label"
              track-by="id"
              placeholder="Cari outlet..."
              :searchable="true"
              :allow-empty="false"
              :show-labels="false"
            />
          </div>

          <div v-if="showMultiplePicker">
            <label class="block text-sm font-medium mb-1">Pilih Outlet (multi)</label>
            <Multiselect
              v-model="selectedMultipleOutlets"
              :options="outlets"
              label="label"
              track-by="id"
              placeholder="Cari outlet..."
              :searchable="true"
              :multiple="true"
              :close-on-select="false"
              :show-labels="false"
            />
          </div>

          <p v-if="form.erp_data_scope === 'employee_outlet'" class="text-xs text-gray-600">
            Data diambil dari outlet yang ter-link di profil karyawan.
          </p>
          <p v-if="form.erp_data_scope === 'all_outlets'" class="text-xs text-gray-600">
            Data dijumlahkan dari semua outlet operasional aktif.
          </p>
          <p v-if="regionalScopeHint" class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
            <i class="fa-solid fa-map-location-dot mr-1"></i>{{ regionalScopeHint }}
          </p>
          <div v-if="form.errors.erp_data_scope" class="text-xs text-red-500">{{ form.errors.erp_data_scope }}</div>
          <div v-if="form.errors.erp_scope_outlet_ids" class="text-xs text-red-500">{{ form.errors.erp_scope_outlet_ids }}</div>
        </div>

        <div v-if="previewLoading" class="text-sm text-gray-500">Memuat preview...</div>

        <div v-else-if="preview" class="rounded-xl border bg-rose-50/40 p-4 text-sm space-y-2">
          <div v-if="preview.period">
            <span class="text-gray-500">Periode evaluasi:</span>
            <strong>{{ preview.period.evaluation_label || preview.period.evaluation_period_month }}</strong>
          </div>
          <div v-if="preview.period">
            <span class="text-gray-500">Data KPI (back month):</span>
            <strong>{{ preview.period.data_label || preview.period.data_period_month }}</strong>
            <span class="text-gray-600">— {{ preview.period.start_date }} s/d {{ preview.period.end_date }}</span>
          </div>
          <div v-if="preview.period?.attendance_label">
            <span class="text-gray-500">Periode attendance:</span>
            <strong>{{ preview.period.attendance_start }} s/d {{ preview.period.attendance_end }}</strong>
            <span class="text-gray-600">({{ preview.period.attendance_label }})</span>
          </div>
          <div><span class="text-gray-500">Jabatan:</span> <strong>{{ preview.user?.nama_jabatan || '—' }}</strong></div>
          <div><span class="text-gray-500">Outlet karyawan:</span> <strong>{{ preview.user?.nama_outlet || '—' }}</strong></div>
          <div v-if="preview.template">
            <span class="text-gray-500">Template:</span>
            <strong>{{ preview.template.name }}</strong>
          </div>
        </div>

        <div v-if="previewError" class="text-sm text-red-600 bg-red-50 rounded-xl p-3">{{ previewError }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="px-4 py-2 rounded-xl border" @click="back">Batal</button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold disabled:opacity-50"
            :disabled="form.processing || !form.user_id || !preview?.template"
            @click="submit"
          >
            Buat Draft Evaluasi
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
