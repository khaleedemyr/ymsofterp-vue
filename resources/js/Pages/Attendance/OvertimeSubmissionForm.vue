<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Buat Pengajuan Lembur</h1>
          <p class="text-sm text-gray-500 mt-1">Wajib pilih approver. Baru berfungsi di laporan setelah fully approved.</p>
        </div>
        <Link :href="route('overtime-submissions.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Pengajuan *</label>
            <input v-model="form.submission_date" type="date" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Filter Outlet (opsional)</label>
            <select v-model="selectedOutletId" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
            </select>
          </div>
          <div class="md:col-span-1">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
            <input v-model="form.notes" class="w-full rounded-lg border-gray-300" />
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 overflow-visible">
          <div class="px-6 py-4 border-b bg-indigo-50 font-semibold text-indigo-900 flex items-center justify-between rounded-t-xl">
            <span>Daftar Karyawan Lembur</span>
            <button type="button" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm" @click="addRow">
              <i class="fa-solid fa-plus mr-1"></i> Tambah Baris
            </button>
          </div>

          <div class="p-6 space-y-4 overflow-visible">
            <div class="grid grid-cols-12 gap-2 text-xs font-semibold uppercase text-gray-500 px-1">
              <div class="col-span-5">Nama Karyawan *</div>
              <div class="col-span-3">Tanggal Lembur *</div>
              <div class="col-span-2">Jam Pengajuan *</div>
              <div class="col-span-2">Aksi</div>
            </div>

            <div v-for="(item, index) in form.items" :key="`row-${index}`" class="overtime-item-row grid grid-cols-12 gap-2 items-start pb-1">
              <div class="col-span-5 overtime-user-select">
                <OnboardingUserSelect
                  v-model="item.user_id"
                  search-route="overtime-submissions.search-users"
                  placeholder="Cari nama / NIK / jabatan..."
                  :allow-empty="false"
                />
              </div>
              <div class="col-span-3">
                <input v-model="item.overtime_date" type="date" required class="w-full rounded-lg border-gray-300" />
              </div>
              <div class="col-span-2">
                <input v-model.number="item.requested_hours" type="number" min="1" max="24" step="1" required class="w-full rounded-lg border-gray-300" />
              </div>
              <div class="col-span-2">
                <button type="button" @click="removeRow(index)" class="px-3 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200">
                  Hapus
                </button>
              </div>
              <div class="col-span-12">
                <input v-model="item.notes" type="text" placeholder="Catatan baris (opsional)" class="w-full rounded-lg border-gray-300" />
              </div>
            </div>
          </div>
        </div>

        <!-- Approval Flow -->
        <div class="bg-white rounded-xl shadow mb-6 p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-1">Approval Flow *</h2>
          <p class="text-sm text-gray-500 mb-4">
            Tambahkan approver dari level terendah ke tertinggi. Tanpa approver, data tidak bisa disimpan.
          </p>

          <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <input
              v-model="approverSearch"
              type="text"
              placeholder="Cari nama / jabatan approver..."
              class="flex-1 rounded-lg border-gray-300"
              @input="searchApprovers"
            />
          </div>

          <div v-if="approverResults.length > 0" class="border rounded-lg mb-4 max-h-48 overflow-y-auto divide-y">
            <button
              v-for="user in approverResults"
              :key="user.id"
              type="button"
              class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-sm"
              @click="addApprover(user)"
            >
              <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
              <div class="text-xs text-gray-500">{{ user.jabatan_name || '-' }} · {{ user.email }}</div>
            </button>
          </div>

          <div v-if="form.approvers.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada approver. Tambahkan minimal 1 orang.
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="(approver, index) in form.approvers"
              :key="approver.id"
              class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 bg-gray-50"
            >
              <div>
                <div class="text-xs font-semibold text-indigo-600">Level {{ index + 1 }}</div>
                <div class="font-medium text-gray-800">{{ approver.nama_lengkap }}</div>
                <div class="text-xs text-gray-500">{{ approver.jabatan_name || '-' }}</div>
              </div>
              <div class="flex gap-2">
                <button type="button" class="px-2 py-1 text-xs rounded border" :disabled="index === 0" @click="moveApprover(index, index - 1)">↑</button>
                <button type="button" class="px-2 py-1 text-xs rounded border" :disabled="index === form.approvers.length - 1" @click="moveApprover(index, index + 1)">↓</button>
                <button type="button" class="px-2 py-1 text-xs rounded bg-red-100 text-red-700" @click="removeApprover(index)">Hapus</button>
              </div>
            </div>
          </div>
          <p v-if="form.errors.approvers" class="text-sm text-red-600 mt-2">{{ form.errors.approvers }}</p>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('overtime-submissions.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button
            type="submit"
            :disabled="form.processing || form.items.length === 0 || form.approvers.length === 0"
            class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            Simpan
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import OnboardingUserSelect from '@/Components/EmployeeOnboarding/OnboardingUserSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  today: { type: String, required: true },
});

const selectedOutletId = ref('');
const approverSearch = ref('');
const approverResults = ref([]);
let searchTimer = null;

const form = useForm({
  submission_date: props.today,
  notes: '',
  items: [
    {
      user_id: '',
      overtime_date: props.today,
      requested_hours: 1,
      notes: '',
    },
  ],
  approvers: [],
});

function addRow() {
  form.items.push({
    user_id: '',
    overtime_date: props.today,
    requested_hours: 1,
    notes: '',
  });
}

function removeRow(index) {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
}

function searchApprovers() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    const q = approverSearch.value.trim();
    if (q.length < 2) {
      approverResults.value = [];
      return;
    }
    try {
      const res = await axios.get(route('overtime-submissions.approvers'), { params: { search: q } });
      approverResults.value = (res.data.approvers || []).filter(
        (u) => !form.approvers.some((a) => Number(a.id) === Number(u.id))
      );
    } catch (e) {
      console.error(e);
      approverResults.value = [];
    }
  }, 300);
}

function addApprover(user) {
  if (form.approvers.some((a) => Number(a.id) === Number(user.id))) return;
  form.approvers.push({
    id: user.id,
    nama_lengkap: user.nama_lengkap,
    jabatan_name: user.jabatan_name,
    email: user.email,
  });
  approverResults.value = [];
  approverSearch.value = '';
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
}

function moveApprover(from, to) {
  if (to < 0 || to >= form.approvers.length) return;
  const item = form.approvers.splice(from, 1)[0];
  form.approvers.splice(to, 0, item);
}

function submit() {
  if (form.approvers.length === 0) {
    Swal.fire('Approver wajib', 'Pilih minimal 1 approver sebelum menyimpan.', 'warning');
    return;
  }

  form.transform((data) => ({
    ...data,
    items: data.items.map((item) => ({
      ...item,
      user_id: Number(item.user_id),
    })),
    approvers: data.approvers.map((a) => Number(a.id)),
  })).post(route('overtime-submissions.store'));
}
</script>

<style scoped>
.overtime-item-row {
  position: relative;
  z-index: 0;
}

.overtime-item-row:has(.multiselect--active) {
  z-index: 50;
}

.overtime-user-select :deep(.onboarding-user-select) {
  position: relative;
}

.overtime-user-select :deep(.multiselect__content-wrapper) {
  z-index: 9999;
  min-width: 100%;
  width: max(100%, 320px);
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
}

.overtime-user-select :deep(.multiselect__option) {
  white-space: normal;
  word-break: break-word;
  line-height: 1.35;
  padding: 10px 14px;
  min-height: auto;
}
</style>
