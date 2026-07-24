<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-house-laptop text-teal-600"></i>
            Pengajuan WFH
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Setelah fully approved, jam masuk/keluar diambil dari shift mingguan ke absensi.
          </p>
        </div>
        <Link
          :href="route('wfh-requests.create')"
          class="inline-flex items-center gap-2 bg-teal-600 text-white px-4 py-2 rounded-lg shadow hover:bg-teal-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Pengajuan
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / nama / alasan..."
            class="flex-1 rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500"
          />
          <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Tanggal WFH</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Karyawan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Shift</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Approver</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada pengajuan WFH.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-teal-50/40 align-top">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3">{{ formatDate(row.wfh_date) }}</td>
              <td class="px-4 py-3">
                <div class="font-medium">{{ row.user?.nama_lengkap || '-' }}</div>
                <div class="text-xs text-gray-500">{{ row.user?.jabatan?.nama_jabatan || '-' }}</div>
              </td>
              <td class="px-4 py-3">
                <div>{{ row.shift_name || '-' }}</div>
                <div class="text-xs text-gray-500">
                  {{ formatTime(row.time_start) }} – {{ formatTime(row.time_end) }}
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(row.status)">
                  {{ statusLabel(row.status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div v-if="sortedFlows(row).length === 0" class="text-gray-400">-</div>
                <ul v-else class="space-y-1.5 min-w-[220px]">
                  <li v-for="flow in sortedFlows(row)" :key="flow.id" class="text-xs leading-snug">
                    <div class="font-medium text-gray-800">
                      L{{ flow.approval_level }} · {{ flow.approver?.nama_lengkap || '-' }}
                    </div>
                    <div :class="flowStatusClass(flow.status)">
                      <template v-if="flow.status === 'APPROVED'">Approved</template>
                      <template v-else-if="flow.status === 'REJECTED'">Rejected</template>
                      <template v-else>Waiting</template>
                    </div>
                  </li>
                </ul>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <Link :href="route('wfh-requests.show', row.id)" class="text-teal-600 hover:text-teal-800 mr-3" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="text-red-600 hover:text-red-800"
                  title="Hapus"
                  @click="confirmDelete(row)"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canDelete = computed(() => String(page.props.auth?.user?.id_role || '') === '5af56935b011a');
const filterForm = reactive({ search: props.filters.search || '' });

function applyFilters() {
  router.get(route('wfh-requests.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(value) {
  if (!value) return '-';
  return String(value).substring(0, 5);
}

function sortedFlows(row) {
  const flows = row.approval_flows || [];
  return [...flows].sort((a, b) => (Number(a.approval_level) || 0) - (Number(b.approval_level) || 0));
}

function statusLabel(status) {
  if (status === 'APPROVED') return 'Approved';
  if (status === 'REJECTED') return 'Rejected';
  return 'Submitted';
}

function statusClass(status) {
  if (status === 'APPROVED') return 'bg-green-100 text-green-700';
  if (status === 'REJECTED') return 'bg-red-100 text-red-700';
  return 'bg-amber-100 text-amber-700';
}

function flowStatusClass(status) {
  if (status === 'APPROVED') return 'text-green-600';
  if (status === 'REJECTED') return 'text-red-600';
  return 'text-amber-600';
}

async function confirmDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus pengajuan?',
    text: row.number,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  });
  if (result.isConfirmed) {
    router.delete(route('wfh-requests.destroy', row.id));
  }
}
</script>
