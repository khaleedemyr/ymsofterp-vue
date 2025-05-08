<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  users: Array,
  jabatans: Array,
  divisis: Array,
  levels: Array,
  outlets: Array,
  show: Boolean,
});
const emit = defineEmits(['close', 'success']);

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
  content: '',
  image: null,
  files: [],
  targets: [],
});

const loading = ref(false);
const userSearch = ref('');
const jabatanSearch = ref('');
const divisiSearch = ref('');
const levelSearch = ref('');
const outletSearch = ref('');

const filteredUsers = computed(() => {
  if (!userSearch.value) return props.users;
  return props.users.filter(u =>
    u.nama_lengkap.toLowerCase().includes(userSearch.value.toLowerCase())
  );
});
const filteredJabatans = computed(() => {
  if (!jabatanSearch.value) return props.jabatans;
  return props.jabatans.filter(j =>
    j.nama_jabatan.toLowerCase().includes(jabatanSearch.value.toLowerCase())
  );
});
const filteredDivisis = computed(() => {
  if (!divisiSearch.value) return props.divisis;
  return props.divisis.filter(d =>
    d.nama_divisi.toLowerCase().includes(divisiSearch.value.toLowerCase())
  );
});
const filteredLevels = computed(() => {
  if (!levelSearch.value) return props.levels;
  return props.levels.filter(l =>
    l.nama_level.toLowerCase().includes(levelSearch.value.toLowerCase())
  );
});
const filteredOutlets = computed(() => {
  if (!outletSearch.value) return props.outlets;
  return props.outlets.filter(o =>
    o.nama_outlet.toLowerCase().includes(outletSearch.value.toLowerCase())
  );
});

