<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Buat Employee Onboarding</h1>
          <p class="text-sm text-gray-500 mt-1">Pilih karyawan, template, dan assign PIC per checklist</p>
        </div>
        <Link :href="route('employee-onboarding.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Template *</label>
            <select v-model="form.template_id" required class="w-full rounded-lg border-gray-300" @change="loadTemplate">
              <option value="">Pilih template</option>
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }} ({{ tpl.total_weeks }} minggu)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Karyawan *</label>
            <OnboardingUserSelect
              v-model="form.employee_user_id"
              search-route="employee-onboarding.search-employees"
              placeholder="Cari karyawan..."
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet</label>
            <select v-model="form.outlet_id" class="w-full rounded-lg border-gray-300">
              <option value="">Opsional</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Mulai *</label>
            <input v-model="form.start_date" type="date" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
            <input v-model="form.notes" class="w-full rounded-lg border-gray-300" />
          </div>
        </div>

        <div v-if="templateStructure" class="bg-white rounded-xl shadow mb-6 overflow-hidden">
          <div class="px-6 py-4 border-b bg-indigo-50 font-semibold text-indigo-900">Assign PIC Checklist</div>
          <div class="p-6 space-y-6">
            <div v-for="week in templateStructure.weeks" :key="week.week_number" class="border rounded-xl overflow-hidden">
              <div class="px-4 py-3 bg-gray-50 font-semibold">Minggu {{ week.week_number }} — {{ week.week_label || '-' }}</div>
              <div v-for="area in week.areas" :key="area.id || area.area_name" class="p-4 border-t">
                <div class="flex flex-col md:flex-row md:items-center gap-3 mb-3">
                  <div class="font-medium text-gray-800">{{ area.area_name }}</div>
                  <div class="flex-1"></div>
                  <div class="flex items-center gap-2">
                    <OnboardingUserSelect
                      v-model="bulkPic[`${week.week_number}::${area.area_name}`]"
                      search-route="employee-onboarding.search-users"
                      placeholder="Cari nama PIC (bulk)..."
                      class="min-w-[260px]"
                      @user-selected="cachePicUser"
                    />
                    <button type="button" class="px-3 py-1.5 rounded-lg bg-indigo-100 text-indigo-700 text-sm" @click="applyBulkPic(week.week_number, area.area_name)">Terapkan</button>
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 mb-2 px-1 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  <div class="md:col-span-7">Checklist</div>
                  <div class="md:col-span-2">Role Hint</div>
                  <div class="md:col-span-3">PIC (Nama)</div>
                </div>
                <div v-for="item in area.items" :key="item.id" class="grid grid-cols-1 md:grid-cols-12 gap-2 mb-2 items-center">
                  <div class="md:col-span-7 text-sm">{{ item.checklist_text }}</div>
                  <div class="md:col-span-2 text-xs text-gray-500">{{ item.pic_role_hint || '-' }}</div>
                  <OnboardingUserSelect
                    v-model="assignments[item.id]"
                    search-route="employee-onboarding.search-users"
                    placeholder="Cari nama PIC..."
                    class="md:col-span-3"
                    :initial-user="picUserCache[assignments[item.id]] || null"
                    @user-selected="cachePicUser"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('employee-onboarding.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="form.processing || !form.template_id" class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import OnboardingUserSelect from '@/Components/EmployeeOnboarding/OnboardingUserSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';

defineProps({
  templates: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
});

const templateStructure = ref(null);
const assignments = reactive({});
const bulkPic = reactive({});
const picUserCache = reactive({});

function cachePicUser(user) {
  if (!user?.id) return;
  picUserCache[user.id] = { id: user.id, name: user.name, jabatan: user.jabatan };
}

const form = useForm({
  template_id: '',
  employee_user_id: '',
  outlet_id: '',
  start_date: new Date().toISOString().slice(0, 10),
  notes: '',
});

async function loadTemplate() {
  if (!form.template_id) {
    templateStructure.value = null;
    return;
  }
  const { data } = await axios.get(route('employee-onboarding.template-structure', form.template_id));
  templateStructure.value = data.template;
  Object.keys(assignments).forEach((key) => delete assignments[key]);
}

function applyBulkPic(weekNumber, areaName) {
  const userId = bulkPic[`${weekNumber}::${areaName}`];
  if (!userId || !templateStructure.value) return;
  const week = templateStructure.value.weeks.find((w) => Number(w.week_number) === Number(weekNumber));
  const area = week?.areas.find((a) => a.area_name === areaName);
  area?.items.forEach((item) => {
    assignments[item.id] = userId;
  });
}

function submit() {
  const item_assignments = Object.entries(assignments)
    .filter(([, userId]) => userId)
    .map(([templateItemId, assignedPicUserId]) => ({
      template_item_id: Number(templateItemId),
      assigned_pic_user_id: Number(assignedPicUserId),
    }));

  form.transform((data) => ({ ...data, item_assignments })).post(route('employee-onboarding.store'));
}
</script>
