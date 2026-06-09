<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  mode: String,
  row: Object,
  options: Object,
});

const emit = defineEmits(['close', 'success']);

const showErpMapping = computed(() => ['erp', 'hybrid'].includes(form.source_type));

const form = useForm({
  code: '',
  name: '',
  source_type: 'manual',
  scope_type: 'outlet',
  data_type: 'decimal',
  description: '',
  is_shared: true,
  status: 'A',
  erp_mapping: {
    resolver_key: '',
    aggregation: 'sum',
    static_filters: null,
    dynamic_filter_bindings: null,
  },
});

watch(
  () => props.show,
  (val) => {
    if (!val) return;
    if (props.mode === 'edit' && props.row) {
      form.code = props.row.code;
      form.name = props.row.name;
      form.source_type = props.row.source_type;
      form.scope_type = props.row.scope_type;
      form.data_type = props.row.data_type;
      form.description = props.row.description || '';
      form.is_shared = !!props.row.is_shared;
      form.status = props.row.status;
      const mapping = props.row.erp_mapping || {};
      form.erp_mapping = {
        resolver_key: mapping.resolver_key || '',
        aggregation: mapping.aggregation || 'sum',
        static_filters: mapping.static_filters || null,
        dynamic_filter_bindings: mapping.dynamic_filter_bindings || null,
      };
    } else {
      form.reset();
      form.source_type = 'manual';
      form.scope_type = 'outlet';
      form.data_type = 'decimal';
      form.is_shared = true;
      form.status = 'A';
      form.erp_mapping = { resolver_key: '', aggregation: 'sum', static_filters: null, dynamic_filter_bindings: null };
    }
  },
);

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  const opts = {
    onSuccess: () => { emit('success'); emit('close'); },
    onFinish: () => { isSubmitting.value = false; },
  };
  if (props.mode === 'create') {
    form.post(route('kpi-parameters.store'), opts);
  } else if (props.row) {
    form.put(route('kpi-parameters.update', props.row.id), opts);
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
      <div class="px-6 pt-6 pb-2 border-b">
        <h3 class="text-xl font-bold">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Parameter KPI</h3>
      </div>
      <div class="px-6 py-4 max-h-[70vh] overflow-y-auto space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode *</label>
            <input v-model="form.code" class="mt-1 w-full rounded-lg border-gray-300" placeholder="P001" />
            <div v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama *</label>
            <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Source *</label>
            <select v-model="form.source_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.source_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Scope *</label>
            <select v-model="form.scope_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.scope_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Data Type *</label>
            <select v-model="form.data_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.data_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status *</label>
            <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="A">Aktif</option>
              <option value="N">Nonaktif</option>
            </select>
          </div>
        </div>
        <div>
          <label class="inline-flex items-center gap-2 text-sm">
            <input v-model="form.is_shared" type="checkbox" class="rounded border-gray-300" />
            Parameter shared (bisa dipakai banyak KPI)
          </label>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300" />
        </div>
        <div v-if="showErpMapping" class="border rounded-xl p-4 bg-indigo-50/50 space-y-3">
          <h4 class="font-semibold text-indigo-800">ERP Mapping</h4>
          <div>
            <label class="block text-sm font-medium text-gray-700">Resolver Key *</label>
            <select v-model="form.erp_mapping.resolver_key" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="">-- Pilih --</option>
              <option v-for="opt in options.resolver_keys" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <div v-if="form.errors['erp_mapping.resolver_key']" class="text-xs text-red-500 mt-1">{{ form.errors['erp_mapping.resolver_key'] }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Aggregation</label>
            <input v-model="form.erp_mapping.aggregation" class="mt-1 w-full rounded-lg border-gray-300" placeholder="sum, avg, count" />
          </div>
          <p class="text-xs text-gray-500">Filter dinamis (outlet, bulan) akan di-resolve saat evaluation — fase 2.</p>
        </div>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 rounded-b-2xl">
        <button type="button" class="px-4 py-2 rounded-lg border" @click="$emit('close')">Batal</button>
        <button type="button" :disabled="isSubmitting" class="px-4 py-2 rounded-lg bg-indigo-600 text-white disabled:opacity-50" @click="submit">
          {{ isSubmitting ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>
