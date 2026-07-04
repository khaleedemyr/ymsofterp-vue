<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  modelValue: { type: [Number, String, null], default: '' },
  searchRoute: { type: String, required: true },
  placeholder: { type: String, default: 'Cari user...' },
  initialUser: { type: Object, default: null },
  allowEmpty: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'user-selected']);

const options = ref([]);
const loading = ref(false);
let searchTimer = null;
let searchRequestId = 0;

function normalizeUser(user) {
  if (!user?.id) return null;
  const name = user.name || user.nama_lengkap || `User #${user.id}`;
  const jabatan = user.jabatan || user.nama_jabatan || '';
  return {
    id: Number(user.id),
    name,
    jabatan,
    email: user.email || '',
    label: jabatan ? `${name} — ${jabatan}` : name,
  };
}

function selectedOnlyOptions() {
  const id = Number(props.modelValue);
  if (!id) return [];
  const existing = options.value.find((row) => row.id === id);
  if (existing) return [existing];
  if (props.initialUser) {
    const seeded = normalizeUser(props.initialUser);
    return seeded ? [seeded] : [];
  }
  return [{ id, name: `User #${id}`, jabatan: '', email: '', label: `User #${id}` }];
}

function replaceSearchResults(users) {
  const selectedIds = new Set(
    [Number(props.modelValue)].filter((id) => Number.isFinite(id) && id > 0),
  );
  const fetched = users.map((user) => normalizeUser(user)).filter(Boolean);
  options.value = [
    ...selectedOnlyOptions().filter((row) => selectedIds.has(row.id)),
    ...fetched.filter((row) => !selectedIds.has(row.id)),
  ];
}

function refreshSearch(query, immediate = false) {
  const trimmed = String(query || '').trim();
  options.value = selectedOnlyOptions();
  loading.value = true;
  clearTimeout(searchTimer);

  const requestId = ++searchRequestId;
  const delay = immediate ? 0 : 280;

  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get(route(props.searchRoute), { params: { search: trimmed } });
      if (requestId !== searchRequestId) return;
      replaceSearchResults(data.users || []);
    } catch (error) {
      if (requestId !== searchRequestId) return;
      console.error('OnboardingUserSelect search failed', error);
      options.value = selectedOnlyOptions();
    } finally {
      if (requestId === searchRequestId) {
        loading.value = false;
      }
    }
  }, delay);
}

function onOpen() {
  refreshSearch('', true);
}

const selectedOption = computed({
  get() {
    const id = Number(props.modelValue);
    if (!id) return null;
    return options.value.find((row) => row.id === id) || selectedOnlyOptions()[0] || null;
  },
  set(value) {
    if (!value?.id) {
      emit('update:modelValue', props.allowEmpty ? '' : null);
      return;
    }
    const normalized = normalizeUser(value);
    if (normalized && !options.value.some((row) => row.id === normalized.id)) {
      options.value = [normalized, ...options.value];
    }
    emit('update:modelValue', value.id);
    emit('user-selected', normalized);
  },
});

watch(
  () => [props.modelValue, props.initialUser],
  () => {
    options.value = selectedOnlyOptions();
  },
  { deep: true, immediate: true },
);
</script>

<template>
  <Multiselect
    v-model="selectedOption"
    :options="options"
    :multiple="false"
    :searchable="true"
    :internal-search="false"
    :loading="loading"
    :close-on-select="true"
    :show-labels="false"
    :allow-empty="allowEmpty"
    :preserve-search="true"
    :options-limit="40"
    label="label"
    track-by="id"
    :placeholder="placeholder"
    class="onboarding-user-select"
    @search-change="(query) => refreshSearch(query, false)"
    @open="onOpen"
  >
    <template #option="{ option }">
      <div class="py-0.5">
        <div class="font-medium text-slate-800">{{ option.name }}</div>
        <div v-if="option.jabatan || option.email" class="text-xs text-slate-500">
          {{ option.jabatan || '—' }}<span v-if="option.email"> · {{ option.email }}</span>
        </div>
      </div>
    </template>
    <template #singleLabel="{ option }">
      <span class="text-sm">{{ option.label }}</span>
    </template>
    <template #noOptions>
      <span class="px-2 py-1 text-xs text-slate-500">Ketik nama, email, atau jabatan</span>
    </template>
    <template #noResult>
      <span class="px-2 py-1 text-xs text-slate-500">Tidak ada user ditemukan</span>
    </template>
  </Multiselect>
</template>

<style>
.onboarding-user-select .multiselect__tags {
  min-height: 42px;
  border-radius: 0.5rem;
  border-color: rgb(209 213 219);
  padding-top: 8px;
}
.onboarding-user-select .multiselect__content-wrapper {
  z-index: 60;
}
.onboarding-user-select .multiselect__input,
.onboarding-user-select .multiselect__single {
  font-size: 0.875rem;
}
.onboarding-user-select .multiselect__option {
  padding-top: 8px;
  padding-bottom: 8px;
  line-height: 1.3;
  white-space: normal;
}
.onboarding-user-select .multiselect__option--highlight {
  background: #4f46e5;
}
</style>
