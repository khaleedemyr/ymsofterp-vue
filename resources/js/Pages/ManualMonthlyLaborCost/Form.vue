<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-clock text-blue-600"></i>
            {{ isEdit ? 'Edit' : 'Tambah' }} Manual Monthly Labor Cost
          </h1>
          <p class="text-sm text-gray-500 mt-1">Pilih periode, lalu isi data Labor Cost per outlet</p>
        </div>
        <Link :href="route('manual-monthly-labor-cost.index')" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Periode</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-xl">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan <span class="text-red-500">*</span></label>
              <select
                v-model="form.month"
                required
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">Pilih Bulan</option>
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
              <p v-if="form.errors.month" class="text-sm text-red-600 mt-1">{{ form.errors.month }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun <span class="text-red-500">*</span></label>
              <select
                v-model="form.year"
                required
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">Pilih Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
              <p v-if="form.errors.year" class="text-sm text-red-600 mt-1">{{ form.errors.year }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Data per Outlet</h2>
            <button
              type="button"
              @click="addRow"
              class="px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 text-sm"
            >
              <i class="fa-solid fa-plus mr-1"></i> Tambah Outlet
            </button>
          </div>

          <p v-if="form.errors.items" class="text-sm text-red-600 mb-3">{{ form.errors.items }}</p>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
              <thead class="bg-gray-800 text-white">
                <tr>
                  <th class="px-3 py-2 text-left w-8">#</th>
                  <th class="px-3 py-2 text-left min-w-[200px]">Outlet</th>
                  <th class="px-3 py-2 text-right min-w-[160px]">Nilai Labor Cost</th>
                  <th class="px-3 py-2 text-right min-w-[140px]">% Labor Cost</th>
                  <th class="px-3 py-2 text-center w-16">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="form.items.length === 0">
                  <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                    Belum ada outlet. Klik "Tambah Outlet".
                  </td>
                </tr>
                <tr v-for="(row, idx) in form.items" :key="row._key" class="border-t align-top">
                  <td class="px-3 py-3 text-gray-500">{{ idx + 1 }}</td>
                  <td class="px-3 py-3">
                    <select
                      v-model="row.outlet_id"
                      required
                      class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                      <option value="">Pilih Outlet</option>
                      <option
                        v-for="outlet in availableOutletsForRow(idx)"
                        :key="outlet.id_outlet"
                        :value="outlet.id_outlet"
                      >
                        {{ outlet.nama_outlet }}
                      </option>
                    </select>
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.labor_cost_value" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.labor_cost_percent" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3 text-center">
                    <button
                      type="button"
                      @click="removeRow(idx)"
                      class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200"
                      title="Hapus baris"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="route('manual-monthly-labor-cost.index')"
            class="px-5 py-2.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-5 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            <i v-if="form.processing" class="fas fa-spinner fa-spin mr-1"></i>
            {{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
  monthOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.record?.id)

let rowKey = 0
function makeRow(item = null) {
  return {
    _key: ++rowKey,
    outlet_id: item?.outlet_id ?? '',
    labor_cost_value: item?.labor_cost_value ?? '',
    labor_cost_percent: item?.labor_cost_percent ?? '',
  }
}

const form = useForm({
  month: props.record?.month ?? '',
  year: props.record?.year ?? '',
  items: props.record?.items?.length
    ? props.record.items.map((item) => makeRow(item))
    : [makeRow()],
})

function addRow() {
  form.items.push(makeRow())
}

function removeRow(idx) {
  if (form.items.length <= 1) return
  form.items.splice(idx, 1)
}

function availableOutletsForRow(currentIdx) {
  const selectedIds = form.items
    .map((row, idx) => (idx !== currentIdx && row.outlet_id ? String(row.outlet_id) : null))
    .filter(Boolean)

  return props.outlets.filter((outlet) => !selectedIds.includes(String(outlet.id_outlet)))
}

function submit() {
  const payload = {
    month: form.month,
    year: form.year,
    items: form.items
      .filter((row) => row.outlet_id)
      .map((row) => ({
        outlet_id: row.outlet_id,
        labor_cost_value: row.labor_cost_value === '' ? 0 : row.labor_cost_value,
        labor_cost_percent: row.labor_cost_percent === '' ? 0 : row.labor_cost_percent,
      })),
  }

  if (isEdit.value) {
    form.transform(() => payload).put(route('manual-monthly-labor-cost.update', props.record.id))
  } else {
    form.transform(() => payload).post(route('manual-monthly-labor-cost.store'))
  }
}
</script>
