<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import JaUserMultiselect from '@/Components/JustAcademy/JaUserMultiselect.vue';
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

function mapUserFromParticipant(p) {
  const user = p?.user;
  if (!user) return null;
  return {
    id: user.id,
    name: user.nama_lengkap || user.name,
    email: user.email,
    jabatan: user.jabatan?.nama_jabatan || '—',
    divisi: user.divisi?.nama_divisi || '—',
    outlet: user.outlet?.nama_outlet || '—',
    label: user.nama_lengkap || user.name,
  };
}

function mapUserFromTrainer(t) {
  const user = t?.user;
  if (!user) return null;
  return {
    id: user.id,
    name: user.nama_lengkap || user.name,
    email: user.email,
    jabatan: user.jabatan?.nama_jabatan || '—',
    divisi: user.divisi?.nama_divisi || '—',
    outlet: user.outlet?.nama_outlet || '—',
    label: user.nama_lengkap || user.name,
  };
}

const selectedParticipants = ref(
  (props.schedule?.participants || []).map(mapUserFromParticipant).filter(Boolean),
);
const selectedInternalTrainers = ref(
  (props.schedule?.trainers || [])
    .filter((t) => (t.trainer_type || 'internal') === 'internal' && t.user)
    .map(mapUserFromTrainer)
    .filter(Boolean),
);

const externalTrainerNames = ref(
  (() => {
    const names = (props.schedule?.trainers || [])
      .filter((t) => t.trainer_type === 'external' && t.external_name)
      .map((t) => t.external_name);
    return names.length ? names : [''];
  })(),
);

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
          <JaUserMultiselect
            v-model="selectedParticipants"
            :jabatan-list="jabatanList"
            :divisions="divisions"
            :outlets="outlets"
            show-filters
            placeholder="Cari nama, jabatan, divisi, atau outlet..."
          />
          <p class="mt-1 text-xs text-slate-500">Filter by jabatan/divisi/outlet, atau ketik nama untuk mencari.</p>
        </div>

        <div>
          <label :class="jaUi.label">Trainer internal</label>
          <JaUserMultiselect
            v-model="selectedInternalTrainers"
            placeholder="Cari nama trainer internal..."
          />
          <p class="mt-1 text-xs text-slate-500">Ketik nama trainer untuk mencari.</p>
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
