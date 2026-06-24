<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmPageLayout from './components/EmPageLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import './styles/em-theme.css';

const props = defineProps({
  movements: Object,
  filters: Object,
  employees: Array,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const selectedEmployee = ref(null);

const employeeOptions = ref(props.employees.map(emp => ({
  id: emp.id,
  name: `${emp.nama_lengkap} (${emp.nik})`,
  nik: emp.nik
})));

if (props.filters?.employee_id) {
  const employee = props.employees.find(emp => emp.id == props.filters.employee_id);
  if (employee) {
    selectedEmployee.value = {
      id: employee.id,
      name: `${employee.nama_lengkap} (${employee.nik})`,
      nik: employee.nik
    };
  }
}

const debouncedSearch = debounce(() => {
  router.get('/employee-movements', {
    search: search.value,
    status: status.value,
    employee_id: selectedEmployee.value?.id || '',
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function onFilterChange() {
  debouncedSearch();
}

function onEmployeeChange() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/employee-movements/create');
}

function openEdit(movement) {
  router.visit(`/employee-movements/${movement.id}/edit`);
}

function openShow(movement) {
  router.visit(`/employee-movements/${movement.id}`);
}

async function hapus(movement) {
  const result = await Swal.fire({
    title: 'Hapus Employee Movement?',
    text: `Yakin ingin menghapus employee movement untuk "${movement.employee_name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });

  if (!result.isConfirmed) return;

  router.delete(route('employee-movements.destroy', movement.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Employee movement berhasil dihapus!', 'success'),
  });
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'draft': return 'em-badge-draft';
    case 'pending': return 'em-badge-pending';
    case 'approved': return 'em-badge-approved';
    case 'rejected': return 'em-badge-rejected';
    case 'executed': return 'em-badge-executed';
    default: return 'em-badge-draft';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'draft': return 'Draft';
    case 'pending': return 'Pending';
    case 'approved': return 'Approved';
    case 'rejected': return 'Rejected';
    case 'executed': return 'Executed';
    default: return status;
  }
}

function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}
</script>

<template>
  <AppLayout title="Employee Movement">
    <EmPageLayout
      title="Employee Movement"
      subtitle="Kelola perubahan status, mutasi, dan promosi karyawan"
    >
      <template #actions>
        <button type="button" class="em-btn em-btn-primary" @click="openCreate">
          <i class="fas fa-plus"></i>
          <span>Tambah Movement</span>
        </button>
      </template>

      <div class="em-card">
        <!-- Filters -->
        <div class="em-filter-bar">
          <div class="em-filter-grid">
            <div>
              <label class="em-label">Cari</label>
              <div class="relative em-search-wrap">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                  v-model="search"
                  type="text"
                  placeholder="Nama, NIK, atau email..."
                  class="em-input !pl-9"
                  @input="onSearchInput"
                />
              </div>
            </div>

            <div>
              <label class="em-label">Status</label>
              <select v-model="status" class="em-input" @change="onFilterChange">
                <option value="all">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>

            <div>
              <label class="em-label">Karyawan</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employeeOptions"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                track-by="id"
                label="name"
                placeholder="Pilih karyawan..."
                class="w-full"
                @select="onEmployeeChange"
                @remove="onEmployeeChange"
              />
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="movements.data.length === 0" class="em-empty">
          <div class="em-empty-icon">
            <i class="fas fa-inbox"></i>
          </div>
          <p class="em-empty-title">Tidak ada data employee movement</p>
          <p class="em-empty-desc">Coba ubah filter pencarian atau tambah movement baru</p>
        </div>

        <!-- Table -->
        <div v-else class="em-table-wrap">
          <table class="em-table">
            <thead>
              <tr>
                <th>Karyawan</th>
                <th>Posisi</th>
                <th>Divisi</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="movement in movements.data" :key="movement.id">
                <td>
                  <div class="flex items-center gap-3">
                    <div class="em-avatar">{{ getInitials(movement.employee_name) }}</div>
                    <div>
                      <div class="font-semibold text-slate-800">{{ movement.employee_name }}</div>
                      <div class="text-xs text-slate-500 mt-0.5">{{ movement.nik }}</div>
                    </div>
                  </div>
                </td>
                <td>{{ movement.employee_position || movement.nama_jabatan || '-' }}</td>
                <td>{{ movement.employee_division || movement.nama_divisi || '-' }}</td>
                <td>
                  <span :class="['em-badge', getStatusBadgeClass(movement.status)]">
                    {{ getStatusText(movement.status) }}
                  </span>
                </td>
                <td class="text-slate-500 whitespace-nowrap">
                  {{ new Date(movement.created_at).toLocaleDateString('id-ID') }}
                </td>
                <td>
                  <div class="em-action-group">
                    <button
                      type="button"
                      class="em-action-btn em-action-btn--view"
                      title="Detail"
                      @click="openShow(movement)"
                    >
                      <i class="fas fa-eye"></i>
                      Detail
                    </button>
                    <button
                      type="button"
                      class="em-action-btn em-action-btn--edit"
                      title="Edit"
                      @click="openEdit(movement)"
                    >
                      <i class="fas fa-edit"></i>
                      Edit
                    </button>
                    <button
                      type="button"
                      class="em-action-btn em-action-btn--delete"
                      title="Hapus"
                      @click="hapus(movement)"
                    >
                      <i class="fas fa-trash"></i>
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="movements.links && movements.links.length > 3 && movements.data.length > 0" class="em-pagination">
          <div class="em-pagination-info">
            Menampilkan <strong>{{ movements.from }}</strong>–<strong>{{ movements.to }}</strong>
            dari <strong>{{ movements.total }}</strong> data
          </div>
          <div class="em-pagination-nav">
            <button
              type="button"
              class="em-page-btn"
              :disabled="!movements.prev_page_url"
              @click="goToPage(movements.prev_page_url)"
            >
              <i class="fas fa-chevron-left text-xs"></i>
            </button>

            <template v-for="(link, index) in movements.links" :key="index">
              <button
                v-if="link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                type="button"
                :class="['em-page-btn', { 'em-page-btn--active': link.active }]"
                @click="goToPage(link.url)"
                v-html="link.label"
              />
            </template>

            <button
              type="button"
              class="em-page-btn"
              :disabled="!movements.next_page_url"
              @click="goToPage(movements.next_page_url)"
            >
              <i class="fas fa-chevron-right text-xs"></i>
            </button>
          </div>
        </div>
      </div>
    </EmPageLayout>
  </AppLayout>
</template>
