<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-indigo-600"></i>
            Employee Onboarding
          </h1>
          <p class="text-sm text-gray-500 mt-1">Tracking onboarding karyawan baru per minggu</p>
        </div>
        <Link :href="route('employee-onboarding.create')" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
          <i class="fa-solid fa-plus"></i>
          Buat Onboarding
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3 items-stretch md:items-end">
          <div class="flex-1">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Cari nomor, karyawan, outlet, template..."
              class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Per page</label>
            <select
              v-model.number="filterForm.per_page"
              class="rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
              @change="changePerPage"
            >
              <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Karyawan</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Template</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Tanggal Mulai</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Minggu</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="records.data.length === 0">
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada onboarding.</td>
              </tr>
              <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-indigo-50/40">
                <td class="px-4 py-3 font-medium">{{ row.number }}</td>
                <td class="px-4 py-3">{{ row.employee?.nama_lengkap || '-' }}</td>
                <td class="px-4 py-3">{{ row.outlet_name || '-' }}</td>
                <td class="px-4 py-3">{{ row.template_name }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.start_date) }}</td>
                <td class="px-4 py-3">{{ row.unlocked_week }} / {{ row.total_weeks }}</td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100">{{ statusLabel(row.status) }}</span></td>
                <td class="px-4 py-3 text-right">
                  <div class="inline-flex items-center gap-2">
                    <Link :href="route('employee-onboarding.show', row.id)" class="text-indigo-600 hover:text-indigo-800" title="Lihat">
                      <i class="fa-solid fa-eye"></i>
                    </Link>
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

const SUPERADMIN_ROLE_ID = '5af56935b011a';
const perPageOptions = [10, 15, 25, 50, 100];

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canDelete = computed(() => String(page.props.auth?.user?.id_role || '') === SUPERADMIN_ROLE_ID);

const filterForm = reactive({
  search: props.filters?.search || '',
  per_page: Number(props.filters?.per_page) || 15,
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
    route('employee-onboarding.index'),
    { ...filterForm, page: 1 },
    { preserveState: true, replace: true },
  );
}

function changePerPage() {
  applyFilters();
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function statusLabel(status) {
  const map = { draft: 'Draft', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' };
  return map[status] || status;
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus onboarding?',
    text: `Hapus ${row.number} — ${row.employee?.nama_lengkap || 'karyawan'}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('employee-onboarding.destroy', row.id));
    }
  });
}

if (page.props.flash?.success) {
  Swal.fire({ icon: 'success', title: 'Berhasil', text: page.props.flash.success, timer: 2000, showConfirmButton: false });
}
</script>
