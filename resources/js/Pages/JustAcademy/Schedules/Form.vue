<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedule: Object,
  programs: Array,
  outlets: Array,
  regions: Array,
  jabatanList: Array,
  divisions: Array,
  initialStartAt: String,
  initialEndAt: String,
});

function userFromRelation(user) {
  if (!user) return null;
  return {
    id: user.id,
    name: user.nama_lengkap || user.name,
    nama_lengkap: user.nama_lengkap || user.name,
    email: user.email,
    jabatan: user.jabatan?.nama_jabatan || user.jabatan,
    divisi: user.divisi?.nama_divisi || user.divisi,
    outlet: user.outlet?.nama_outlet || user.outlet,
  };
}

function toUserOption(user) {
  const normalized = userFromRelation(user) || user;
  if (!normalized?.id) return null;

  const name = normalized.name || normalized.nama_lengkap || `User #${normalized.id}`;
  const jabatan = normalized.jabatan || '—';
  const divisi = normalized.divisi || '—';
  const outlet = normalized.outlet || '—';

  return {
    id: normalized.id,
    name,
    email: normalized.email || '',
    jabatan,
    divisi,
    outlet,
    label: name,
    meta: [jabatan, divisi, outlet].filter((v) => v && v !== '—').join(' · '),
  };
}

function initialParticipants() {
  return (props.schedule?.participants || [])
    .map((p) => toUserOption(p.user))
    .filter(Boolean);
}

function initialInternalTrainers() {
  return (props.schedule?.trainers || [])
    .filter((t) => (t.trainer_type || 'internal') === 'internal' && t.user)
    .map((t) => toUserOption(t.user))
    .filter(Boolean);
}

function initialExternalTrainers() {
  const names = (props.schedule?.trainers || [])
    .filter((t) => t.trainer_type === 'external' && t.external_name)
    .map((t) => t.external_name);
  return names.length ? names : [''];
}

const selectedParticipants = ref(initialParticipants());
const selectedInternalTrainers = ref(initialInternalTrainers());
const externalTrainerNames = ref(initialExternalTrainers());
const participantOptions = ref([]);
const trainerOptions = ref([]);
const participantSearchLoading = ref(false);
const trainerSearchLoading = ref(false);
const participantSearchQuery = ref('');
const trainerSearchQuery = ref('');

const participantFilterJabatan = ref('');
const participantFilterDivisi = ref('');
const participantFilterOutlet = ref('');
const participantSortBy = ref('name');

const form = useForm({
  program_id: props.schedule?.program_id || '',
  title: props.schedule?.title || '',
  start_at: props.schedule?.start_at?.slice(0, 16) || props.initialStartAt || '',
  end_at: props.schedule?.end_at?.slice(0, 16) || props.initialEndAt || '',
  location: props.schedule?.location || '',
  outlet_id: props.schedule?.outlet_id || '',
  region_id: props.schedule?.region_id || '',
  capacity: props.schedule?.capacity || '',
  status: props.schedule?.status || 'draft',
  notes: props.schedule?.notes || '',
  participant_ids: [],
  internal_trainer_ids: [],
  external_trainers: [],
});

function mergeUserOptions(target, users) {
  const map = new Map(target.map((u) => [u.id, u]));
  users.forEach((user) => {
    const option = toUserOption(user);
    if (option) map.set(option.id, option);
  });
  return Array.from(map.values());
}

async function fetchUsers({ query, targetRef, loadingRef, extraParams = {} }) {
  const q = String(query || '').trim();
  const hasFilter = extraParams.jabatan_id || extraParams.division_id || extraParams.outlet_id;
  if (q.length < 1 && !hasFilter) {
    return;
  }

  loadingRef.value = true;
  try {
    const { data } = await axios.get(route('just-academy.api.users.search'), {
      params: { q, ...extraParams },
    });
    targetRef.value = mergeUserOptions(targetRef.value, data.users || []);
  } finally {
    loadingRef.value = false;
  }
}

