<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Buat Marcomm Tracker</h1>
          <p class="text-sm text-gray-500 mt-1">Isi job, PIC, status, output link, dan approver.</p>
        </div>
        <Link
          :href="route('marcomm-trackers.index')"
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
            :href="route('marcomm-trackers.index')"
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
            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
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
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  picUsers: { type: Array, default: () => [] },
});

const form = useForm({
  job_category: '',
  pic_user_id: '',
  task: '',
  due_date: '',
  job_status: 'on_progress',
  status_remarks: { on_progress: '', rev_1: '', rev_2: '' },
  output_links: [''],
  remarks: '',
  approvers: [],
});

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      approvers: data.approvers.map((a) => a.id),
      output_links: (data.output_links || []).filter((l) => String(l).trim() !== ''),
    }))
    .post(route('marcomm-trackers.store'));
};
</script>
