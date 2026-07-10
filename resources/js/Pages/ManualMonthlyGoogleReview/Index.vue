<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-brands fa-google text-blue-600"></i>
            Manual Monthly Google Review
          </h1>
          <p class="text-sm text-gray-500 mt-1">Input manual rating Google Review per outlet per periode</p>
        </div>
        <Link
          :href="route('manual-monthly-google-review.create')"
          class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Tambah Baru
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col sm:flex-row gap-4 items-end">
          <div class="flex-1 w-full sm:max-w-[200px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
            <select v-model="filterForm.month" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Semua Bulan</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div class="flex-1 w-full sm:max-w-[160px]">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
            <select v-model="filterForm.year" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Semua Tahun</option>
              <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button type="button" @click="resetFilters" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
              Reset
            </button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
              Filter
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Periode</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Jumlah Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Dibuat Oleh</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Terakhir Diubah</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-800">{{ periodLabel(row) }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                  {{ row.items_count || 0 }} outlet
                </span>
              </td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(row.updated_at) }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <Link
                    :href="route('manual-monthly-google-review.show', row.id)"
                    class="px-3 py-1.5 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                    title="View"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link
                    :href="route('manual-monthly-google-review.edit', row.id)"
                    class="px-3 py-1.5 rounded bg-amber-100 text-amber-700 hover:bg-amber-200"
                    title="Edit"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </Link>
                  <button
                    type="button"
                    @click="confirmDelete(row)"
                    class="px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200"
                    title="Delete"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
        <Link
          v-for="link in records.links"
          :key="link.label"
          :href="link.url || '#'"
          class="px-3 py-1 rounded border text-sm"
          :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  monthOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
})

const filterForm = reactive({
  month: props.filters.month ?? '',
  year: props.filters.year ?? '',
})

const monthNameMap = Object.fromEntries(props.monthOptions.map((m) => [m.value, m.label]))

function periodLabel(row) {
  const monthName = monthNameMap[row.month] || row.month
  return `${monthName} ${row.year}`
}

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function applyFilters() {
  router.get(route('manual-monthly-google-review.index'), {
    month: filterForm.month || undefined,
    year: filterForm.year || undefined,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  filterForm.month = ''
  filterForm.year = ''
  applyFilters()
}

async function confirmDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus Data?',
    text: `Yakin hapus data periode ${periodLabel(row)}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  })

  if (!result.isConfirmed) return

  router.delete(route('manual-monthly-google-review.destroy', row.id))
}
</script>
