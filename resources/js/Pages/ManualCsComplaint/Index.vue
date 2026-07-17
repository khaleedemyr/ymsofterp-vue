<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-headset text-sky-600"></i>
            Input Complaint CS
          </h1>
          <p class="text-sm text-gray-500 mt-1">Catat komplain manual dari Customer Service, lalu sync ke CVCC</p>
        </div>
        <Link :href="route('manual-cs-complaints.create')" class="inline-flex items-center gap-2 bg-sky-600 text-white px-4 py-2 rounded-lg shadow hover:bg-sky-700 transition">
          <i class="fa-solid fa-plus"></i>
          Input Complaint
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <input v-model="filterForm.search" type="text" placeholder="Cari nomor / nama / kontak..." class="rounded-lg border-gray-300 md:col-span-2" />
          <select v-model="filterForm.sync_status" class="rounded-lg border-gray-300">
            <option value="">Semua status sync</option>
            <option value="pending">Pending</option>
            <option value="synced">Synced</option>
            <option value="failed">Failed</option>
          </select>
          <div class="flex gap-2">
            <select v-model="filterForm.id_outlet" class="flex-1 rounded-lg border-gray-300">
              <option value="">Semua outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700">Cari</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Tanggal Kejadian</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Pelanggan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Channel</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Severity</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Sync</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada complaint CS.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-sky-50/40">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(row.event_at) }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ row.author_name }}</div>
                <div class="text-xs text-gray-500">{{ row.customer_contact || '-' }}</div>
              </td>
              <td class="px-4 py-3">{{ row.outlet?.nama_outlet || '-' }}</td>
              <td class="px-4 py-3">{{ channelLabel(row.input_channel) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="severityClass(row.severity)" class="px-2 py-1 rounded-full text-xs font-semibold uppercase">{{ row.severity }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="syncClass(row.sync_status)" class="px-2 py-1 rounded-full text-xs font-semibold">{{ syncLabel(row.sync_status) }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex items-center gap-2">
                  <a
                    v-if="row.feedback_case_id"
                    :href="`/customer-voice-command-center?show_all=1&open_case=${row.feedback_case_id}`"
                    class="text-sky-600 hover:text-sky-800"
                    title="Buka di CVCC"
                    target="_blank"
                  >
                    <i class="fa-solid fa-up-right-from-square"></i>
                  </a>
                  <button
                    v-if="row.sync_status !== 'synced'"
                    type="button"
                    @click="syncRow(row)"
                    class="text-emerald-600 hover:text-emerald-800"
                    title="Sync ke CVCC"
                  >
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                  </button>
                  <button
                    v-if="canDelete"
                    type="button"
                    @click="confirmDelete(row)"
                    class="text-red-600 hover:text-red-800"
                    title="Hapus"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
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
  outlets: { type: Array, default: () => [] },
});

const page = usePage();
const canDelete = computed(() => String(page.props.auth?.user?.id_role || '') === '5af56935b011a');

const filterForm = reactive({
  search: props.filters.search || '',
  sync_status: props.filters.sync_status || '',
  id_outlet: props.filters.id_outlet || '',
});

function applyFilters() {
  router.get(route('manual-cs-complaints.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function channelLabel(v) {
  const map = { phone: 'Telepon', walk_in: 'Walk-in', email: 'Email', whatsapp_cs: 'WhatsApp CS', other: 'Lainnya' };
  return map[v] || v || '-';
}

function syncLabel(v) {
  const map = { pending: 'Pending', synced: 'Synced', failed: 'Failed' };
  return map[v] || v || '-';
}

function syncClass(v) {
  if (v === 'synced') return 'bg-emerald-100 text-emerald-700';
  if (v === 'failed') return 'bg-red-100 text-red-700';
  return 'bg-amber-100 text-amber-700';
}

function severityClass(v) {
  if (v === 'critical') return 'bg-red-100 text-red-700';
  if (v === 'major') return 'bg-orange-100 text-orange-700';
  return 'bg-yellow-100 text-yellow-700';
}

function syncRow(row) {
  router.post(route('manual-cs-complaints.sync', row.id));
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus complaint?',
    text: `Hapus ${row.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('manual-cs-complaints.destroy', row.id));
    }
  });
}
</script>
