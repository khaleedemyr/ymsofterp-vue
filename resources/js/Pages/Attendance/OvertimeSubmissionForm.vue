<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Buat Pengajuan Lembur</h1>
          <p class="text-sm text-gray-500 mt-1">Satu record bisa berisi beberapa nama karyawan</p>
        </div>
        <Link :href="route('overtime-submissions.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Pengajuan *</label>
            <input v-model="form.submission_date" type="date" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Filter Outlet (opsional)</label>
            <select v-model="selectedOutletId" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
            </select>
          </div>
          <div class="md:col-span-1">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
            <input v-model="form.notes" class="w-full rounded-lg border-gray-300" />
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
          <div class="px-6 py-4 border-b bg-indigo-50 font-semibold text-indigo-900 flex items-center justify-between">
            <span>Daftar Karyawan Lembur</span>
            <button type="button" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm" @click="addRow">
              <i class="fa-solid fa-plus mr-1"></i> Tambah Baris
            </button>
          </div>

          <div class="p-6 space-y-3">
            <div class="grid grid-cols-12 gap-2 text-xs font-semibold uppercase text-gray-500 px-1">
              <div class="col-span-5">Nama Karyawan *</div>
              <div class="col-span-3">Tanggal Lembur *</div>
              <div class="col-span-2">Jam Pengajuan *</div>
              <div class="col-span-2">Aksi</div>
            </div>

            <div v-for="(item, index) in form.items" :key="`row-${index}`" class="grid grid-cols-12 gap-2 items-start">
              <div class="col-span-5">
                <OnboardingUserSelect
                  v-model="item.user_id"
                  search-route="overtime-submissions.search-users"
                  placeholder="Cari nama / NIK / jabatan..."
                  :allow-empty="false"

                />
              </div>
              <div class="col-span-3">
                <input v-model="item.overtime_date" type="date" required class="w-full rounded-lg border-gray-300" />
              </div>
              <div class="col-span-2">
                <input v-model.number="item.requested_hours" type="number" min="0.01" max="24" step="0.25" required class="w-full rounded-lg border-gray-300" />
              </div>
              <div class="col-span-2">
                <button type="button" @click="removeRow(index)" class="px-3 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200">
                  Hapus
                </button>
              </div>
              <div class="col-span-12">
                <input v-model="item.notes" type="text" placeholder="Catatan baris (opsional)" class="w-full rounded-lg border-gray-300" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('overtime-submissions.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="form.processing || form.items.length === 0" class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import OnboardingUserSelect from '@/Components/EmployeeOnboarding/OnboardingUserSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  today: { type: String, required: true },
});

const selectedOutletId = ref('');

const form = useForm({
  submission_date: props.today,
  notes: '',
  items: [
    {
      user_id: '',
      overtime_date: props.today,
      requested_hours: 1,
      notes: '',
    },
  ],
});

function addRow() {
  form.items.push({
    user_id: '',
    overtime_date: props.today,
    requested_hours: 1,
    notes: '',
  });
}

function removeRow(index) {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
}

function submit() {
  form.transform((data) => ({
    ...data,
    items: data.items.map((item) => ({
      ...item,
      user_id: Number(item.user_id),
    })),
  })).post(route('overtime-submissions.store'));
}
</script>

