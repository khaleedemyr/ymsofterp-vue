<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  users: Array,
  jabatans: Array,
  divisis: Array,
  levels: Array,
  outlets: Array,
});

const tab = ref('user');
const selectedTargets = ref({
  user: [],
  jabatan: [],
  divisi: [],
  level: [],
  outlet: [],
});

const form = useForm({
  title: '',
  image: null,
  files: [],
  targets: [], // Akan diisi sebelum submit
});

function handleSubmit() {
  // Gabungkan semua target ke form.targets
  form.targets = [];
  Object.entries(selectedTargets.value).forEach(([type, arr]) => {
    arr.forEach(id => form.targets.push({ type, id }));
  });

  form.post(route('announcement.store'), {
    forceFormData: true,
  });
}
</script>

<template>
  <AppLayout title="Buat Announcement">
    <div>
      <h1 class="text-2xl font-bold mb-4">Buat Announcement</h1>
      <form @submit.prevent="handleSubmit">
        <div class="mb-4">
          <label>Judul</label>
          <input v-model="form.title" class="input" required />
        </div>
        <div class="mb-4">
          <label>Header Image</label>
          <input type="file" @change="e => form.image = e.target.files[0]" accept="image/*" />
        </div>
        <div class="mb-4">
          <label>Lampiran File (bisa multiple)</label>
          <input type="file" multiple @change="e => form.files = Array.from(e.target.files)" />
        </div>
        <div class="mb-4">
          <label>Pilih Target</label>
          <div class="flex gap-2 mb-2">
            <button v-for="t in ['user','jabatan','divisi','level','outlet']" :key="t"
              type="button"
              :class="['px-3 py-1 rounded', tab === t ? 'bg-blue-600 text-white' : 'bg-gray-200']"
              @click="tab = t"
            >{{ t.charAt(0).toUpperCase() + t.slice(1) }}</button>
          </div>
          <div v-if="tab === 'user'">
            <select v-model="selectedTargets.user" multiple class="input w-full">
              <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
            </select>
          </div>
          <div v-else-if="tab === 'jabatan'">
            <select v-model="selectedTargets.jabatan" multiple class="input w-full">
              <option v-for="j in props.jabatans" :key="j.id_jabatan" :value="j.id_jabatan">{{ j.nama_jabatan }}</option>
            </select>
          </div>
          <div v-else-if="tab === 'divisi'">
            <select v-model="selectedTargets.divisi" multiple class="input w-full">
              <option v-for="d in props.divisis" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
            </select>
          </div>
          <div v-else-if="tab === 'level'">
            <select v-model="selectedTargets.level" multiple class="input w-full">
              <option v-for="l in props.levels" :key="l.id" :value="l.id">{{ l.nama_level }}</option>
            </select>
          </div>
          <div v-else-if="tab === 'outlet'">
            <select v-model="selectedTargets.outlet" multiple class="input w-full">
              <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Kirim</button>
      </form>
    </div>
  </AppLayout>
</template>
