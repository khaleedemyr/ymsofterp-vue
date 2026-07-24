<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Detail Pengajuan WFH</h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.number }}</p>
        </div>
        <Link
          :href="route('wfh-requests.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
        >
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(record.status)">
            {{ statusLabel(record.status) }}
          </span>
          <span v-if="record.att_log_written_at" class="text-xs text-teal-700 bg-teal-50 px-2 py-1 rounded">
            Absensi tercatat · {{ formatDateTime(record.att_log_written_at) }}
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-xs text-gray-500">Nama</div>
            <div class="font-medium">{{ record.user?.nama_lengkap || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Jabatan</div>
            <div class="font-medium">{{ record.user?.jabatan?.nama_jabatan || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Divisi</div>
            <div class="font-medium">{{ record.user?.divisi?.nama_divisi || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Tanggal WFH</div>
            <div class="font-medium">{{ formatDate(record.wfh_date) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Shift</div>
            <div class="font-medium">
              {{ record.shift_name || '-' }}
              ({{ formatTime(record.time_start) }} – {{ formatTime(record.time_end) }})
            </div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Diajukan oleh</div>
            <div class="font-medium">{{ record.creator?.nama_lengkap || '-' }}</div>
          </div>
          <div class="md:col-span-2">
            <div class="text-xs text-gray-500">Alasan WFH</div>
            <div class="font-medium whitespace-pre-wrap">{{ record.reason }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">List yang dikerjakan</h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-800">
          <li v-for="task in record.tasks || []" :key="task.id">{{ task.description }}</li>
        </ol>
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
              <div class="text-xs font-semibold text-teal-700">Level {{ flow.approval_level }}</div>
              <div class="font-medium">{{ flow.approver?.nama_lengkap || '-' }}</div>
              <div v-if="flow.comments" class="text-xs text-gray-500 mt-1">{{ flow.comments }}</div>
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
          class="px-5 py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50"
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
});

const busy = ref(false);

const sortedFlows = computed(() => {
  const flows = props.record.approval_flows || [];
  return [...flows].sort((a, b) => (Number(a.approval_level) || 0) - (Number(b.approval_level) || 0));
});

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID');
}

function formatTime(value) {
  if (!value) return '-';
  return String(value).substring(0, 5);
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
    const { data } = await axios.post(`/api/wfh-requests/${props.record.id}/approve`, { comments: '' });
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
    title: 'Tolak Pengajuan WFH',
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
      const { data } = await axios.post(`/api/wfh-requests/${props.record.id}/reject`, {
        rejection_reason: result.value || '',
      });
      if (data.success) {
        await Swal.fire('Success', data.message || 'Pengajuan ditolak', 'success');
        router.reload();
      } else {
        Swal.fire('Error', data.message || 'Gagal reject', 'error');
      }
    } catch (e) {
      Swal.fire('Error', e.response?.data?.message || 'Gagal reject', 'error');
    } finally {
      busy.value = false;
    }
  });
}
</script>
