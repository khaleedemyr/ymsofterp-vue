<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ schedules: Object, filters: Object });
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const debounced = debounce(() => {
  router.get(route('just-academy.schedules.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch(status, debounced);
</script>

<template>
  <AppLayout title="Schedules — Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Jadwal Training</h1>
        <Link :href="route('just-academy.schedules.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-semibold">+ Jadwal Baru</Link>
      </div>

      <div class="flex gap-3 mb-4">
        <input v-model="search" type="text" placeholder="Cari jadwal..." class="px-4 py-2 rounded-xl border max-w-md" @input="debounced" />
        <select v-model="status" class="px-3 py-2 rounded-xl border">
          <option value="">Semua status</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Judul</th>
              <th class="px-4 py-3 text-left">Program</th>
              <th class="px-4 py-3 text-left">Waktu</th>
              <th class="px-4 py-3 text-left">Peserta</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in schedules.data" :key="s.id" class="border-t">
              <td class="px-4 py-3 font-medium">{{ s.title }}</td>
              <td class="px-4 py-3">{{ s.program?.title }}</td>
              <td class="px-4 py-3">{{ s.start_at }}</td>
              <td class="px-4 py-3">{{ s.participants_count }}</td>
              <td class="px-4 py-3 capitalize">{{ s.status }}</td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('just-academy.schedules.show', s.id)" class="text-indigo-600">Detail</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
