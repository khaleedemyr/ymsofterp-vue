<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Form Pengajuan WFH</h1>
          <p class="text-sm text-gray-500 mt-1">YM-HR045 — isi pekerjaan, alasan, dan approval flow.</p>
        </div>
        <Link
          :href="route('wfh-requests.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
        >
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-4">Data Karyawan</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-xs text-gray-500">Nama</div>
              <div class="font-medium text-gray-900">{{ employee.nama_lengkap }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">Jabatan</div>
              <div class="font-medium text-gray-900">{{ employee.jabatan || '-' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">Divisi / Department</div>
              <div class="font-medium text-gray-900">{{ employee.divisi || '-' }}</div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal WFH *</label>
              <input
                v-model="form.wfh_date"
                type="date"
                required
                class="w-full rounded-lg border-gray-300"
                @change="checkShift"
              />
              <p v-if="form.errors.wfh_date" class="text-sm text-red-600 mt-1">{{ form.errors.wfh_date }}</p>
            </div>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Alasan WFH *</label>
            <textarea
              v-model="form.reason"
              rows="2"
              required
              class="w-full rounded-lg border-gray-300"
              placeholder="Contoh: Instruksi atasan / keperluan ..."
            ></textarea>
            <p v-if="form.errors.reason" class="text-sm text-red-600 mt-1">{{ form.errors.reason }}</p>
          </div>

          <div
            v-if="shiftInfo"
            class="mt-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900"
          >
            <div class="font-semibold">Shift ditemukan</div>
            <div>
              {{ shiftInfo.shift_name }} · {{ formatTime(shiftInfo.time_start) }} – {{ formatTime(shiftInfo.time_end) }}
              <span v-if="shiftInfo.outlet_name"> · {{ shiftInfo.outlet_name }}</span>
            </div>
            <div class="text-xs mt-1 text-teal-700">
              Setelah fully approved, jam ini yang akan dicatat ke absensi (telat/lembur = 0).
            </div>
          </div>
          <div
            v-else-if="shiftError"
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
          >
            {{ shiftError }}
            <Link :href="'/user-shifts'" class="underline font-medium ml-1">Buka Input Shift Mingguan</Link>
          </div>
          <div v-else-if="checkingShift" class="mt-4 text-sm text-gray-500">Memeriksa shift...</div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6">
          <div class="px-6 py-4 border-b bg-teal-50 font-semibold text-teal-900 flex items-center justify-between rounded-t-xl">
            <span>List yang dikerjakan *</span>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-sm disabled:opacity-50"
              :disabled="form.tasks.length >= 10"
              @click="addTask"
            >
              <i class="fa-solid fa-plus mr-1"></i> Tambah
            </button>
          </div>
          <div class="p-6 space-y-3">
            <div v-for="(task, index) in form.tasks" :key="`task-${index}`" class="flex gap-2 items-start">
              <div class="w-8 pt-2 text-sm font-semibold text-gray-500">{{ index + 1 }}.</div>
              <input
                v-model="task.description"
                type="text"
                required
                maxlength="500"
                class="flex-1 rounded-lg border-gray-300"
                :placeholder="`Pekerjaan ${index + 1}`"
              />
              <button
                type="button"
                class="px-3 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-40"
                :disabled="form.tasks.length <= 1"
                @click="removeTask(index)"
              >
                Hapus
              </button>
            </div>
            <p v-if="form.errors.tasks" class="text-sm text-red-600">{{ form.errors.tasks }}</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-1">Approval Flow *</h2>
          <p class="text-sm text-gray-500 mb-4">
            Tambahkan approver dari level terendah ke tertinggi (bebas, minimal 1).
          </p>

          <input
            v-model="approverSearch"
            type="text"
            placeholder="Cari nama / jabatan approver..."
            class="w-full rounded-lg border-gray-300 mb-4"
            @input="searchApprovers"
          />

          <div v-if="approverResults.length > 0" class="border rounded-lg mb-4 max-h-48 overflow-y-auto divide-y">
            <button
              v-for="user in approverResults"
              :key="user.id"
              type="button"
              class="w-full text-left px-4 py-2 hover:bg-teal-50 text-sm"
              @click="addApprover(user)"
            >
              <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
              <div class="text-xs text-gray-500">{{ user.jabatan_name || '-' }} · {{ user.email }}</div>
            </button>
          </div>

          <div
            v-if="form.approvers.length === 0"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          >
            Belum ada approver. Tambahkan minimal 1 orang.
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="(approver, index) in form.approvers"
              :key="approver.id"
              class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 bg-gray-50"
            >
              <div>
                <div class="text-xs font-semibold text-teal-600">Level {{ index + 1 }}</div>
                <div class="font-medium text-gray-800">{{ approver.nama_lengkap }}</div>
                <div class="text-xs text-gray-500">{{ approver.jabatan_name || '-' }}</div>
              </div>
              <div class="flex gap-2">
                <button type="button" class="px-2 py-1 text-xs rounded border" :disabled="index === 0" @click="moveApprover(index, index - 1)">↑</button>
                <button
                  type="button"
                  class="px-2 py-1 text-xs rounded border"
                  :disabled="index === form.approvers.length - 1"
                  @click="moveApprover(index, index + 1)"
                >
                  ↓
                </button>
                <button type="button" class="px-2 py-1 text-xs rounded bg-red-100 text-red-700" @click="removeApprover(index)">
                  Hapus
                </button>
              </div>
            </div>
          </div>
          <p v-if="form.errors.approvers" class="text-sm text-red-600 mt-2">{{ form.errors.approvers }}</p>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('wfh-requests.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button
            type="submit"
            :disabled="form.processing || !canSubmit"
            class="px-5 py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50"
          >
            {{ form.processing ? 'Menyimpan...' : 'Ajukan WFH' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
  employee: { type: Object, required: true },
  today: { type: String, required: true },
});

const form = useForm({
  wfh_date: props.today,
  reason: '',
  tasks: [{ description: '' }],
  approvers: [],
});

const approverSearch = ref('');
const approverResults = ref([]);
const shiftInfo = ref(null);
const shiftError = ref('');
const checkingShift = ref(false);
let searchTimer = null;
let shiftTimer = null;

const canSubmit = computed(() => {
  return (
    !!form.wfh_date &&
    !!form.reason.trim() &&
    form.tasks.some((t) => (t.description || '').trim() !== '') &&
    form.approvers.length > 0 &&
    !!shiftInfo.value
  );
});

function formatTime(value) {
  if (!value) return '-';
  return String(value).substring(0, 5);
}

function addTask() {
  if (form.tasks.length >= 10) return;
  form.tasks.push({ description: '' });
}

function removeTask(index) {
  if (form.tasks.length <= 1) return;
  form.tasks.splice(index, 1);
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
      const { data } = await axios.get(route('wfh-requests.approvers'), { params: { search: q } });
      const selectedIds = new Set(form.approvers.map((a) => a.id));
      approverResults.value = (data.approvers || []).filter((u) => !selectedIds.has(u.id));
    } catch {
      approverResults.value = [];
    }
  }, 300);
}

function addApprover(user) {
  if (form.approvers.some((a) => a.id === user.id)) return;
  form.approvers.push(user);
  approverSearch.value = '';
  approverResults.value = [];
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
}

function moveApprover(from, to) {
  if (to < 0 || to >= form.approvers.length) return;
  const item = form.approvers.splice(from, 1)[0];
  form.approvers.splice(to, 0, item);
}

function checkShift() {
  clearTimeout(shiftTimer);
  shiftInfo.value = null;
  shiftError.value = '';
  if (!form.wfh_date) return;

  shiftTimer = setTimeout(async () => {
    checkingShift.value = true;
    try {
      const { data } = await axios.get(route('wfh-requests.check-shift'), {
        params: { wfh_date: form.wfh_date },
      });
      if (data.success) {
        shiftInfo.value = data.shift;
        shiftError.value = '';
      } else {
        shiftInfo.value = null;
        shiftError.value = data.message || 'Shift belum tersedia.';
      }
    } catch (e) {
      shiftInfo.value = null;
      shiftError.value = e.response?.data?.message || 'Gagal memeriksa shift.';
    } finally {
      checkingShift.value = false;
    }
  }, 200);
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      tasks: data.tasks
        .map((t) => ({ description: (t.description || '').trim() }))
        .filter((t) => t.description !== ''),
      approvers: data.approvers.map((a) => a.id),
    }))
    .post(route('wfh-requests.store'), {
      preserveScroll: true,
    });
}

checkShift();
</script>
