<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  prFoods: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

const debouncedSearch = debounce(() => {
  router.get('/pr-foods', { search: search.value, status: selectedStatus.value }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}
function onStatusChange() {
  debouncedSearch();
}
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}
function openCreate() {
  router.visit('/pr-foods/create');
}
function openEdit(id) {
  router.visit(`/pr-foods/${id}/edit`);
}
async function hapus(pr) {
  const result = await Swal.fire({
    title: 'Hapus PR Foods?',
    text: `Yakin ingin menghapus PR ${pr.pr_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('pr-foods.destroy', pr.id), {
    onSuccess: () => Swal.fire('Berhasil', 'PR berhasil dihapus!', 'success'),
  });
}
</script>
<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file-invoice text-blue-500"></i> Purchase Requisition Foods
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat PR Foods Baru
        </button>
      </div>
      <div class="flex gap-3 mb-4">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor PR..."
          class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="po">PO</option>
          <option value="receive">Receive</option>
          <option value="payment">Payment</option>
        </select>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. PR</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Requester</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="prFoods.data.length === 0">
              <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data PR Foods.</td>
            </tr>
            <tr v-for="pr in prFoods.data" :key="pr.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ pr.pr_number }}</td>
              <td class="px-6 py-3">{{ new Date(pr.tanggal).toLocaleDateString('id-ID') }}</td>
              <td class="px-6 py-3">{{ pr.warehouse?.name }}</td>
              <td class="px-6 py-3">{{ pr.requester?.name }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': pr.status === 'draft',
                  'bg-green-100 text-green-700': pr.status === 'approved',
                  'bg-red-100 text-red-700': pr.status === 'rejected',
                  'bg-blue-100 text-blue-700': pr.status === 'po',
                  'bg-yellow-100 text-yellow-700': pr.status === 'receive',
                  'bg-purple-100 text-purple-700': pr.status === 'payment',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ pr.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openEdit(pr.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    Edit
                  </button>
                  <button @click="hapus(pr)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Hapus
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
          v-for="link in prFoods.links"
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
