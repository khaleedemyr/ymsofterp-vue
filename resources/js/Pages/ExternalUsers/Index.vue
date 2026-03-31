<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  users: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');

watch([search, status], () => {
  router.get(
    route('external-report-users.index'),
    {
      search: search.value,
      status: status.value,
    },
    { preserveState: true, replace: true }
  );
});
</script>

<template>
  <Head title="List User External" />
  <div class="w-full py-8 px-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">List User External Report</h1>
      <Link
        :href="route('external-report-users.create')"
        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
      >
        + Input User External
      </Link>
    </div>

    <div class="bg-white rounded-2xl shadow p-4 mb-4 flex gap-3">
      <input
        v-model="search"
        type="text"
        placeholder="Cari nama/email/outlet..."
        class="flex-1 rounded-lg border-gray-300"
      />
      <select v-model="status" class="rounded-lg border-gray-300">
        <option value="A">Aktif</option>
        <option value="N">Non Aktif</option>
        <option value="all">Semua</option>
      </select>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-x-auto">
      <table class="min-w-full">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold">Nama</th>
            <th class="px-4 py-3 text-left text-xs font-semibold">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold">Outlet</th>
            <th class="px-4 py-3 text-left text-xs font-semibold">Kode Outlet</th>
            <th class="px-4 py-3 text-center text-xs font-semibold">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users.data" :key="user.id" class="border-t">
            <td class="px-4 py-3">{{ user.name }}</td>
            <td class="px-4 py-3">{{ user.email }}</td>
            <td class="px-4 py-3">{{ user.nama_outlet }}</td>
            <td class="px-4 py-3">{{ user.kode_outlet }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="user.status === 'A' ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                {{ user.status === 'A' ? 'Aktif' : 'Non Aktif' }}
              </span>
            </td>
          </tr>
          <tr v-if="users.data.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada user external</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
