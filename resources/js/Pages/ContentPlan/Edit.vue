<template>
  <AppLayout>
    <div class="w-full max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Edit Tasking Plan</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ record.number }} · {{ record.workflow_status }}</p>
        </div>
        <Link
          :href="route('content-plans.show', record.id)"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <FormFields
          :form="form"
          :brands="brands"
          :pic-users="picUsers"
          :platforms="platforms"
          :funnels="funnels"
          :pillars="pillars"
          :formats="formats"
          :priorities="priorities"
          :meta="editMeta"
        />

        <div class="mt-5 flex justify-end gap-2.5 sticky bottom-0 z-10 py-3 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent">
          <Link
            :href="route('content-plans.show', record.id)"
            class="px-4 py-2.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            class="px-5 py-2.5 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50 shadow-sm"
            :disabled="form.processing"
          >
            <i class="fa-solid fa-save mr-1.5"></i>
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
import { computed } from 'vue';

const props = defineProps({
  record: { type: Object, required: true },
  brands: { type: Array, default: () => [] },
  picUsers: { type: Array, default: () => [] },
  platforms: { type: Array, default: () => [] },
  funnels: { type: Array, default: () => [] },
  pillars: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
});

const editMeta = computed(() => ({
  number: props.record.number,
  created_at: props.record.created_at,
  created_by_name: props.record.creator?.nama_lengkap || '',
}));

const form = useForm({
  brand_id: props.record.brand_id || '',
  pic_user_id: props.record.pic_user_id || '',
  platform: props.record.platform || '',
  deadline_content: props.record.deadline_content ? String(props.record.deadline_content).substring(0, 10) : '',
  date_post: props.record.date_post ? String(props.record.date_post).substring(0, 10) : '',
  funnel: props.record.funnel || '',
  pillar: props.record.pillar || '',
  format: props.record.format || '',
  objective: props.record.objective || '',
  brief: props.record.brief || '',
  reference: props.record.reference || '',
  reference_images: [],
  existing_reference_images: [...(props.record.reference_images || [])],
  caption: props.record.caption || '',
  priority: props.record.priority || 'medium',
  remarks: props.record.remarks || '',
  approvers: (props.record.approval_flows || []).map((f) => ({
    id: f.approver_id || f.approver?.id,
    nama_lengkap: f.approver?.nama_lengkap,
    name: f.approver?.nama_lengkap,
    email: f.approver?.email,
    jabatan: null,
  })),
  _method: 'put',
});

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      approvers: data.approvers.map((a) => a.id),
      existing_reference_images_json: JSON.stringify(data.existing_reference_images || []),
    }))
    .post(route('content-plans.update', props.record.id), {
      forceFormData: true,
    });
};
</script>