function searchParticipants(query) {
  participantSearchQuery.value = query || '';
  return fetchUsers({
    query,
    targetRef: participantOptions,
    loadingRef: participantSearchLoading,
    extraParams: {
      jabatan_id: participantFilterJabatan.value || undefined,
      division_id: participantFilterDivisi.value || undefined,
      outlet_id: participantFilterOutlet.value || undefined,
      sort_by: participantSortBy.value,
    },
  });
}

function searchTrainers(query) {
  trainerSearchQuery.value = query || '';
  return fetchUsers({
    query,
    targetRef: trainerOptions,
    loadingRef: trainerSearchLoading,
    extraParams: { sort_by: 'name' },
  });
}

function refreshParticipantOptions() {
  return searchParticipants(participantSearchQuery.value);
}

function addExternalTrainer() {
  externalTrainerNames.value.push('');
}

function removeExternalTrainer(index) {
  if (externalTrainerNames.value.length <= 1) {
    externalTrainerNames.value[0] = '';
    return;
  }
  externalTrainerNames.value.splice(index, 1);
}

watch(
  selectedParticipants,
  (list) => {
    participantOptions.value = mergeUserOptions(participantOptions.value, list);
  },
  { immediate: true, deep: true },
);

watch(
  selectedInternalTrainers,
  (list) => {
    trainerOptions.value = mergeUserOptions(trainerOptions.value, list);
  },
  { immediate: true, deep: true },
);

watch([participantFilterJabatan, participantFilterDivisi, participantFilterOutlet, participantSortBy], () => {
  refreshParticipantOptions();
});

function submit() {
  form.participant_ids = selectedParticipants.value.map((u) => u.id);
  form.internal_trainer_ids = selectedInternalTrainers.value.map((u) => u.id);
  form.external_trainers = externalTrainerNames.value.map((n) => n.trim()).filter(Boolean);

  const opts = { onError: (e) => jaFormErrors(e) };
  if (props.schedule) {
    form.put(route('just-academy.schedules.update', props.schedule.id), opts);
  } else {
    form.post(route('just-academy.schedules.store'), opts);
  }
}
</script>

