<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Cari user...' },
  showFilters: { type: Boolean, default: false },
  jabatanList: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const options = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterJabatan = ref('');
const filterDivisi = ref('');
const filterOutlet = ref('');
const sortBy = ref('name');
let searchTimer = null;
let searchRequestId = 0;

function normalizeUser(user) {
  if (!user) return null;
  return {
    id: user.id,
    name: user.name || user.nama_lengkap || `User #${user.id}`,
    email: user.email || '',
    jabatan: user.jabatan?.nama_jabatan || user.jabatan || '—',
    divisi: user.divisi?.nama_divisi || user.divisi || '—',
    outlet: user.outlet?.nama_outlet || user.outlet || '—',
  };
}

function toUserOption(user) {
  const normalized = normalizeUser(user);
  if (!normalized?.id) return null;
  return {
    ...normalized,
    label: normalized.name,
  };
}

function selectedOnlyOptions() {
  return [...(props.modelValue || [])];
}

function replaceSearchResults(users) {
  const selectedIds = new Set((props.modelValue || []).map((u) => u.id));
  const fetched = users.map((u) => toUserOption(u)).filter(Boolean);
  options.value = [
    ...(props.modelValue || []),
    ...fetched.filter((u) => !selectedIds.has(u.id)),
  ];
}

function buildSearchParams(query) {
  const params = {
    q: String(query || '').trim(),
    sort_by: props.showFilters ? sortBy.value : 'name',
  };

  if (props.showFilters) {
    params.jabatan_id = filterJabatan.value || undefined;
    params.division_id = filterDivisi.value || undefined;
    params.outlet_id = filterOutlet.value || undefined;
  }

  return params;
}

function refreshSearch(query, immediate = false) {
  searchQuery.value = query ?? '';
  const params = buildSearchParams(searchQuery.value);
  const hasFilter = props.showFilters && (params.jabatan_id || params.division_id || params.outlet_id);

  if (!params.q && !hasFilter) {
    options.value = selectedOnlyOptions();
    loading.value = false;
    return;
  }

  options.value = selectedOnlyOptions();
  loading.value = true;

  clearTimeout(searchTimer);
  const requestId = ++searchRequestId;
  const delay = immediate ? 0 : 280;

  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('just-academy.api.users.search'), { params });
      if (requestId !== searchRequestId) return;
      replaceSearchResults(data.users || []);
    } catch (error) {
      if (requestId !== searchRequestId) return;
      console.error('JaUserMultiselect search failed', error);
      options.value = selectedOnlyOptions();
    } finally {
      if (requestId === searchRequestId) {
        loading.value = false;
      }
    }
  }, delay);
}

function onOpen() {
  if (searchQuery.value || (props.showFilters && (filterJabatan.value || filterDivisi.value || filterOutlet.value))) {
    refreshSearch(searchQuery.value, true);
  }
}

watch(
  () => props.modelValue,
  (selected) => {
    if (!selected?.length) return;
    const existingIds = new Set(options.value.map((u) => u.id));
    const missing = selected.filter((u) => !existingIds.has(u.id));
    if (missing.length) {
      options.value = [...options.value, ...missing];
    }
  },
  { deep: true, immediate: true },
);

watch([filterJabatan, filterDivisi, filterOutlet, sortBy], () => {
  if (!props.showFilters) return;
  refreshSearch(searchQuery.value, true);
});
</script>

<template>
  <div>
    <div v-if="showFilters" class="mb-2 grid grid-cols-2 gap-2 md:grid-cols-4">
      <select v-model="filterJabatan" :class="jaUi.select" class="!text-xs">
        <option value="">Semua jabatan</option>
        <option v-for="j in jabatanList" :key="j.id_jabatan" :value="j.id_jabatan">{{ j.nama_jabatan }}</option>
      </select>
      <select v-model="filterDivisi" :class="jaUi.select" class="!text-xs">
        <option value="">Semua divisi</option>
        <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
      </select>
      <select v-model="filterOutlet" :class="jaUi.select" class="!text-xs">
        <option value="">Semua outlet</option>
        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
      </select>
      <select v-model="sortBy" :class="jaUi.select" class="!text-xs">
        <option value="name">Urut: Nama</option>
        <option value="jabatan">Urut: Jabatan</option>
        <option value="divisi">Urut: Divisi</option>
        <option value="outlet">Urut: Outlet</option>
      </select>
    </div>

    <Multiselect
      :model-value="modelValue"
      :options="options"
      :multiple="true"
      :searchable="true"
      :internal-search="false"
      :loading="loading"
      :close-on-select="false"
      :clear-on-select="false"
      :preserve-search="true"
      :options-limit="40"
      label="label"
      track-by="id"
      :placeholder="placeholder"
      select-label=""
      deselect-label=""
      selected-label=""
      class="ja-user-multiselect"
      @update:model-value="emit('update:modelValue', $event)"
      @search-change="(q) => refreshSearch(q, false)"
      @open="onOpen"
    >
      <template #option="{ option }">
        <div class="py-0.5">
          <div class="font-medium text-slate-800">{{ option.name }}</div>
          <div class="text-xs text-slate-500">
            {{ option.jabatan || '—' }} · {{ option.divisi || '—' }} · {{ option.outlet || '—' }}
          </div>
        </div>
      </template>
      <template #noOptions>
        <span class="px-2 py-1 text-xs text-slate-500">
          {{ showFilters ? 'Ketik nama atau gunakan filter' : 'Ketik nama untuk mencari' }}
        </span>
      </template>
      <template #noResult>
        <span class="px-2 py-1 text-xs text-slate-500">Tidak ada user ditemukan</span>
      </template>
    </Multiselect>
  </div>
</template>

<style>
.ja-user-multiselect .multiselect__tags {
  min-height: 42px;
  border-radius: 0.75rem;
  border-color: rgb(226 232 240);
  padding-top: 8px;
}
.ja-user-multiselect .multiselect__content-wrapper {
  z-index: 60;
}
.ja-user-multiselect .multiselect__input,
.ja-user-multiselect .multiselect__single {
  font-size: 0.875rem;
}
.ja-user-multiselect .multiselect__option {
  padding-top: 8px;
  padding-bottom: 8px;
  line-height: 1.3;
  white-space: normal;
}
.ja-user-multiselect .multiselect__option--highlight {
  background: #6366f1;
}
.ja-user-multiselect .multiselect__tag {
  background: #6366f1;
}
</style>
