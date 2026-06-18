<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ materials: Object, filters: Object });
const search = ref(props.filters?.search || '');

const debounced = debounce(() => {
  router.get(route('just-academy.materials.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
}, 400);

watch(search, debounced);

function remove(id) {
  if (!confirm('Hapus materi?')) return;
  router.delete(route('just-academy.materials.destroy', id));
}
</script>

<template>
  <AppLayout title="Materi — Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Pustaka Materi</h1>
        <Link :href="route('just-academy.materials.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-semibold">+ Materi Baru</Link>
      </div>
      <input v-model="search" type="text" placeholder="Cari materi..." class="px-4 py-2 rounded-xl border max-w-md mb-4" @input="debounced" />
      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Judul</th>
              <th class="px-4 py-3 text-left">Tipe</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in materials.data" :key="m.id" class="border-t">
              <td class="px-4 py-3 font-medium">{{ m.title }}</td>
              <td class="px-4 py-3 uppercase text-xs">{{ m.type }}</td>
              <td class="px-4 py-3">{{ m.is_active ? 'Aktif' : 'Nonaktif' }}</td>
              <td class="px-4 py-3 text-right space-x-3">
                <Link :href="route('just-academy.materials.edit', m.id)" class="text-indigo-600">Edit</Link>
                <button type="button" class="text-red-600" @click="remove(m.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
