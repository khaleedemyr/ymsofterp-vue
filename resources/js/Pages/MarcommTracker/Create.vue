<template>
  <AppLayout>
    <div class="w-full max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Buat Marcomm Tracker</h1>
          <p class="text-sm text-gray-500 mt-0.5">Isi detail job dan approval flow.</p>
        </div>
        <Link
          :href="route('marcomm-trackers.index')"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <FormFields
          :form="form"
          :job-categories="jobCategories"
          :job-statuses="jobStatuses"
          :pic-users="picUsers"
        />

        <div class="mt-5 flex justify-end gap-2.5 sticky bottom-0 py-3 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent">
          <Link
            :href="route('marcomm-trackers.index')"
            class="px-4 py-2.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            class="px-5 py-2.5 rounded-lg text-sm font-medium bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-50 shadow-sm"
            :disabled="form.processing"
          >
            <i class="fa-solid fa-save mr-1.5"></i>
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

defineProps({
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
