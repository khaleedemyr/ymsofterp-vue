<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ programs: Object, filters: Object });
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const debounced = debounce(() => {
  router.get(route('just-academy.programs.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch(status, debounced);
</script>

<template>
  <AppLayout title="Programs — Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Programs</h1>
        <Link :href="route('just-academy.programs.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-semibold">+ Program Baru</Link>
      </div>

      <div class="flex gap-3 mb-4">
        <input v-model="search" type="text" placeholder="Cari program..." class="px-4 py-2 rounded-xl border max-w-md" @input="debounced" />
        <select v-model="status" class="px-3 py-2 rounded-xl border">
          <option value="">Semua status</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Judul</th>
              <th class="px-4 py-3 text-left">Kategori</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in programs.data" :key="p.id" class="border-t">
              <td class="px-4 py-3">{{ p.code || '-' }}</td>
              <td class="px-4 py-3 font-medium">{{ p.title }}</td>
              <td class="px-4 py-3">{{ p.category?.name || '-' }}</td>
              <td class="px-4 py-3 capitalize">{{ p.status }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('just-academy.programs.edit', p.id)" class="text-indigo-600">Edit</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
