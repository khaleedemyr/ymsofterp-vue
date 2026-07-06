<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  mode: String,
  setting: Object,
  categories: Array,
  regions: Array,
  outlets: Array,
  users: Array,
});

const isEdit = computed(() => props.mode === 'edit');

const form = ref({
  category_id: props.setting?.category_id ? String(props.setting.category_id) : '',
  name: props.setting?.name || '',
  status: props.setting?.status || 'A',
  region_ids: (props.setting?.region_ids || []).map(String),
  outlet_ids: (props.setting?.outlet_ids || []).map(String),
  user_ids: (props.setting?.user_ids || []).map(String),
  primary_user_id: props.setting?.primary_user_id ? String(props.setting.primary_user_id) : '',
});

const errors = ref({});
const saving = ref(false);

const regionOptions = computed(() =>
  (props.regions || []).map((r) => ({ value: String(r.id), label: `${r.name}${r.code ? ` (${r.code})` : ''}` }))
);

const outletOptions = computed(() =>
  (props.outlets || []).map((o) => ({ value: String(o.id_outlet), label: o.nama_outlet }))
);

const userOptions = computed(() =>
  (props.users || []).map((u) => ({ value: String(u.id), label: u.nama_lengkap }))
);

const selectedRegions = computed({
  get() {
    return regionOptions.value.filter((o) => form.value.region_ids.includes(o.value));
  },
  set(items) {
    form.value.region_ids = items.map((i) => i.value);
  },
});

const selectedOutlets = computed({
  get() {
    return outletOptions.value.filter((o) => form.value.outlet_ids.includes(o.value));
  },
  set(items) {
    form.value.outlet_ids = items.map((i) => i.value);
  },
});

const selectedUsers = computed({
  get() {
    return userOptions.value.filter((o) => form.value.user_ids.includes(o.value));
  },
  set(items) {
    form.value.user_ids = items.map((i) => i.value);
    if (!form.value.user_ids.includes(form.value.primary_user_id)) {
      form.value.primary_user_id = form.value.user_ids[0] || '';
    }
  },
});

watch(
  () => form.value.user_ids,
  (ids) => {
    if (!ids.includes(form.value.primary_user_id)) {
      form.value.primary_user_id = ids[0] || '';
    }
  },
  { deep: true }
);

function payload() {
  return {
    category_id: Number(form.value.category_id),
    name: form.value.name || null,
    status: form.value.status,
    region_ids: form.value.region_ids.map(Number),
    outlet_ids: form.value.outlet_ids.map(Number),
    user_ids: form.value.user_ids.map(Number),
    primary_user_id: form.value.primary_user_id ? Number(form.value.primary_user_id) : null,
  };
}

function submit() {
  saving.value = true;
  errors.value = {};
  const data = payload();

  if (isEdit.value) {
    router.put(route('tickets.team-settings.update', props.setting.id), data, options());
  } else {
    router.post(route('tickets.team-settings.store'), data, options());
  }
}

function options() {
  return {
    onError: (e) => {
      errors.value = e;
    },
    onFinish: () => {
      saving.value = false;
    },
  };
}

function back() {
  router.visit(route('tickets.team-settings.index'));
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit Team Setting' : 'Tambah Team Setting'">
    <div class="w-full max-w-3xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">
            {{ isEdit ? 'Edit Setting Team' : 'Tambah Setting Team' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Pilih category, scope region/outlet (opsional), dan anggota tim.
          </p>
        </div>
        <button class="px-3 py-2 bg-gray-100 rounded-xl" @click="back">Kembali</button>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-5">
        <div>
          <label class="block text-sm font-medium mb-1">Category *</label>
          <select v-model="form.category_id" class="w-full border rounded-xl px-3 py-2">
            <option value="" disabled>Pilih category</option>
            <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <div v-if="errors.category_id" class="text-sm text-red-600 mt-1">{{ errors.category_id }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Nama setting (opsional)</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full border rounded-xl px-3 py-2"
            placeholder="Contoh: Team Maintenance Jakarta"
          />
          <div v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Region (multi, opsional)</label>
          <Multiselect
            v-model="selectedRegions"
            :options="regionOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            label="label"
            track-by="value"
            placeholder="Pilih region..."
          />
          <p class="text-xs text-gray-500 mt-1">Kosongkan region & outlet = berlaku untuk semua outlet pada category ini.</p>
          <div v-if="errors.region_ids" class="text-sm text-red-600 mt-1">{{ errors.region_ids }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Outlet (multi, opsional)</label>
          <Multiselect
            v-model="selectedOutlets"
            :options="outletOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            label="label"
            track-by="value"
            placeholder="Pilih outlet..."
          />
          <div v-if="errors.outlet_ids" class="text-sm text-red-600 mt-1">{{ errors.outlet_ids }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Anggota tim (user) *</label>
          <Multiselect
            v-model="selectedUsers"
            :options="userOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            label="label"
            track-by="value"
            placeholder="Pilih user..."
          />
          <div v-if="errors.user_ids" class="text-sm text-red-600 mt-1">{{ errors.user_ids }}</div>
        </div>

        <div v-if="form.user_ids.length">
          <label class="block text-sm font-medium mb-2">PIC utama</label>
          <div class="space-y-2">
            <label
              v-for="uid in form.user_ids"
              :key="uid"
              class="flex items-center gap-2 text-sm"
            >
              <input v-model="form.primary_user_id" type="radio" :value="uid" />
              <span>{{ userOptions.find((u) => u.value === uid)?.label }}</span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
            <option value="A">Aktif</option>
            <option value="N">Nonaktif</option>
          </select>
        </div>

        <button
          :disabled="saving"
          class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-semibold disabled:opacity-50 hover:bg-indigo-700"
          @click="submit"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
:deep(.multiselect) {
  min-height: 42px;
}
:deep(.multiselect__tags) {
  border-radius: 0.75rem;
  border-color: rgb(209 213 219);
  min-height: 42px;
}
</style>
