<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">{{ record.number }}</h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.employee_name }} · {{ record.template_name }} · Minggu {{ record.unlocked_week }}/{{ record.total_weeks }}</p>
        </div>
        <Link :href="route('employee-onboarding.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-gray-500 text-xs">Karyawan</div><div class="font-semibold">{{ record.employee_name }}</div></div>
        <div><div class="text-gray-500 text-xs">Outlet</div><div class="font-semibold">{{ record.outlet_name || '-' }}</div></div>
        <div><div class="text-gray-500 text-xs">Tanggal Mulai</div><div class="font-semibold">{{ record.start_date }}</div></div>
        <div><div class="text-gray-500 text-xs">Status</div><div class="font-semibold">{{ statusLabel(record.status) }}</div></div>
      </div>

      <div class="flex flex-wrap gap-2 mb-4">
        <button
          v-for="week in record.weeks"
          :key="week.week_number"
          type="button"
          class="px-4 py-2 rounded-lg text-sm font-semibold border"
          :class="activeWeek === week.week_number ? 'bg-indigo-600 text-white border-indigo-600' : week.is_unlocked ? 'bg-white text-gray-700 border-gray-300' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'"
          @click="week.is_unlocked && (activeWeek = week.week_number)"
        >
          Minggu {{ week.week_number }}
        </button>
      </div>

      <div v-if="currentWeekData" class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b flex flex-col md:flex-row md:items-center gap-3 justify-between">
          <div>
            <h2 class="text-lg font-semibold">Minggu {{ currentWeekData.week_number }}</h2>
            <p v-if="currentWeekData.submission" class="text-sm text-gray-500">
              Submission: {{ currentWeekData.submission.status }}
              <span v-if="currentWeekData.submission.approved_at"> · Approved {{ formatDateTime(currentWeekData.submission.approved_at) }}</span>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button v-if="canSubmitWeek" type="button" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700" @click="openSubmitModal">Submit Minggu</button>
            <template v-if="canApproveWeek && currentWeekData.submission?.status === 'submitted'">
              <button type="button" class="px-4 py-2 rounded-lg bg-green-600 text-white" @click="processApproval('approve')">Approve</button>
              <button type="button" class="px-4 py-2 rounded-lg bg-amber-500 text-white" @click="processApproval('requires_revision')">Revisi</button>
              <button type="button" class="px-4 py-2 rounded-lg bg-red-600 text-white" @click="processApproval('reject')">Tolak</button>
            </template>
          </div>
        </div>

        <div v-if="currentWeekData.submission?.approval_flows?.length" class="px-6 py-3 border-b bg-gray-50">
          <div class="text-xs font-semibold text-gray-500 mb-2">Approval Flow</div>
          <div class="flex flex-wrap gap-2">
            <span v-for="flow in currentWeekData.submission.approval_flows" :key="flow.id" class="px-3 py-1 rounded-full text-xs font-semibold bg-white border">
              L{{ flow.approval_level }}: {{ flow.approver_name }} — {{ flow.status }}
            </span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-3 py-3 text-left">Area</th>
                <th class="px-3 py-3 text-left">Checklist</th>
                <th class="px-3 py-3 text-left">PIC</th>
                <th class="px-3 py-3 text-left">Status</th>
                <th class="px-3 py-3 text-left">Remark</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in editableItems" :key="item.id" class="border-b align-top">
                <td class="px-3 py-3 whitespace-nowrap">{{ item.area_name }}</td>
                <td class="px-3 py-3">{{ item.checklist_text }}</td>
                <td class="px-3 py-3">
                  <select v-if="record.can_manage && weekEditable" v-model="item.assigned_pic_user_id" class="rounded-lg border-gray-300 text-sm">
                    <option :value="null">-</option>
                    <option v-for="user in userOptions" :key="user.id" :value="user.id">{{ user.name }}</option>
                  </select>
                  <span v-else>{{ item.assigned_pic_name || '-' }}</span>
                </td>
                <td class="px-3 py-3">
                  <select v-if="item.can_edit" v-model="item.status" class="rounded-lg border-gray-300 text-sm">
                    <option value="pending">Pending</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="done">Done</option>
                  </select>
                  <span v-else class="capitalize">{{ item.status }}</span>
                </td>
                <td class="px-3 py-3">
                  <textarea v-if="item.can_edit" v-model="item.remark" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                  <span v-else>{{ item.remark || '-' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="weekEditable" class="px-6 py-4 border-t flex justify-end">
          <button type="button" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="saving" @click="saveItems">
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </button>
        </div>
      </div>

      <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
          <h3 class="text-lg font-semibold mb-2">Submit Minggu {{ activeWeek }}</h3>
          <p class="text-sm text-gray-500 mb-4">Override approver (opsional). Kosongkan untuk pakai default dari template.</p>
          <div v-for="(approverId, idx) in submitApprovers" :key="idx" class="flex items-center gap-2 mb-2">
            <span class="w-8 text-center text-xs font-bold bg-gray-100 rounded">{{ idx + 1 }}</span>
            <select v-model="submitApprovers[idx]" class="flex-1 rounded-lg border-gray-300">
              <option value="">Pilih approver</option>
              <option v-for="user in userOptions" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
            <button type="button" class="text-red-500" @click="submitApprovers.splice(idx, 1)"><i class="fa-solid fa-trash"></i></button>
          </div>
          <button type="button" class="text-sm text-indigo-600 mb-4" @click="submitApprovers.push('')"><i class="fa-solid fa-plus"></i> Tambah Approver</button>
          <div class="flex justify-end gap-2">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-100" @click="showSubmitModal = false">Batal</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-indigo-600 text-white" @click="submitWeek">Submit</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canApproveWeek: { type: Boolean, default: false },
});

