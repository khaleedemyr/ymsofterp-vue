<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-business-time text-indigo-600"></i>
            Pengajuan Lembur
          </h1>
          <p class="text-sm text-gray-500 mt-1">Hanya status Approved yang dipakai di laporan attendance</p>
        </div>
        <Link :href="route('overtime-submissions.create')" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
          <i class="fa-solid fa-plus"></i>
          Buat Pengajuan
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / pembuat..."
            class="flex-1 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
          />
          <label class="inline-flex items-center gap-2 shrink-0">
            <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Per page</span>
            <select
              v-model.number="filterForm.per_page"
              class="rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
              @change="changePerPage"
            >
              <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </label>
          <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Tanggal</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Pembuat</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Approver</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">Jumlah Karyawan</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">Total Jam Lembur</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="records.data.length === 0">
                <td colspan="9" class="px-4 py-8 text-center text-gray-500">Belum ada pengajuan lembur.</td>
              </tr>
              <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-indigo-50/40 align-top">
                <td class="px-4 py-3 font-medium">
                  <Link :href="route('overtime-submissions.show', row.id)" class="text-indigo-700 hover:text-indigo-900">
                    {{ row.number }}
                  </Link>
                </td>
                <td class="px-4 py-3">{{ formatDate(row.submission_date) }}</td>
                <td class="px-4 py-3">
                  <span v-if="row.outlet_name" class="text-gray-800">{{ row.outlet_name }}</span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-4 py-3">{{ row.creator?.nama_lengkap || '-' }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(row.status)">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div v-if="sortedFlows(row).length === 0" class="text-gray-400">-</div>
                  <ul v-else class="space-y-1.5 min-w-[220px]">
                    <li
                      v-for="flow in sortedFlows(row)"
                      :key="flow.id"
                      class="text-xs leading-snug"
                    >
                      <div class="font-medium text-gray-800">
                        L{{ flow.approval_level }} · {{ flow.approver?.nama_lengkap || '-' }}
                      </div>
                      <div :class="flowStatusClass(flow.status)">
                        <template v-if="flow.status === 'APPROVED'">
                          Approved · {{ formatDateTime(flow.approved_at) }}
                        </template>
                        <template v-else-if="flow.status === 'REJECTED'">
                          Rejected · {{ formatDateTime(flow.rejected_at) }}
                        </template>
                        <template v-else>
                          Waiting
                        </template>
                      </div>
                    </li>
                  </ul>
                </td>
                <td class="px-4 py-3 text-right">{{ row.employee_count || 0 }}</td>
                <td class="px-4 py-3 text-right font-semibold text-indigo-700">{{ formatHours(row.total_hours) }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <Link
                    :href="route('overtime-submissions.show', row.id)"
                    class="text-indigo-600 hover:text-indigo-800 mr-3"
                    title="Detail"
                  >
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

        <div
          v-if="records.total > 0"
          class="px-4 py-3 border-t flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-gray-600"
        >
          <div class="flex flex-wrap items-center gap-3">
            <span>
              Menampilkan {{ showingFrom }}–{{ showingTo }} dari {{ records.total }} data
            </span>
            <label class="inline-flex items-center gap-2">
              <span class="text-xs font-semibold text-gray-500">Per page</span>
              <select
                v-model.number="filterForm.per_page"
                class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1"
                @change="changePerPage"
              >
                <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
              </select>
            </label>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Halaman {{ records.current_page }} / {{ records.last_page }}</span>
            <Link
              v-if="records.prev_page_url"
              :href="records.prev_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
              preserve-scroll
            >Prev</Link>
            <span v-else class="px-3 py-1 rounded bg-gray-50 text-gray-300 cursor-not-allowed">Prev</span>
            <Link
              v-if="records.next_page_url"
              :href="records.next_page_url"
              class="px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
              preserve-scroll
            >Next</Link>
            <span v-else class="px-3 py-1 rounded bg-gray-50 text-gray-300 cursor-not-allowed">Next</span>
          </div>
        </div>
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

const perPageOptions = [10, 15, 25, 50, 100];

const filterForm = reactive({
  search: props.filters.search || '',
  per_page: Number(props.filters.per_page) || 15,
});

const showingFrom = computed(() => {
  if (!props.records?.total) return 0;
  return ((props.records.current_page - 1) * props.records.per_page) + 1;
});

const showingTo = computed(() => {
  if (!props.records?.total) return 0;
  return Math.min(props.records.current_page * props.records.per_page, props.records.total);
});

function applyFilters() {
  router.get(
    route('overtime-submissions.index'),
    { ...filterForm, page: 1 },
    { preserveState: true, replace: true },
  );
}

function changePerPage() {
  applyFilters();
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatHours(value) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) return '0 jam';
  const rounded = Math.round(n * 100) / 100;
  return `${rounded} jam`;
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function sortedFlows(row) {
  const flows = row.approval_flows || [];
  return [...flows].sort((a, b) => (Number(a.approval_level) || 0) - (Number(b.approval_level) || 0));
}

function statusLabel(status) {
  if (status === 'APPROVED') return 'Approved';
  if (status === 'REJECTED') return 'Rejected';
  if (status === 'SUBMITTED') return 'Waiting Approval';
  return status || '-';
}

function statusClass(status) {
  if (status === 'APPROVED') return 'bg-green-100 text-green-800';
  if (status === 'REJECTED') return 'bg-red-100 text-red-800';
  return 'bg-amber-100 text-amber-800';
}

function flowStatusClass(status) {
  if (status === 'APPROVED') return 'text-green-700';
  if (status === 'REJECTED') return 'text-red-700';
  return 'text-amber-700';
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus pengajuan?',
    text: `Hapus ${row.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('overtime-submissions.destroy', row.id));
    }
  });
}
</script>
