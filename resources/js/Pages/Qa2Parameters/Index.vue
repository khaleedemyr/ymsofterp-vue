<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  parameters: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');

const reload = debounce(() => {
  router.get(route('qa2-parameters.index'), {
    search: search.value,
    status: status.value,
  }, { preserveState: true, replace: true });
}, 350);

watch(status, reload);

function openCreate() {
  router.visit(route('qa2-parameters.create'));
}

function openEdit(row) {
  router.visit(route('qa2-parameters.edit', row.id));
}

async function deactivate(row) {
  const result = await Swal.fire({
    title: 'Nonaktifkan parameter?',
    text: `${row.code}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya',
  });

  if (!result.isConfirmed) return;
  router.delete(route('qa2-parameters.destroy', row.id));
}
</script>

<template>
  <AppLayout title="QA2 Parameters">
    <div class="w-full max-w-[110rem] mx-auto py-8 px-3">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">QA2 Parameters</h1>
          <p class="text-sm text-gray-500">Master parameter audit berbobot.</p>
        </div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded-xl font-semibold hover:bg-blue-700" @click="openCreate">
          + Buat Parameter
        </button>
      </div>

      <div class="flex gap-3 mb-4 flex-wrap">
        <input
          v-model="search"
          @input="reload"
          type="text"
          placeholder="Cari code / text / category..."
          class="px-4 py-2 border rounded-xl min-w-[340px]"
        />
        <select v-model="status" class="px-3 py-2 border rounded-xl">
          <option value="A">Aktif</option>
          <option value="N">Non-Aktif</option>
          <option value="all">Semua</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-blue-700 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Code</th>
              <th class="px-4 py-3 text-left">Parameter</th>
              <th class="px-4 py-3 text-left">Category / Subcategory</th>
              <th class="px-4 py-3 text-center">Weight</th>
              <th class="px-4 py-3 text-center">Sort</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in parameters.data" :key="row.id" class="border-b hover:bg-blue-50 align-top">
              <td class="px-4 py-2 font-mono">{{ row.code }}</td>
              <td class="px-4 py-2">{{ row.parameter_text }}</td>
              <td class="px-4 py-2">
                <div class="font-semibold">{{ row.category_code }} - {{ row.category_name }}</div>
                <div class="text-gray-500">{{ row.subcategory_code }} - {{ row.subcategory_name }}</div>
              </td>
              <td class="px-4 py-2 text-center">{{ row.weight }}</td>
              <td class="px-4 py-2 text-center">{{ row.sort_order }}</td>
              <td class="px-4 py-2 text-center">
                <span :class="row.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ row.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                </span>
              </td>
              <td class="px-4 py-2 text-center space-x-2">
                <button class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded" @click="openEdit(row)">
                  Edit
                </button>
                <button v-if="row.status === 'A'" class="px-2 py-1 bg-red-100 text-red-700 rounded" @click="deactivate(row)">
                  Nonaktifkan
                </button>
              </td>
            </tr>
            <tr v-if="!parameters.data?.length">
              <td colspan="7" class="text-center py-8 text-gray-400">Belum ada parameter QA2</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
