<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Edit Marcomm Tracker</h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.number }} · Workflow: {{ record.workflow_status }}</p>
        </div>
        <Link
          :href="route('marcomm-trackers.show', record.id)"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
        >
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <FormFields
          :form="form"
          :job-categories="jobCategories"
          :job-statuses="jobStatuses"
          :pic-users="picUsers"
        />

        <div class="mt-6 flex justify-end gap-3">
          <Link
            :href="route('marcomm-trackers.show', record.id)"
            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            class="px-5 py-2 rounded-lg bg-pink-600 text-white hover:bg-pink-700 disabled:opacity-50"
            :disabled="form.processing"
          >
            <i class="fa-solid fa-save mr-1"></i>
            {{ form.processing ? 'Menyimpan...' : 'Update' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FormFields from './FormFields.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  record: { type: Object, required: true },
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  picUsers: { type: Array, default: () => [] },
});

const remarks = props.record.status_remarks || {};
const form = useForm({
  job_category: props.record.job_category || '',
  pic_user_id: props.record.pic_user_id || '',
  task: props.record.task || '',
  due_date: props.record.due_date ? String(props.record.due_date).substring(0, 10) : '',
  job_status: props.record.job_status || 'on_progress',
  status_remarks: {
    on_progress: remarks.on_progress || '',
    rev_1: remarks.rev_1 || '',
    rev_2: remarks.rev_2 || '',
  },
  output_links: (props.record.output_links && props.record.output_links.length)
    ? [...props.record.output_links]
    : [''],
  remarks: props.record.remarks || '',
  approvers: (props.record.approval_flows || []).map((f) => ({
    id: f.approver_id || f.approver?.id,
    nama_lengkap: f.approver?.nama_lengkap,
    name: f.approver?.nama_lengkap,
    email: f.approver?.email,
    jabatan: null,
  })),
});

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      approvers: data.approvers.map((a) => a.id),
      output_links: (data.output_links || []).filter((l) => String(l).trim() !== ''),
    }))
    .put(route('marcomm-trackers.update', props.record.id));
};
</script>