function handleSubmit() {
  form.targets = [];
  Object.entries(selectedTargets.value).forEach(([type, arr]) => {
    arr.forEach(id => form.targets.push({ type, id }));
  });

  loading.value = true;
  form.post(route('announcement.store'), {
    forceFormData: true,
    onSuccess: () => {
      loading.value = false;
      emit('success');
      emit('close');
    },
    onError: () => {
      loading.value = false;
    }
  });
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-blue-100 relative">
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-blue-800">Buat Announcement</h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-blue-600 text-2xl">&times;</button>
      </div>

      <!-- Error -->
      <div v-if="Object.keys(form.errors).length" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
        <ul>
          <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
        </ul>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit">
        <div class="mb-4">
          <label class="block font-medium mb-1">Judul</label>
          <input v-model="form.title" class="input w-full" required />
        </div>
        <div class="mb-4">
          <label class="block font-medium mb-1">Isi Konten</label>
          <textarea v-model="form.content" class="input w-full" rows="4" required />
        </div>
        <div class="mb-4">
          <label class="block font-medium mb-1">Header Image</label>
          <input type="file" @change="e => form.image = e.target.files[0]" accept="image/*" class="input w-full" />
        </div>
        <div class="mb-4">
          <label class="block font-medium mb-1">Lampiran File (bisa multiple)</label>
          <input type="file" multiple @change="e => form.files = Array.from(e.target.files)" class="input w-full" />
        </div>
        <div class="mb-4">
          <label class="block font-medium mb-1">Pilih Target</label>
          <div class="flex gap-2 mb-2">
            <button v-for="t in ['user','jabatan','divisi','level','outlet']" :key="t"
              type="button"
              :class="['px-3 py-1 rounded', tab === t ? 'bg-blue-600 text-white' : 'bg-gray-200']"
              @click="tab = t"
            >{{ t.charAt(0).toUpperCase() + t.slice(1) }}</button>
          </div>
          <div v-if="tab === 'user'">
            <input
              v-model="userSearch"
              type="text"
              placeholder="Cari user..."
              class="input w-full mb-2"
            />
            <div class="border rounded mb-2 h-32 overflow-y-auto">
              <div v-for="u in filteredUsers" :key="u.id" class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
                @click="!selectedTargets.user.includes(u.id) && selectedTargets.user.push(u.id)">
                <input type="checkbox" :checked="selectedTargets.user.includes(u.id)" class="mr-2" />
                <span>{{ u.nama_lengkap }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="uid in selectedTargets.user"
                :key="uid"
                class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
              >
                {{ props.users.find(u => u.id === uid)?.nama_lengkap || uid }}
                <button
                  type="button"
                  class="ml-1 text-red-500 hover:text-red-700"
                  @click="selectedTargets.user = selectedTargets.user.filter(id => id !== uid)"
                  title="Hapus"
                >×</button>
              </span>
            </div>
          </div>
          <div v-else-if="tab === 'jabatan'">
            <input
              v-model="jabatanSearch"
              type="text"
              placeholder="Cari jabatan..."
              class="input w-full mb-2"
            />
            <div class="border rounded mb-2 h-32 overflow-y-auto">
              <div v-for="j in filteredJabatans" :key="j.id_jabatan" class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
                @click="!selectedTargets.jabatan.includes(j.id_jabatan) && selectedTargets.jabatan.push(j.id_jabatan)">
                <input type="checkbox" :checked="selectedTargets.jabatan.includes(j.id_jabatan)" class="mr-2" />
                <span>{{ j.nama_jabatan }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="jid in selectedTargets.jabatan"
                :key="jid"
                class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
              >
                {{ props.jabatans.find(j => j.id_jabatan === jid)?.nama_jabatan || jid }}
                <button
                  type="button"
                  class="ml-1 text-red-500 hover:text-red-700"
                  @click="selectedTargets.jabatan = selectedTargets.jabatan.filter(id => id !== jid)"
                  title="Hapus"
                >×</button>
              </span>
            </div>
          </div>
          <div v-else-if="tab === 'divisi'">
            <input
              v-model="divisiSearch"
              type="text"
              placeholder="Cari divisi..."
              class="input w-full mb-2"
            />
            <div class="border rounded mb-2 h-32 overflow-y-auto">
              <div v-for="d in filteredDivisis" :key="d.id" class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
                @click="!selectedTargets.divisi.includes(d.id) && selectedTargets.divisi.push(d.id)">
                <input type="checkbox" :checked="selectedTargets.divisi.includes(d.id)" class="mr-2" />
                <span>{{ d.nama_divisi }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="did in selectedTargets.divisi"
                :key="did"
                class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
              >
                {{ props.divisis.find(d => d.id === did)?.nama_divisi || did }}
                <button
                  type="button"
                  class="ml-1 text-red-500 hover:text-red-700"
                  @click="selectedTargets.divisi = selectedTargets.divisi.filter(id => id !== did)"
                  title="Hapus"
                >×</button>
              </span>
            </div>
          </div>
          <div v-else-if="tab === 'level'">
            <input
              v-model="levelSearch"
              type="text"
              placeholder="Cari level..."
              class="input w-full mb-2"
            />
            <div class="border rounded mb-2 h-32 overflow-y-auto">
              <div v-for="l in filteredLevels" :key="l.id" class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
                @click="!selectedTargets.level.includes(l.id) && selectedTargets.level.push(l.id)">
                <input type="checkbox" :checked="selectedTargets.level.includes(l.id)" class="mr-2" />
                <span>{{ l.nama_level }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="lid in selectedTargets.level"
                :key="lid"
                class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
              >
                {{ props.levels.find(l => l.id === lid)?.nama_level || lid }}
                <button
                  type="button"
                  class="ml-1 text-red-500 hover:text-red-700"
                  @click="selectedTargets.level = selectedTargets.level.filter(id => id !== lid)"
                  title="Hapus"
                >×</button>
              </span>
            </div>
          </div>
          <div v-else-if="tab === 'outlet'">
            <input
              v-model="outletSearch"
              type="text"
              placeholder="Cari outlet..."
              class="input w-full mb-2"
            />
            <div class="border rounded mb-2 h-32 overflow-y-auto">
              <div v-for="o in filteredOutlets" :key="o.id_outlet" class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
                @click="!selectedTargets.outlet.includes(o.id_outlet) && selectedTargets.outlet.push(o.id_outlet)">
                <input type="checkbox" :checked="selectedTargets.outlet.includes(o.id_outlet)" class="mr-2" />
                <span>{{ o.nama_outlet }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="oid in selectedTargets.outlet"
                :key="oid"
                class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
              >
                {{ props.outlets.find(o => o.id_outlet === oid)?.nama_outlet || oid }}
                <button
                  type="button"
                  class="ml-1 text-red-500 hover:text-red-700"
                  @click="selectedTargets.outlet = selectedTargets.outlet.filter(id => id !== oid)"
                  title="Hapus"
                >×</button>
              </span>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-2">
          <button type="button" @click="$emit('close')" :disabled="loading"
            class="px-4 py-2 rounded font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition disabled:opacity-60">
            Cancel
          </button>
          <button type="submit" :disabled="loading"
            class="px-4 py-2 rounded font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-60">
            <span v-if="loading">Menyimpan...</span>
            <span v-else>Kirim</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
