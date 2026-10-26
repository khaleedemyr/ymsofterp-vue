<template>
  <AppLayout>
    <div class="w-full max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Edit Sales Lead</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ record.number }} · {{ record.company_name }}</p>
        </div>
        <Link
          :href="route('sales-leads.show', record.id)"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <FormFields
          :form="form"
          :segment1-options="segment1Options"
          :segment2-options="segment2Options"
          :type-of-events="typeOfEvents"
          :statuses="statuses"
          :outlets="outlets"
          :sales-user="salesUser"
        />

        <div class="mt-5 flex justify-end gap-2.5 sticky bottom-0 z-10 py-3 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent">
          <Link
            :href="route('sales-leads.show', record.id)"
            class="px-4 py-2.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            class="px-5 py-2.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 shadow-sm"
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

const props = defineProps({
  record: { type: Object, required: true },
  segment1Options: { type: Array, default: () => [] },
  segment2Options: { type: Array, default: () => [] },
  typeOfEvents: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  salesUser: { type: Object, default: () => ({}) },
});

const form = useForm({
  company_name: props.record.company_name || '',
  segment_1: props.record.segment_1 || '',
  segment_2: props.record.segment_2 || '',
  type_of_event: props.record.type_of_event || '',
  event_date: props.record.event_date ? String(props.record.event_date).substring(0, 10) : '',
  pax: props.record.pax ?? '',
  outlet_id: props.record.outlet_id || '',
  status: props.record.status || 'new_leads',
  revenue: props.record.revenue ?? '',
  remarks: props.record.remarks || '',
});

const submit = () => {
  form.put(route('sales-leads.update', props.record.id));
};
</script>
