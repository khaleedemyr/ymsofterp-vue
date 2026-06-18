<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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
  if (props.schedule) {
    form.put(route('just-academy.schedules.update', props.schedule.id));
  } else {
    form.post(route('just-academy.schedules.store'));
  }
}
</script>

<template>
  <AppLayout :title="schedule ? 'Edit Jadwal' : 'Jadwal Baru'">
    <div class="max-w-3xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6">{{ schedule ? 'Edit Jadwal' : 'Jadwal Baru' }}</h1>
      <form class="bg-white rounded-2xl shadow p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium mb-1">Program</label>
          <select v-model="form.program_id" class="w-full border rounded-xl px-3 py-2" required>
            <option value="">— Pilih program —</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Judul jadwal</label>
          <input v-model="form.title" class="w-full border rounded-xl px-3 py-2" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Mulai</label>
            <input v-model="form.start_at" type="datetime-local" class="w-full border rounded-xl px-3 py-2" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Selesai</label>
            <input v-model="form.end_at" type="datetime-local" class="w-full border rounded-xl px-3 py-2" required />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Lokasi</label>
          <input v-model="form.location" class="w-full border rounded-xl px-3 py-2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Outlet</label>
            <select v-model="form.outlet_id" class="w-full border rounded-xl px-3 py-2">
              <option value="">— Opsional —</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Region</label>
            <select v-model="form.region_id" class="w-full border rounded-xl px-3 py-2">
              <option value="">— Opsional —</option>
              <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Kapasitas</label>
            <input v-model="form.capacity" type="number" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Catatan</label>
          <textarea v-model="form.notes" rows="2" class="w-full border rounded-xl px-3 py-2"></textarea>
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" :disabled="form.processing">Simpan</button>
      </form>
    </div>
  </AppLayout>
</template>
