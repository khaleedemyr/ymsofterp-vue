<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  evaluations: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const periodMonth = ref(props.filters?.period_month || '');
const evalStatus = ref(props.filters?.eval_status || '');

const debouncedSearch = debounce(() => {
  router.get(route('kpi-evaluations.index'), {
    search: search.value || undefined,
    period_month: periodMonth.value || undefined,
    eval_status: evalStatus.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch([periodMonth, evalStatus], () => debouncedSearch());

function statusBadge(st) {
  const map = {
    draft: 'bg-yellow-100 text-yellow-800',
    submitted: 'bg-green-100 text-green-800',
    locked: 'bg-gray-100 text-gray-600',
  };
  return map[st] || 'bg-gray-100';
}

function openCreate() {
  router.visit(route('kpi-evaluations.create'));
}

function openEdit(row) {
  router.visit(row.eval_status === 'draft' ? route('kpi-evaluations.edit', row.id) : route('kpi-evaluations.show', row.id));
}

async function hapus(row) {
  if (row.eval_status !== 'draft') return;
  const result = await Swal.fire({ title: 'Hapus draft evaluasi?', icon: 'warning', showCancelButton: true });
  if (!result.isConfirmed) return;
  router.delete(route('kpi-evaluations.destroy', row.id));
}
</script>

<template>
  <AppLayout title="KPI Evaluation">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-rose-600"></i>
            KPI Evaluation
          </h1>
          <p class="text-sm text-gray-600 mt-1">Evaluasi KPI karyawan per periode bulanan.</p>
        </div>
        <button type="button" class="bg-rose-600 text-white px-4 py-2 rounded-xl font-semibold" @click="openCreate">+ Buat Evaluasi</button>
      </div>

      <div class="flex flex-wrap gap-3 mb-4">
        <input v-model="search" type="text" placeholder="Cari kode / karyawan / outlet..." class="px-4 py-2 rounded-xl border max-w-md" @input="debouncedSearch" />
        <input v-model="periodMonth" type="month" class="px-3 py-2 rounded-xl border" @change="debouncedSearch" />
        <select v-model="evalStatus" class="px-3 py-2 rounded-xl border">
          <option value="">Semua status</option>
          <option value="draft">Draft</option>
          <option value="submitted">Submitted</option>
          <option value="locked">Locked</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Karyawan</th>
              <th class="px-4 py-3 text-left">Jabatan / Outlet</th>
              <th class="px-4 py-3 text-left">Periode</th>
              <th class="px-4 py-3 text-left">Template</th>
              <th class="px-4 py-3 text-right">Skor</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="row in evaluations.data" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-indigo-700">{{ row.evaluation_code }}</td>
              <td class="px-4 py-3 font-medium">{{ row.employee_name }}</td>
              <td class="px-4 py-3 text-gray-600">
                <div>{{ row.jabatan_name || '—' }}</div>
                <div class="text-xs">{{ row.outlet_name || '—' }}</div>
              </td>
              <td class="px-4 py-3">{{ row.period_month }}</td>
              <td class="px-4 py-3 text-xs">{{ row.template?.name || '—' }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ row.total_score ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusBadge(row.eval_status)">{{ row.eval_status }}</span>
              </td>
              <td class="px-4 py-3 text-right space-x-2">
                <button class="text-indigo-600 hover:underline" @click="openEdit(row)">{{ row.eval_status === 'draft' ? 'Edit' : 'Lihat' }}</button>
                <button v-if="row.eval_status === 'draft'" class="text-red-600 hover:underline" @click="hapus(row)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!evaluations.data?.length">
              <td colspan="8" class="px-4 py-8 text-center text-gray-400">Belum ada evaluasi KPI.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
