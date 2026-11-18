<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-calendar-days text-violet-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Detail Tasking Plan · Flow 1 & Flow 2</p>
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
            <i class="fa-solid fa-pen"></i> Edit Flow 1
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

      <!-- Flow 1 -->
      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Flow 1 · Pemberi Tugas</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><div class="text-xs text-gray-500">Nomor TP</div><div class="font-medium">{{ record.number }}</div></div>
          <div><div class="text-xs text-gray-500">Created Date</div><div class="font-medium">{{ formatDateTime(record.created_at) }}</div></div>
          <div><div class="text-xs text-gray-500">Created By</div><div class="font-medium">{{ record.creator?.nama_lengkap || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">PIC</div><div class="font-medium">{{ record.pic?.nama_lengkap || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Brand</div><div class="font-medium">{{ record.brand?.brand || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Platform</div><div class="font-medium">{{ record.platform }}</div></div>
          <div><div class="text-xs text-gray-500">Deadline Content</div><div class="font-medium">{{ formatDate(record.deadline_content) }}</div></div>
          <div><div class="text-xs text-gray-500">Date Post</div><div class="font-medium">{{ formatDate(record.date_post) }}</div></div>
          <div><div class="text-xs text-gray-500">Day</div><div class="font-medium">{{ record.day_name || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Funnel</div><div class="font-medium">{{ record.funnel || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Pillar</div><div class="font-medium">{{ record.pillar || '-' }}</div></div>
          <div><div class="text-xs text-gray-500">Format</div><div class="font-medium">{{ record.format || '-' }}</div></div>
          <div>
            <div class="text-xs text-gray-500">Prioritas</div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="priorityClass(record.priority)">
              {{ priorities[record.priority] || record.priority || '-' }}
            </span>
          </div>
          <div>
            <div class="text-xs text-gray-500">Approval</div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="workflowClass(record.workflow_status)">
              {{ workflowStatuses[record.workflow_status] || record.workflow_status }}
            </span>
          </div>
        </div>

        <div class="mt-4 space-y-4">
          <div>
            <div class="text-xs text-gray-500 mb-1">Objective</div>
            <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.objective || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Brief</div>
            <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.brief || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Reference Link</div>
            <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50 break-all">{{ record.reference || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Reference Image</div>
            <div v-if="(record.reference_images || []).length" class="flex flex-wrap gap-3">
              <a
                v-for="(img, i) in record.reference_images"
                :key="'ri'+i"
                :href="storageUrl(img)"
                target="_blank"
                rel="noopener"
                class="block w-28 h-28 rounded-lg overflow-hidden border border-gray-200"
              >
                <img :src="storageUrl(img)" :alt="'Reference '+ (i + 1)" class="w-full h-full object-cover" />
              </a>
            </div>
            <div v-else class="text-sm text-gray-400">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Caption</div>
            <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.caption || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Remarks</div>
            <div class="text-sm whitespace-pre-wrap">{{ record.remarks || '-' }}</div>
          </div>
        </div>
      </div>

      <!-- Flow 2 PIC -->
      <div class="bg-white rounded-xl shadow p-6 mb-6 border border-violet-100">
        <div class="flex items-start justify-between gap-3 mb-4">
          <div>
            <h2 class="text-sm font-semibold text-gray-800">Flow 2 · Input PIC</h2>
            <p class="text-xs text-gray-500 mt-0.5">Diisi oleh PIC yang ditugaskan. Simpan akan kirim notifikasi ke creator & approver.</p>
          </div>
          <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-semibold" :class="workflowClass(record.workflow_status)">
            {{ (workflowStatuses[record.workflow_status] || record.workflow_status || '-').toUpperCase() }}
          </span>
        </div>

        <form v-if="canEditPic" class="space-y-4" @submit.prevent="submitPic">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Nomor TP</label>
              <input :value="record.number" type="text" readonly class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-gray-50 text-gray-600" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Submit Date</label>
              <input v-model="picForm.pic_submit_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Task</label>
            <textarea v-model="picForm.task" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Deskripsi task..."></textarea>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-gray-700">Final Output <span class="text-xs font-normal text-violet-600">(Revisi 1)</span></label>
              <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-violet-600 hover:text-violet-700" @click="addLink('final_output_links')">
                <i class="fa-solid fa-plus text-xs"></i> Tambah
              </button>
            </div>
            <div class="space-y-2">
              <div v-for="(_, idx) in picForm.final_output_links" :key="'fo-'+idx" class="flex gap-2 items-center">
                <input v-model="picForm.final_output_links[idx]" type="url" placeholder="https://..." class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                <button type="button" class="shrink-0 w-10 h-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200" @click="removeLink('final_output_links', idx)">
                  <i class="fa-solid fa-trash text-sm"></i>
                </button>
              </div>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-gray-700">Actual Post Link <span class="text-xs font-normal text-violet-600">(Revisi Akhir)</span></label>
              <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-violet-600 hover:text-violet-700" @click="addLink('actual_post_links')">
                <i class="fa-solid fa-plus text-xs"></i> Tambah
              </button>
            </div>
            <div class="space-y-2">
              <div v-for="(_, idx) in picForm.actual_post_links" :key="'ap-'+idx" class="flex gap-2 items-center">
                <input v-model="picForm.actual_post_links[idx]" type="url" placeholder="https://..." class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                <button type="button" class="shrink-0 w-10 h-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200" @click="removeLink('actual_post_links', idx)">
                  <i class="fa-solid fa-trash text-sm"></i>
                </button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
            <textarea v-model="picForm.pic_remarks" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Catatan PIC..."></textarea>
          </div>

          <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <div class="font-medium mb-1">Approval</div>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">SUBMITTED</span>
              <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">APPROVED</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Status saat ini: <strong>{{ (workflowStatuses[record.workflow_status] || record.workflow_status || '-').toUpperCase() }}</strong></p>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              class="px-5 py-2.5 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
              :disabled="picForm.processing"
            >
              <i class="fa-solid fa-save mr-1.5"></i>
              {{ picForm.processing ? 'Menyimpan...' : 'Simpan Flow 2' }}
            </button>
          </div>
        </form>

        <div v-else class="space-y-4 text-sm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><div class="text-xs text-gray-500">Nomor TP</div><div class="font-medium">{{ record.number }}</div></div>
            <div><div class="text-xs text-gray-500">Submit Date</div><div class="font-medium">{{ formatDate(record.pic_submit_date) }}</div></div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Task</div>
            <div class="whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.task || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Final Output (Revisi 1)</div>
            <ul v-if="(record.final_output_links || []).length" class="space-y-1">
              <li v-for="(link, i) in record.final_output_links" :key="'fo-view-'+i">
                <a :href="link" target="_blank" rel="noopener" class="text-violet-600 hover:underline break-all">{{ link }}</a>
              </li>
            </ul>
            <div v-else class="text-gray-400">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Actual Post Link (Revisi Akhir)</div>
            <ul v-if="(record.actual_post_links || []).length" class="space-y-1">
              <li v-for="(link, i) in record.actual_post_links" :key="'ap-view-'+i">
                <a :href="link" target="_blank" rel="noopener" class="text-violet-600 hover:underline break-all">{{ link }}</a>
              </li>
            </ul>
            <div v-else class="text-gray-400">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mb-1">Remarks</div>
            <div class="whitespace-pre-wrap">{{ record.pic_remarks || '-' }}</div>
          </div>
          <p v-if="!canEditPic" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            Form Flow 2 hanya bisa diisi oleh PIC yang ditugaskan.
          </p>
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
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canApprove: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canEditPic: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  priorities: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
});

const sortedFlows = computed(() =>
  [...(props.record.approval_flows || [])].sort((a, b) => a.approval_level - b.approval_level)
);

const picForm = useForm({
  pic_submit_date: props.record.pic_submit_date ? String(props.record.pic_submit_date).substring(0, 10) : '',
  task: props.record.task || '',
  final_output_links: (props.record.final_output_links?.length) ? [...props.record.final_output_links] : [''],
  actual_post_links: (props.record.actual_post_links?.length) ? [...props.record.actual_post_links] : [''],
  pic_remarks: props.record.pic_remarks || '',
});

const addLink = (field) => {
  picForm[field].push('');
};

const removeLink = (field, idx) => {
  picForm[field].splice(idx, 1);
  if (!picForm[field].length) picForm[field].push('');
};

const submitPic = () => {
  picForm
    .transform((data) => ({
      ...data,
      final_output_links: (data.final_output_links || []).filter((l) => String(l).trim() !== ''),
      actual_post_links: (data.actual_post_links || []).filter((l) => String(l).trim() !== ''),
    }))
    .post(route('content-plans.pic-update', props.record.id), {
      preserveScroll: true,
    });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');
const formatDateTime = (d) => {
  if (!d) return '-';
  return String(d).replace('T', ' ').substring(0, 19);
};

const storageUrl = (path) => {
  if (!path) return '';
  if (String(path).startsWith('http') || String(path).startsWith('/')) return path;
  return `/storage/${path}`;
};

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};

const priorityClass = (s) => {
  if (s === 'urgent') return 'bg-red-100 text-red-800';
  if (s === 'high') return 'bg-amber-100 text-amber-800';
  if (s === 'medium') return 'bg-blue-100 text-blue-800';
  return 'bg-slate-100 text-slate-700';
};

const flowBadge = (s) => {
  if (s === 'APPROVED') return 'bg-green-100 text-green-800';
  if (s === 'REJECTED') return 'bg-red-100 text-red-800';
  return 'bg-amber-100 text-amber-800';
};

const confirmDelete = async () => {
  const result = await Swal.fire({
    title: 'Hapus Tasking Plan?',
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
    title: 'Approve Tasking Plan?',
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
    title: 'Tolak Tasking Plan',
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
