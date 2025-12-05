<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  user: Object,
  repacks: Object,
});

const search = ref('');
const selectedStatus = ref('');
const from = ref('');
const to = ref('');

function onSearchInput() {}
function onStatusChange() {}
function onDateChange() {}
function goToPage(url) {}
function openCreate() {
  window.location.href = '/repack/create';
}
function openEdit(id) {
  router.visit(`/repack/edit/${id}`);
}
function openDetail(id) {
  router.visit(`/repack/${id}`);
}
async function hapus(repack) {
  const result = await Swal.fire({
    title: 'Hapus Repack?',
    text: `Yakin ingin menghapus Repack ${repack.repack_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/repack/${repack.id}`);
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Repack berhasil dihapus.',
      timer: 1500,
      showConfirmButton: false
    });
    window.location.reload();
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err?.response?.data?.error || 'Tidak bisa hapus Repack ini.'
    });
  }
}

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function getSubtotal(repack) {
  if (!repack.items) return 0;
  return repack.items.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0);
}
const grandTotal = computed(() =>
  props.repacks.data.reduce((sum, repack) => sum + getSubtotal(repack), 0)
);
</script>
<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div v-if="props.user?.outlet" class="mb-4">
        <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-800 px-4 py-2 rounded-xl font-semibold">
          <i class="fa fa-store"></i>
          Outlet Anda: <span class="font-bold">{{ props.user.outlet.nama_outlet }}</span>
        </div>
      </div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-box-open text-blue-500"></i> Repack
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Repack
        </button>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor Repack..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
        </select>
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. Repack</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item Hasil</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Hasil</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="props.repacks.data.length === 0">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data Repack.</td>
            </tr>
            <tr v-for="repack in props.repacks.data" :key="repack.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ repack.repack_number }}</td>
              <td class="px-6 py-3">{{ new Date(repack.created_at).toLocaleDateString('id-ID') }}</td>
              <td class="px-6 py-3">{{ repack.item_asal?.name ?? '-' }}</td>
              <td class="px-6 py-3">{{ repack.qty_asal }}</td>
              <td class="px-6 py-3">{{ repack.item_hasil?.name ?? '-' }}</td>
              <td class="px-6 py-3">{{ repack.qty_hasil }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': repack.status === 'draft',
                  'bg-green-100 text-green-700': repack.status === 'approved',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ repack.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openDetail(repack.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Detail
                  </button>
                  <button @click="openEdit(repack.id)" :disabled="repack.status !== 'draft'" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    Edit
                  </button>
                  <button @click="hapus(repack)" :disabled="repack.status !== 'draft'" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
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
          v-for="link in props.repacks.links"
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