<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ categories: Object, filters: Object });

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const editing = ref(null);

const form = useForm({
  name: '',
  description: '',
  sort_order: 0,
  is_active: true,
});

const debounced = debounce(() => {
  router.get(route('just-academy.categories.index'), {
    search: search.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch(search, debounced);

function openCreate() {
  editing.value = null;
  form.reset();
  form.is_active = true;
  form.sort_order = 0;
  showModal.value = true;
}

function openEdit(cat) {
  editing.value = cat;
  form.name = cat.name;
  form.description = cat.description || '';
  form.sort_order = cat.sort_order ?? 0;
  form.is_active = !!cat.is_active;
  showModal.value = true;
}

function submit() {
  if (editing.value) {
    form.put(route('just-academy.categories.update', editing.value.id), {
      onSuccess: () => { showModal.value = false; },
    });
  } else {
    form.post(route('just-academy.categories.store'), {
      onSuccess: () => { showModal.value = false; form.reset(); },
    });
  }
}

function remove(cat) {
  if (!confirm(`Hapus kategori "${cat.name}"?`)) return;
  useForm({}).delete(route('just-academy.categories.destroy', cat.id));
}
</script>

<template>
  <AppLayout title="Kategori — Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Kategori Program</h1>
        <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-semibold" @click="openCreate">+ Kategori Baru</button>
      </div>

      <input v-model="search" type="text" placeholder="Cari kategori..." class="px-4 py-2 rounded-xl border max-w-md mb-4" />

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Deskripsi</th>
              <th class="px-4 py-3 text-left">Urutan</th>
              <th class="px-4 py-3 text-left">Program</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in categories.data" :key="c.id" class="border-t">
              <td class="px-4 py-3 font-medium">{{ c.name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ c.description || '—' }}</td>
              <td class="px-4 py-3">{{ c.sort_order }}</td>
              <td class="px-4 py-3">{{ c.programs_count }}</td>
              <td class="px-4 py-3">
                <span :class="c.is_active ? 'text-emerald-600' : 'text-gray-400'">{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="px-4 py-3 text-right space-x-3">
                <button type="button" class="text-indigo-600" @click="openEdit(c)">Edit</button>
                <button type="button" class="text-red-600" @click="remove(c)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!categories.data.length">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada kategori. Tambahkan kategori sebelum membuat program.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showModal = false">
      <form class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md space-y-4" @submit.prevent="submit">
        <h2 class="text-lg font-semibold">{{ editing ? 'Edit Kategori' : 'Kategori Baru' }}</h2>
        <div>
          <label class="block text-sm font-medium mb-1">Nama</label>
          <input v-model="form.name" class="w-full border rounded-xl px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="w-full border rounded-xl px-3 py-2"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Urutan</label>
          <input v-model="form.sort_order" type="number" min="0" class="w-full border rounded-xl px-3 py-2" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_active" type="checkbox" /> Aktif
        </label>
        <div class="flex gap-2 justify-end">
          <button type="button" class="px-4 py-2 rounded-xl border" @click="showModal = false">Batal</button>
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" :disabled="form.processing">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
