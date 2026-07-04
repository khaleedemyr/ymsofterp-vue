<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">{{ isEdit ? 'Edit Template' : 'Buat Template' }}</h1>
          <p class="text-sm text-gray-500 mt-1">Atur minggu, area, checklist, dan default approver</p>
        </div>
        <Link :href="route('onboarding-templates.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Code *</label>
            <input v-model="form.code" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Template *</label>
            <input v-model="form.name" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Total Minggu *</label>
            <input v-model.number="form.total_weeks" type="number" min="1" max="52" required class="w-full rounded-lg border-gray-300" @change="syncWeekCount" />
          </div>
          <div class="md:col-span-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
            <input v-model="form.notes" class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm">
              <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
              Aktif
            </label>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6">
          <div class="border-b px-6 py-3 flex gap-4">
            <button type="button" :class="tab === 'structure' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500'" class="pb-2 font-semibold" @click="tab = 'structure'">Struktur Checklist</button>
            <button type="button" :class="tab === 'approvers' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500'" class="pb-2 font-semibold" @click="tab = 'approvers'">Default Approver</button>
          </div>

          <div v-show="tab === 'structure'" class="p-6 space-y-6">
            <div v-for="(week, wi) in form.weeks" :key="wi" class="border rounded-xl overflow-hidden">
              <div class="bg-indigo-50 px-4 py-3 flex items-center justify-between">
                <div class="font-semibold text-indigo-900">Minggu {{ week.week_number }} — {{ week.week_label || 'Tanpa label' }}</div>
              </div>
              <div class="p-4 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <input v-model="week.week_label" placeholder="Label minggu (opsional)" class="rounded-lg border-gray-300" />
                </div>
                <div v-for="(area, ai) in week.areas" :key="ai" class="border rounded-lg p-4">
                  <div class="flex items-center gap-2 mb-3">
                    <input v-model="area.area_name" required placeholder="Nama area *" class="flex-1 rounded-lg border-gray-300 font-semibold" />
                    <button type="button" class="text-red-500" @click="week.areas.splice(ai, 1)"><i class="fa-solid fa-trash"></i></button>
                  </div>
                  <div class="space-y-2">
                    <div v-for="(item, ii) in area.items" :key="ii" class="grid grid-cols-1 md:grid-cols-12 gap-2 items-start">
                      <textarea v-model="item.checklist_text" required rows="2" placeholder="Checklist *" class="md:col-span-8 rounded-lg border-gray-300 text-sm"></textarea>
                      <input v-model="item.pic_role_hint" placeholder="PIC hint" class="md:col-span-3 rounded-lg border-gray-300 text-sm" />
                      <button type="button" class="md:col-span-1 text-red-500" @click="area.items.splice(ii, 1)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <button type="button" class="text-sm text-indigo-600" @click="area.items.push(emptyItem())"><i class="fa-solid fa-plus"></i> Tambah Checklist</button>
                  </div>
                </div>
                <button type="button" class="text-sm text-indigo-600" @click="week.areas.push(emptyArea())"><i class="fa-solid fa-plus"></i> Tambah Area</button>
              </div>
            </div>
          </div>

          <div v-show="tab === 'approvers'" class="p-6">
            <p class="text-sm text-gray-500 mb-4">Default approver per minggu. Bisa di-override saat submit minggu di instance onboarding.</p>
            <div v-for="weekNum in form.total_weeks" :key="weekNum" class="mb-6 border rounded-lg p-4">
              <div class="font-semibold mb-3">Minggu {{ weekNum }}</div>
              <div v-for="(approver, idx) in approversForWeek(weekNum)" :key="idx" class="flex items-center gap-2 mb-2">
                <span class="w-8 text-center text-xs font-bold bg-gray-100 rounded">{{ idx + 1 }}</span>
                <select v-model="approver.approver_user_id" class="flex-1 rounded-lg border-gray-300">
                  <option value="">Pilih approver</option>
                  <option v-for="user in userOptions" :key="user.id" :value="user.id">{{ user.name }} — {{ user.jabatan || user.email }}</option>
                </select>
                <button type="button" class="text-red-500" @click="removeApprover(weekNum, idx)"><i class="fa-solid fa-trash"></i></button>
              </div>
              <button type="button" class="text-sm text-indigo-600" @click="addApprover(weekNum)"><i class="fa-solid fa-plus"></i> Tambah Approver</button>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('onboarding-templates.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
            Simpan Template
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  record: { type: Object, default: null },
});

const isEdit = computed(() => Boolean(props.record?.id));
const tab = ref('structure');
const userOptions = ref([]);

function emptyItem() {
  return { checklist_text: '', pic_role_hint: '' };
}

function emptyArea() {
  return { area_name: '', items: [emptyItem()] };
}

function buildWeeks(count, existing = []) {
  const weeks = [];
  for (let i = 1; i <= count; i++) {
    const found = existing.find((w) => Number(w.week_number) === i);
    weeks.push(found ? JSON.parse(JSON.stringify(found)) : {
      week_number: i,
      week_label: '',
      areas: [emptyArea()],
    });
  }
  return weeks;
}

const form = useForm({
  code: props.record?.code || '',
  name: props.record?.name || '',
  total_weeks: props.record?.total_weeks || 8,
  is_active: props.record?.is_active ?? true,
  notes: props.record?.notes || '',
  weeks: buildWeeks(props.record?.total_weeks || 8, props.record?.weeks || []),
  week_approvers: props.record?.week_approvers || [],
});

function syncWeekCount() {
  form.weeks = buildWeeks(form.total_weeks, form.weeks);
}

function approversForWeek(weekNum) {
  return form.week_approvers.filter((row) => Number(row.week_number) === Number(weekNum));
}

function addApprover(weekNum) {
  form.week_approvers.push({
    week_number: weekNum,
    approver_user_id: '',
    approval_level: approversForWeek(weekNum).length + 1,
  });
  reindexApprovers(weekNum);
}

function removeApprover(weekNum, idx) {
  const rows = approversForWeek(weekNum);
  const target = rows[idx];
  form.week_approvers = form.week_approvers.filter((row) => row !== target);
  reindexApprovers(weekNum);
}

function reindexApprovers(weekNum) {
  approversForWeek(weekNum).forEach((row, index) => {
    row.approval_level = index + 1;
  });
}

async function loadUsers() {
  const { data } = await axios.get(route('onboarding-templates.search-users'));
  userOptions.value = data.users || [];
}

function submit() {
  const payload = {
    ...form.data(),
    week_approvers: form.week_approvers.filter((row) => row.approver_user_id),
  };

  if (isEdit.value) {
    form.transform(() => payload).put(route('onboarding-templates.update', props.record.id));
  } else {
    form.transform(() => payload).post(route('onboarding-templates.store'));
  }
}

onMounted(loadUsers);
</script>
