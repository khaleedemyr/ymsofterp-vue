<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-calendar-days text-violet-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Detail Content Plan</p>
        </div>
        <div class="flex gap-2">
          <Link
            :href="route('content-plans.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
          <Link
            v-if="canEdit"
            :href="route('content-plans.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <button
            v-if="canDelete"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"
            @click="confirmDelete"
          >
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><div class="text-xs text-gray-500">Platform</div><div class="font-medium">{{ record.platform }}</div></div>
          <div><div class="text-xs text-gray-500">Date Post</div><div class="font-medium">{{ formatDate(record.date_post) }}</div></div>
          <div><div class="text-xs text-gray-500">Day</div><div class="font-medium">{{ record.day_name || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Funnel</div><div class="font-medium">{{ record.funnel || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Pillar</div><div class="font-medium">{{ record.pillar || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Format</div><div class="font-medium">{{ record.format || '-' }}</div></div>
          <div>
            <div class="text-xs text-gray-500">Status</div>
            <div class="font-medium">{{ jobStatuses[record.job_status] || record.job_status }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Workflow</div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="workflowClass(record.workflow_status)">
              {{ workflowStatuses[record.workflow_status] || record.workflow_status }}
            </span>
          </div>
          <div><div class="text-xs text-gray-500">Actual Post Date</div><div class="font-medium">{{ formatDate(record.actual_post_date) }}</div></div>
          <div><div class="text-xs text-gray-500">Created By</div><div class="font-medium">{{ record.creator?.nama_lengkap || '-' }}</div></div>
        </div>

        <div class="mt-4 space-y-4">
          <div>
            <div class="text-xs text-gray-500 mb-1">Objective</div>
            <div class="prose prose-sm max-w-none border rounded-lg p-3 bg-gray-50" v-html="record.objective || '-'"></div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Brief</div>
            <div class="prose prose-sm max-w-none border rounded-lg p-3 bg-gray-50" v-html="record.brief || '-'"></div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Reference</div>
            <div class="prose prose-sm max-w-none border rounded-lg p-3 bg-gray-50" v-html="record.reference || '-'"></div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Caption</div>
            <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.caption || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Final Output</div>
            <ul v-if="(record.final_output_links || []).length" class="space-y-1">
              <li v-for="(link, i) in record.final_output_links" :key="'fo'+i">
                <a :href="link" target="_blank" rel="noopener" class="text-violet-600 hover:underline break-all">{{ link }}</a>
              </li>
            </ul>
            <div v-else class="text-sm text-gray-400">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Actual Post Link</div>
            <ul v-if="(record.actual_post_links || []).length" class="space-y-1">
              <li v-for="(link, i) in record.actual_post_links" :key="'ap'+i">
                <a :href="link" target="_blank" rel="noopener" class="text-violet-600 hover:underline break-all">{{ link }}</a>
              </li>
            </ul>
            <div v-else class="text-sm text-gray-400">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Remarks</div>
            <div class="text-sm whitespace-pre-wrap">{{ record.remarks || '-' }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Approval Flow</h2>
        <div v-if="!(record.approval_flows || []).length" class="text-sm text-gray-500">Belum ada approver.</div>
        <ul v-else class="space-y-3">
          <li v-for="flow in sortedFlows" :key="flow.id" class="flex items-start justify-between border rounded-lg px-4 py-3">
            <div>
              <div class="font-medium text-sm">Level {{ flow.approval_level }} · {{ flow.approver?.nama_lengkap || '-' }}</div>
              <div v-if="flow.notes" class="text-xs text-gray-500 mt-1">{{ flow.notes }}</div>
            </div>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="flowBadge(flow.status)">{{ flow.status }}</span>
          </li>
        </ul>

        <div v-if="canApprove" class="mt-4 flex gap-2">
          <button type="button" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700" @click="doApprove">
            <i class="fa-solid fa-check mr-1"></i>Approve
          </button>
          <button type="button" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700" @click="doReject">
            <i class="fa-solid fa-times mr-1"></i>Reject
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canApprove: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  jobStatuses: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
});

const sortedFlows = computed(() =>
  [...(props.record.approval_flows || [])].sort((a, b) => a.approval_level - b.approval_level)
);

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};

const flowBadge = (s) => {
  if (s === 'APPROVED') return 'bg-green-100 text-green-800';
  if (s === 'REJECTED') return 'bg-red-100 text-red-800';
  return 'bg-amber-100 text-amber-800';
};

const confirmDelete = async () => {
  const result = await Swal.fire({
    title: 'Hapus Content Plan?',
    text: props.record.number,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!result.isConfirmed) return;
  router.delete(route('content-plans.destroy', props.record.id));
};

const doApprove = async () => {
  const result = await Swal.fire({
    title: 'Approve Content Plan?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#16a34a',
  });
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.post(`/content-plans/${props.record.id}/approve`, { notes: '' });
    if (data.success) {
      await Swal.fire('Success', data.message, 'success');
      router.reload();
    } else {
      Swal.fire('Error', data.message || 'Gagal approve', 'error');
    }
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal approve', 'error');
  }
};

const doReject = async () => {
  const result = await Swal.fire({
    title: 'Tolak Content Plan',
    input: 'textarea',
    inputLabel: 'Alasan penolakan',
    showCancelButton: true,
    confirmButtonText: 'Tolak',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    inputValidator: (v) => (!v ? 'Alasan wajib diisi' : null),
  });
  if (!result.isConfirmed) return;
  try {
    const { data } = await axios.post(`/content-plans/${props.record.id}/reject`, {
      rejection_reason: result.value,
    });
    if (data.success) {
      await Swal.fire('Success', data.message, 'success');
      router.reload();
    } else {
      Swal.fire('Error', data.message || 'Gagal reject', 'error');
    }
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal reject', 'error');
  }
};
</script>
