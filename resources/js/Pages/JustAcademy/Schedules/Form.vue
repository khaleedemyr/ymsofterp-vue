<script setup>
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({ schedule: Object, programs: Array, outlets: Array, regions: Array });

const form = useForm({
  program_id: props.schedule?.program_id || '',
  title: props.schedule?.title || '',
  start_at: props.schedule?.start_at?.slice(0, 16) || '',
  end_at: props.schedule?.end_at?.slice(0, 16) || '',
  location: props.schedule?.location || '',
  outlet_id: props.schedule?.outlet_id || '',
  region_id: props.schedule?.region_id || '',
  capacity: props.schedule?.capacity || '',
  status: props.schedule?.status || 'draft',
  notes: props.schedule?.notes || '',
});

function submit() {
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
    :title="schedule ? 'Edit Jadwal' : 'Jadwal Baru'"
    subtitle="Atur waktu, lokasi, dan program training"
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
        <label :class="jaUi.label">Judul jadwal</label>
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
      <div>
        <label :class="jaUi.label">Catatan</label>
        <textarea v-model="form.notes" rows="2" :class="jaUi.input" />
      </div>
      <button type="submit" :class="jaUi.btnPrimary" :disabled="form.processing">Simpan</button>
    </form>
  </JaLayout>
</template>
