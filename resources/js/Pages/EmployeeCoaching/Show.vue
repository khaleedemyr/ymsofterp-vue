<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-graduate text-blue-600"></i>
            Detail Employee Coaching
          </h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.employee_name }}</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('employee-coaching.index')" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
          </Link>
          <Link
            :href="route('employee-coaching.edit', record.id)"
            class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
          >
            <i class="fa-solid fa-pen mr-1"></i> Edit
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Informasi Karyawan</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-gray-500">Nama Karyawan</div>
            <div class="font-semibold text-gray-900">{{ record.employee_name || '-' }}</div>
          </div>
          <div>
            <div class="text-gray-500">Outlet</div>
            <div class="font-semibold text-gray-900">{{ record.outlet_name || '-' }}</div>
          </div>
          <div>
            <div class="text-gray-500">Jabatan</div>
            <div class="font-semibold text-gray-900">{{ record.jabatan_name || '-' }}</div>
          </div>
          <div>
            <div class="text-gray-500">Divisi</div>
            <div class="font-semibold text-gray-900">{{ record.division_name || '-' }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 space-y-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Point of Concern, Issue, or Incident involving</h2>
          <p class="text-sm italic text-gray-500">Hal yang diperhatikan, masalah / kendala, keterlibatan kejadian</p>
        </div>

        <div v-if="!record.concerns?.length" class="text-sm text-gray-500">Tidak ada concern.</div>

        <div
          v-for="item in record.concerns"
          :key="item.id"
          class="border border-gray-200 rounded-lg p-4"
        >
          <div class="font-medium text-gray-800">{{ concernLabel(item.concern_code) }}</div>
          <div class="text-sm italic text-gray-500 mb-2">{{ concernSubLabel(item.concern_code) }}</div>
          <div v-if="item.concern_code === 'other' && item.other_label" class="text-sm text-gray-700 mb-2">
            <span class="font-medium">Lain-Lain:</span> {{ item.other_label }}
          </div>
          <div class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-3">{{ item.comment }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Describe specific performance concern or issue</h2>
        <p class="text-sm italic text-gray-500 mb-3">Jelaskan masalah atau kinerja yang menjadi perhatian khusus</p>
        <div class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-4 min-h-[80px]">
          {{ record.performance_description || '-' }}
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 space-y-3">
        <div>
          <h2 class="text-sm font-semibold text-gray-800 mb-1">Action taken to improve performance & Due Date</h2>
          <p class="text-sm italic text-gray-500 mb-3">Tindak lanjut perbaikan kinerja & batas waktu</p>
          <div class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-4 min-h-[80px]">
            {{ record.action_taken || '-' }}
          </div>
        </div>
        <div>
          <div class="text-gray-500 text-sm">Due Date</div>
          <div class="font-semibold">{{ formatDate(record.action_due_date) }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-1">Performance review plan date</h2>
        <p class="text-sm italic text-gray-500 mb-3">Tanggal rencana peninjauan kinerja</p>
        <div class="font-semibold text-gray-900">{{ formatDate(record.performance_review_plan_date) }}</div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <div class="text-gray-500">Created At</div>
          <div class="font-semibold">{{ formatDateTime(record.created_at) }}</div>
        </div>
        <div>
          <div class="text-gray-500">Created By</div>
          <div class="font-semibold">{{ record.creator?.nama_lengkap || record.creator?.name || '-' }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  record: { type: Object, required: true },
  concernOptions: { type: Array, default: () => [] },
});

const concernMap = Object.fromEntries(
  props.concernOptions.map((item) => [item.code, item])
);

function concernLabel(code) {
  return concernMap[code]?.label_en || code;
}

function concernSubLabel(code) {
  return concernMap[code]?.label_id || '';
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
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
</script>
