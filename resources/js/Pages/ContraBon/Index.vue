<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file-invoice"></i> Contra Bon
        </h1>
        <Link
          :href="route('contra-bons.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Contra Bon
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor/PO/Supplier..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus-border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No Invoice Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!contraBons.data || !contraBons.data.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Belum ada data Contra Bon.</td>
            </tr>
            <tr v-for="cb in contraBons.data" :key="cb.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ cb.number }}</td>
              <td class="px-6 py-3">{{ formatDate(cb.date) }}</td>
              <td class="px-6 py-3">{{ cb.supplier?.name }}</td>
              <td class="px-6 py-3">{{ cb.purchase_order?.number }}</td>
              <td class="px-6 py-3">{{ cb.supplier_invoice_number || '-' }}</td>
              <td class="px-6 py-3">{{ formatCurrency(cb.total_amount) }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': cb.status === 'draft',
                  'bg-green-100 text-green-700': cb.status === 'approved',
                  'bg-red-100 text-red-700': cb.status === 'rejected',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ cb.status }}
                </span>
              </td>
              <td class="px-6 py-3">{{ cb.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="goToDetail(cb.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button @click="goToEdit(cb.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button @click="confirmDelete(cb.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in contraBons.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  contraBons: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedStatus.value = filters?.status || '';
    from.value = filters?.from || '';
    to.value = filters?.to || '';
  },
  { immediate: true }
);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function debouncedSearch() {
  router.get('/contra-bons', { search: search.value, status: selectedStatus.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onStatusChange() {
  debouncedSearch();
}

function onDateChange() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function goToEdit(id) {
  router.visit(`/contra-bons/${id}/edit`);
}

function goToDetail(id) {
  router.visit(`/contra-bons/${id}`);
}

function confirmDelete(id) {
  if (!id) return;
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Hapus Contra Bon?',
      text: 'Data yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.delete(`/contra-bons/${id}`, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Contra Bon berhasil dihapus!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menghapus Contra Bon', 'error');
          }
        });
      }
    });
  });
}
</script> 