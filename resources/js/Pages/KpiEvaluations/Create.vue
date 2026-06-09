<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  employees: Array,
  defaultPeriod: String,
});

const selectedEmployee = ref(null);
const periodMonth = ref(props.defaultPeriod);
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');

const form = useForm({
  user_id: null,
  period_month: props.defaultPeriod,
});

watch(selectedEmployee, (emp) => {
  form.user_id = emp?.id ?? null;
  loadPreview();
});

watch(periodMonth, (val) => {
  form.period_month = val;
  loadPreview();
});

async function loadPreview() {
  preview.value = null;
  previewError.value = '';
  if (!form.user_id || !form.period_month) return;

  previewLoading.value = true;
  try {
    const { data } = await axios.get(route('kpi-evaluations.preview-employee'), {
      params: { user_id: form.user_id, period_month: form.period_month },
    });
    preview.value = data;
    if (!data.template) {
      previewError.value = 'Tidak ada template KPI aktif untuk jabatan karyawan ini. Publish template terlebih dahulu.';
    }
  } catch (e) {
    previewError.value = e.response?.data?.message || 'Gagal memuat preview.';
  } finally {
    previewLoading.value = false;
  }
}

function submit() {
  form.post(route('kpi-evaluations.store'));
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
          <p class="text-sm text-gray-600">Pilih karyawan dan periode — template mengikuti jabatan.</p>
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
          <label class="block text-sm font-medium mb-1">Periode (Bulan)</label>
          <input v-model="periodMonth" type="month" class="w-full rounded-xl border-gray-300" />
          <div v-if="form.errors.period_month" class="text-xs text-red-500 mt-1">{{ form.errors.period_month }}</div>
        </div>

        <div v-if="previewLoading" class="text-sm text-gray-500">Memuat preview...</div>

        <div v-else-if="preview" class="rounded-xl border bg-rose-50/40 p-4 text-sm space-y-2">
          <div><span class="text-gray-500">Jabatan:</span> <strong>{{ preview.user?.nama_jabatan || '—' }}</strong></div>
          <div><span class="text-gray-500">Outlet:</span> <strong>{{ preview.user?.nama_outlet || '—' }}</strong></div>
          <div><span class="text-gray-500">Divisi:</span> <strong>{{ preview.user?.nama_divisi || '—' }}</strong></div>
          <div v-if="preview.template">
            <span class="text-gray-500">Template:</span>
            <strong>{{ preview.template.name }}</strong>
            <span class="text-gray-400 font-mono text-xs ml-1">({{ preview.template.code }} v{{ preview.template.version }})</span>
          </div>
          <div v-if="preview.period?.label"><span class="text-gray-500">Periode:</span> {{ preview.period.label }}</div>
        </div>

        <div v-if="previewError" class="text-sm text-red-600 bg-red-50 rounded-xl p-3">{{ previewError }}</div>
        <div v-if="form.errors.user_id" class="text-xs text-red-500">{{ form.errors.user_id }}</div>

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