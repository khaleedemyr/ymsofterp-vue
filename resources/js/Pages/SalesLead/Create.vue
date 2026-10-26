<template>
  <AppLayout>
    <div class="w-full max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Buat Sales Lead</h1>
          <p class="text-sm text-gray-500 mt-0.5">Isi detail lead. Sales otomatis dari user login.</p>
        </div>
        <Link
          :href="route('sales-leads.index')"
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
            :href="route('sales-leads.index')"
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
  segment1Options: { type: Array, default: () => [] },
  segment2Options: { type: Array, default: () => [] },
  typeOfEvents: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  salesUser: { type: Object, default: () => ({}) },
});

const form = useForm({
  company_name: '',
  segment_1: '',
  segment_2: '',
  type_of_event: '',
  event_date: '',
  pax: '',
  outlet_id: '',
  status: 'new_leads',
  revenue: '',
  remarks: '',
});

const submit = () => {
  form.post(route('sales-leads.store'));
};
</script>