const localRecord = ref(JSON.parse(JSON.stringify(props.record)));
const activeWeek = ref(props.record.unlocked_week || 1);
const userOptions = ref([]);
const saving = ref(false);
const showSubmitModal = ref(false);
const submitApprovers = ref(['']);

const currentWeekData = computed(() => localRecord.value.weeks.find((w) => w.week_number === activeWeek.value));
const editableItems = computed(() => currentWeekData.value?.items || []);
const weekEditable = computed(() => {
  const week = currentWeekData.value;
  if (!week?.is_unlocked) return false;
  if (!week.submission) return true;
  return ['requires_revision', 'rejected'].includes(week.submission.status);
});
const canSubmitWeek = computed(() => {
  const week = currentWeekData.value;
  if (!week?.is_current || !weekEditable.value) return false;
  return week.items.length > 0 && week.items.every((item) => item.status === 'done' && item.assigned_pic_user_id);
});

watch(() => props.record, (val) => {
  localRecord.value = JSON.parse(JSON.stringify(val));
}, { deep: true });

function statusLabel(status) {
  const map = { draft: 'Draft', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' };
  return map[status] || status;
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID');
}

async function loadUsers() {
  const { data } = await axios.get(route('employee-onboarding.search-users'));
  userOptions.value = data.users || [];
}

async function saveItems() {
  saving.value = true;
  try {
    const { data } = await axios.post(route('employee-onboarding.update-items', localRecord.value.id), {
      items: editableItems.value.map((item) => ({
        id: item.id,
        status: item.status,
        remark: item.remark,
        assigned_pic_user_id: item.assigned_pic_user_id,
      })),
    });
    if (data.success) {
      localRecord.value = data.record;
      Swal.fire({ icon: 'success', title: 'Tersimpan', timer: 1200, showConfirmButton: false });
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || 'Gagal menyimpan.' });
  } finally {
    saving.value = false;
  }
}

function openSubmitModal() {
  submitApprovers.value = [''];
  showSubmitModal.value = true;
}

async function submitWeek() {
  try {
    const approvers = submitApprovers.value.filter(Boolean).map(Number);
    const { data } = await axios.post(route('employee-onboarding.submit-week', localRecord.value.id), {
      week_number: activeWeek.value,
      approvers: approvers.length ? approvers : undefined,
    });
    if (data.success) {
      localRecord.value = data.record;
      showSubmitModal.value = false;
      Swal.fire({ icon: 'success', title: 'Submitted', text: data.message });
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || 'Gagal submit.' });
  }
}

async function processApproval(action) {
  const requireComment = action !== 'approve';
  const result = await Swal.fire({
    title: action === 'approve' ? 'Approve minggu ini?' : action === 'requires_revision' ? 'Requires Revision' : 'Tolak minggu ini?',
    input: requireComment ? 'textarea' : undefined,
    inputPlaceholder: requireComment ? 'Catatan wajib...' : undefined,
    showCancelButton: true,
    confirmButtonText: 'Konfirmasi',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (requireComment && !value?.trim()) return 'Catatan wajib diisi';
      return undefined;
    },
  });
  if (!result.isConfirmed) return;

  try {
    const { data } = await axios.post(route('employee-onboarding.approve', localRecord.value.id), {
      week_number: activeWeek.value,
      action,
      comments: result.value || '',
    });
    if (data.success) {
      localRecord.value = data.record;
      Swal.fire({ icon: 'success', title: 'Selesai', text: data.message });
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || 'Gagal approval.' });
  }
}

onMounted(loadUsers);
</script>
