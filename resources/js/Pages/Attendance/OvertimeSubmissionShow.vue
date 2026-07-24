<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Detail Pengajuan Lembur</h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.number }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-if="canDelete"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-50 text-red-700 hover:bg-red-100"
            @click="confirmDelete"
          >
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
          <Link
            :href="route('overtime-submissions.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(record.status)">
            {{ statusLabel(record.status) }}
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-xs text-gray-500">Nomor</div>
            <div class="font-medium">{{ record.number }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Tanggal Pengajuan</div>
            <div class="font-medium">{{ formatDate(record.submission_date) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Pembuat</div>
            <div class="font-medium">{{ record.creator?.nama_lengkap || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Jumlah Karyawan / Item</div>
            <div class="font-medium">{{ employeeCount }} karyawan · {{ (record.items || []).length }} item</div>
          </div>
          <div class="md:col-span-2">
            <div class="text-xs text-gray-500">Catatan</div>
            <div class="font-medium whitespace-pre-wrap">{{ record.notes || '-' }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Daftar Lembur</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-700">Karyawan</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-700">Tanggal</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Jam</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-700">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="(record.items || []).length === 0">
                <td colspan="4" class="px-3 py-6 text-center text-gray-500">Tidak ada item.</td>
              </tr>
              <tr
                v-for="item in record.items || []"
                :key="item.id"
                class="border-b"
              >
                <td class="px-3 py-2">
                  <div class="font-medium">{{ item.user?.nama_lengkap || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ item.user?.nik || '' }}</div>
                </td>
                <td class="px-3 py-2">{{ formatDate(item.overtime_date) }}</td>
                <td class="px-3 py-2 text-right font-semibold text-indigo-600">{{ item.requested_hours }} jam</td>
                <td class="px-3 py-2 text-gray-600">{{ item.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-3 text-sm text-right text-gray-700">
          Total jam: <span class="font-semibold text-indigo-700">{{ totalHours }}</span>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Approval Flow</h2>
        <div class="space-y-2">
          <div
            v-for="flow in sortedFlows"
            :key="flow.id"
            class="flex items-center justify-between p-3 rounded-lg border"
            :class="flowRowClass(flow.status)"
          >
            <div>
              <div class="text-xs font-semibold text-indigo-700">Level {{ flow.approval_level }}</div>
              <div class="font-medium">{{ flow.approver?.nama_lengkap || '-' }}</div>
              <div v-if="flow.comments" class="text-xs text-gray-500 mt-1">{{ flow.comments }}</div>
              <div v-if="flow.status === 'APPROVED' && flow.approved_at" class="text-xs text-green-700 mt-1">
                {{ formatDateTime(flow.approved_at) }}
              </div>
              <div v-else-if="flow.status === 'REJECTED' && flow.rejected_at" class="text-xs text-red-700 mt-1">
                {{ formatDateTime(flow.rejected_at) }}
              </div>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded text-white" :class="flowBadgeClass(flow.status)">
              {{ flow.status }}
            </span>
          </div>
        </div>
      </div>

      <div v-if="canApprove" class="flex justify-end gap-3">
        <button
          type="button"
          class="px-5 py-2.5 rounded-lg bg-red-500 text-white hover:bg-red-600 disabled:opacity-50"
          :disabled="busy"
          @click="reject"
        >
          Tolak
        </button>
        <button
          type="button"
          class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="busy"
          @click="approve"
        >
          {{ busy ? 'Memproses...' : 'Setujui' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canApprove: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

const busy = ref(false);

const sortedFlows = computed(() => {
  const flows = props.record.approval_flows || [];
  return [...flows].sort((a, b) => (Number(a.approval_level) || 0) - (Number(b.approval_level) || 0));
});

const employeeCount = computed(() => {
  const ids = (props.record.items || []).map((i) => i.user_id).filter(Boolean);
  return new Set(ids).size;
});

const totalHours = computed(() => {
  const sum = (props.record.items || []).reduce((acc, item) => acc + (Number(item.requested_hours) || 0), 0);
  return `${sum} jam`;
});

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
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

function flowRowClass(status) {
  if (status === 'APPROVED') return 'bg-green-50 border-green-200';
  if (status === 'REJECTED') return 'bg-red-50 border-red-200';
  return 'bg-gray-50 border-gray-200';
}

function flowBadgeClass(status) {
  if (status === 'APPROVED') return 'bg-green-500';
  if (status === 'REJECTED') return 'bg-red-500';
  return 'bg-amber-500';
}

async function approve() {
  if (busy.value) return;
  busy.value = true;
  try {
    const { data } = await axios.post(`/api/overtime-submissions/${props.record.id}/approve`, { comments: '' });
    if (data.success) {
      await Swal.fire('Success', data.message || 'Berhasil disetujui', 'success');
      router.reload();
    } else {
      Swal.fire('Error', data.message || 'Gagal approve', 'error');
    }
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal approve', 'error');
  } finally {
    busy.value = false;
  }
}

function reject() {
  Swal.fire({
    title: 'Tolak Pengajuan Lembur',
    input: 'textarea',
    inputLabel: 'Alasan Penolakan',
    inputPlaceholder: 'Masukkan alasan penolakan...',
    showCancelButton: true,
    confirmButtonText: 'Tolak',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
  }).then(async (result) => {
    if (!result.isConfirmed) return;
    busy.value = true;
    try {
      const { data } = await axios.post(`/api/overtime-submissions/${props.record.id}/reject`, {
        comments: result.value || '',
      });
      if (data.success) {
        await Swal.fire('Success', data.message || 'Berhasil ditolak', 'success');
        router.reload();
      } else {
        Swal.fire('Error', data.message || 'Gagal menolak', 'error');
      }
    } catch (e) {
      Swal.fire('Error', e.response?.data?.message || 'Gagal menolak', 'error');
    } finally {
      busy.value = false;
    }
  });
}

function confirmDelete() {
  Swal.fire({
    title: 'Hapus pengajuan?',
    text: `Hapus ${props.record.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('overtime-submissions.destroy', props.record.id));
    }
  });
}
</script>