<template>
  <JaLayout
    :title="schedule ? 'Edit Training Plan' : 'Training Plan Baru'"
    subtitle="Atur waktu, lokasi, program, peserta, dan trainer"
    icon="fa-solid fa-calendar-days"
    narrow
  >
    <form :class="[jaUi.card, jaUi.cardBody, 'space-y-4']" @submit.prevent="submit">
      <div>
        <label :class="jaUi.label">Program</label>
        <select v-model="form.program_id" :class="jaUi.input" required>
          <option value="">— Pilih program —</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
      </div>
      <div>
        <label :class="jaUi.label">Judul training plan</label>
        <input v-model="form.title" :class="jaUi.input" required />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label :class="jaUi.label">Mulai</label>
          <input v-model="form.start_at" type="datetime-local" :class="jaUi.input" required />
        </div>
        <div>
          <label :class="jaUi.label">Selesai</label>
          <input v-model="form.end_at" type="datetime-local" :class="jaUi.input" required />
        </div>
      </div>
      <div>
        <label :class="jaUi.label">Lokasi</label>
        <input v-model="form.location" :class="jaUi.input" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label :class="jaUi.label">Outlet</label>
          <select v-model="form.outlet_id" :class="jaUi.input">
            <option value="">— Opsional —</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
          </select>
        </div>
        <div>
          <label :class="jaUi.label">Region</label>
          <select v-model="form.region_id" :class="jaUi.input">
            <option value="">— Opsional —</option>
            <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label :class="jaUi.label">Kapasitas</label>
          <input v-model="form.capacity" type="number" :class="jaUi.input" />
        </div>
        <div>
          <label :class="jaUi.label">Status</label>
          <select v-model="form.status" :class="jaUi.input">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-4">
        <h3 class="text-sm font-semibold text-slate-800">Peserta & Trainer</h3>

        <div>
          <label :class="jaUi.label">Peserta</label>
          <div class="mb-2 grid grid-cols-2 gap-2 md:grid-cols-4">
            <select v-model="participantFilterJabatan" :class="jaUi.select" class="!text-xs">
              <option value="">Semua jabatan</option>
              <option v-for="j in jabatanList" :key="j.id_jabatan" :value="j.id_jabatan">{{ j.nama_jabatan }}</option>
            </select>
            <select v-model="participantFilterDivisi" :class="jaUi.select" class="!text-xs">
              <option value="">Semua divisi</option>
              <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
            </select>
            <select v-model="participantFilterOutlet" :class="jaUi.select" class="!text-xs">
              <option value="">Semua outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
            <select v-model="participantSortBy" :class="jaUi.select" class="!text-xs">
              <option value="name">Urut: Nama</option>
              <option value="jabatan">Urut: Jabatan</option>
              <option value="divisi">Urut: Divisi</option>
              <option value="outlet">Urut: Outlet</option>
            </select>
          </div>
          <Multiselect
            v-model="selectedParticipants"
            :options="participantOptions"
            :multiple="true"
            :searchable="true"
            :internal-search="false"
            :loading="participantSearchLoading"
            :close-on-select="false"
            :clear-on-select="false"
            label="label"
            track-by="id"
            placeholder="Cari nama, jabatan, divisi, atau outlet..."
            select-label=""
            deselect-label=""
            selected-label=""
            class="ja-multiselect"
            @search-change="searchParticipants"
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
              <span class="px-2 py-1 text-xs text-slate-500">Ketik nama atau pilih filter di atas</span>
            </template>
            <template #noResult>
              <span class="px-2 py-1 text-xs text-slate-500">Tidak ada user ditemukan</span>
            </template>
          </Multiselect>
          <p class="mt-1 text-xs text-slate-500">Filter by jabatan/divisi/outlet, atau ketik nama untuk mencari.</p>
        </div>

        <div>
          <label :class="jaUi.label">Trainer internal</label>
          <Multiselect
            v-model="selectedInternalTrainers"
            :options="trainerOptions"
            :multiple="true"
            :searchable="true"
            :internal-search="false"
            :loading="trainerSearchLoading"
            :close-on-select="false"
            :clear-on-select="false"
            label="label"
            track-by="id"
            placeholder="Cari nama trainer internal..."
            select-label=""
            deselect-label=""
            selected-label=""
            class="ja-multiselect"
            @search-change="searchTrainers"
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
              <span class="px-2 py-1 text-xs text-slate-500">Ketik nama trainer untuk mencari</span>
            </template>
            <template #noResult>
              <span class="px-2 py-1 text-xs text-slate-500">Tidak ada trainer ditemukan</span>
            </template>
          </Multiselect>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between">
            <label :class="jaUi.label" class="!mb-0">Trainer eksternal</label>
            <button type="button" :class="jaUi.btnLink" class="!text-xs" @click="addExternalTrainer">
              <i class="fa-solid fa-plus mr-1" /> Tambah
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="(_, index) in externalTrainerNames" :key="index" class="flex gap-2">
              <input
                v-model="externalTrainerNames[index]"
                type="text"
                :class="jaUi.input"
                placeholder="Nama trainer eksternal"
              />
              <button
                type="button"
                :class="jaUi.btnSecondary"
                class="!px-3 shrink-0"
                @click="removeExternalTrainer(index)"
              >
                <i class="fa-solid fa-minus" />
              </button>
            </div>
          </div>
          <p class="mt-1 text-xs text-slate-500">Isi nama trainer jika bukan karyawan internal.</p>
        </div>
      </div>

      <div>
        <label :class="jaUi.label">Catatan</label>
        <textarea v-model="form.notes" rows="2" :class="jaUi.input" />
      </div>
      <button type="submit" :class="jaUi.btnPrimary" :disabled="form.processing">Simpan</button>
    </form>
  </JaLayout>
</template>

<style>
.ja-multiselect .multiselect__tags {
  min-height: 42px;
  border-radius: 0.75rem;
  border-color: rgb(226 232 240);
  padding-top: 8px;
}
.ja-multiselect .multiselect__input,
.ja-multiselect .multiselect__single {
  font-size: 0.875rem;
}
.ja-multiselect .multiselect__option {
  padding-top: 8px;
  padding-bottom: 8px;
  line-height: 1.3;
  white-space: normal;
}
.ja-multiselect .multiselect__option--highlight {
  background: #6366f1;
}
.ja-multiselect .multiselect__tag {
  background: #6366f1;
}
</style>
