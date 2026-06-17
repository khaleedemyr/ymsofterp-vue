<template>
  <div>
    <div class="mb-2 flex items-center justify-between">
      <h3 class="text-sm font-bold uppercase tracking-wide text-orange-700 bg-orange-100 inline-block px-3 py-1 rounded">
        {{ title }}
      </h3>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-300 shadow">
      <table class="min-w-[1400px] w-full text-xs border-collapse">
        <thead>
          <tr class="bg-orange-400 text-black">
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-left min-w-[90px]">Cabang</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-left min-w-[160px]">Posisi</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-left">Area</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-left">PIC</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-center">Kebutuhan</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-center">Tgl Mulai</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-center">Target Fulfill</th>
            <th colspan="10" class="border border-orange-500 px-2 py-1 text-center font-bold">PROGRESS (auto dari pelamar)</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-center">Tgl Join</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 text-left min-w-[120px]">Keterangan</th>
            <th rowspan="2" class="border border-orange-500 px-2 py-2 w-24"></th>
          </tr>
          <tr class="bg-orange-300 text-black">
            <th class="border border-orange-500 px-1 py-1">Sourcing</th>
            <th class="border border-orange-500 px-1 py-1">Scr CV OK</th>
            <th class="border border-orange-500 px-1 py-1">Scr CV NOK</th>
            <th class="border border-orange-500 px-1 py-1">HR OK</th>
            <th class="border border-orange-500 px-1 py-1">HR NOK</th>
            <th class="border border-orange-500 px-1 py-1 text-[10px]">Ket. HR</th>
            <th class="border border-orange-500 px-1 py-1">User OK</th>
            <th class="border border-orange-500 px-1 py-1">User NOK</th>
            <th class="border border-orange-500 px-1 py-1 text-[10px]">Ket. User</th>
            <th class="border border-orange-500 px-1 py-1">LOI</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            :class="row.recruitment_config?.is_hold ? 'bg-gray-200 text-gray-600' : 'bg-white'"
          >
            <td class="border border-gray-300 px-2 py-2">{{ branchLabel(row.job_scope) }}</td>
            <td class="border border-gray-300 px-2 py-2 font-semibold">{{ row.position }}</td>
            <td class="border border-gray-300 px-2 py-2">{{ row.location }}</td>
            <td class="border border-gray-300 px-2 py-2">{{ row.recruitment_config?.pic || '-' }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center font-bold">
              {{ row.recruitment_config?.is_hold ? 'HOLD' : (row.recruitment_config?.headcount_needed ?? '-') }}
            </td>
            <td class="border border-gray-300 px-2 py-2 text-center whitespace-nowrap">{{ fmtDate(row.recruitment_config?.search_start_date) }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center whitespace-nowrap">{{ fmtDate(row.recruitment_config?.target_fulfill_date) }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center font-semibold">{{ count(row, 'sourcing') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'screening_cv_ok') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'screening_cv_nok') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'hr_interview_ok') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'hr_interview_nok') }}</td>
            <td class="border border-gray-300 px-2 py-1 text-[10px] align-top">{{ row.recruitment_config?.hr_interview_notes || '' }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'user_interview_ok') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center">{{ count(row, 'user_interview_nok') }}</td>
            <td class="border border-gray-300 px-2 py-1 text-[10px] align-top">{{ row.recruitment_config?.user_interview_notes || '' }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center font-semibold">{{ count(row, 'loi') }}</td>
            <td class="border border-gray-300 px-2 py-2 text-center whitespace-nowrap">{{ fmtDate(row.join_date) }}</td>
            <td class="border border-gray-300 px-2 py-1 text-[10px] align-top">{{ row.recruitment_config?.final_notes || '' }}</td>
            <td class="border border-gray-300 px-2 py-2 whitespace-nowrap">
              <button type="button" class="text-blue-600 hover:underline mr-2" @click="$emit('edit-config', row)">Config</button>
              <button type="button" class="text-indigo-600 hover:underline" @click="$emit('view-applicants', row)">Pelamar</button>
            </td>
          </tr>
          <tr v-if="!rows?.length">
            <td colspan="19" class="border border-gray-300 px-4 py-8 text-center text-gray-400">Tidak ada data</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
});

defineEmits(['edit-config', 'view-applicants']);

function branchLabel(scope) {
  return scope === 'head_office' ? 'Head Office' : 'Outlet';
}

function count(row, key) {
  return row.stage_counts?.[key] ?? 0;
}

function fmtDate(value) {
  if (!value) return '';
  const s = typeof value === 'string' ? value.slice(0, 10) : '';
  if (!s) return '';
  const [y, m, d] = s.split('-');
  return `${d}/${m}/${y}`;
}
</script>
