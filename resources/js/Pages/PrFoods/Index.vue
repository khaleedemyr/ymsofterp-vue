<script setup>
import { ref, computed } from 'vue';
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
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

// Computed untuk cek jadwal PR Foods
const scheduleInfo = computed(() => {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  const startTime = new Date(today);
  startTime.setHours(15, 0, 0, 0); // 15:00 hari ini
  
  const endTime = new Date(tomorrow);
  endTime.setHours(10, 0, 0, 0); // 10:00 besok
  
  const isWithinSchedule = now >= startTime && now <= endTime;
  
  const formatTime = (date) => {
    return date.toLocaleTimeString('id-ID', { 
      hour: '2-digit', 
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };
  
  return {
    isWithinSchedule,
    startTime: formatTime(startTime),
    endTime: formatTime(endTime),
    now: formatTime(now)
  };
});

const debouncedSearch = debounce(() => {
  router.get('/pr-foods', { search: search.value, status: selectedStatus.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}, 400);

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
function openCreate() {
  // Cek jadwal PR Foods (15:00 hari ini - 10:00 besok)
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  const startTime = new Date(today);
  startTime.setHours(15, 0, 0, 0); // 15:00 hari ini
  
  const endTime = new Date(tomorrow);
  endTime.setHours(10, 0, 0, 0); // 10:00 besok
  
  // Cek apakah sekarang dalam jadwal yang diizinkan
  if (now >= startTime && now <= endTime) {
    router.visit('/pr-foods/create');
  } else {
    // Format waktu untuk display
    const formatTime = (date) => {
      return date.toLocaleTimeString('id-ID', { 
        hour: '2-digit', 
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
    };
    
    Swal.fire({
      icon: 'warning',
      title: 'Di Luar Jadwal',
      html: `
        <div class="text-left">
          <p class="mb-3">Jadwal pembuatan PR Foods:</p>
          <p class="mb-2"><strong>Buka:</strong> ${formatTime(startTime)}</p>
          <p class="mb-2"><strong>Tutup:</strong> ${formatTime(endTime)}</p>
          <p class="mt-3 text-sm text-gray-600">Silakan buat PR Foods pada jadwal yang ditentukan.</p>
        </div>
      `,
      confirmButtonColor: '#3085d6',
      confirmButtonText: 'OK'
    });
  }
}
function openEdit(id) {
  router.visit(`/pr-foods/${id}/edit`);
}
function openDetail(id) {
  router.visit(`/pr-foods/${id}`);
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
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-file-invoice text-blue-500"></i> Purchase Requisition Foods
          </h1>
          <!-- Info Jadwal -->
          <div class="mt-2 flex items-center gap-2">
            <div class="flex items-center gap-1 text-sm">
              <i class="fa fa-clock text-blue-500"></i>
              <span class="font-medium">Jadwal:</span>
              <span class="text-gray-600">{{ scheduleInfo.startTime }} - {{ scheduleInfo.endTime }}</span>
            </div>
            <div class="flex items-center gap-1 text-sm">
              <i class="fa fa-circle" :class="scheduleInfo.isWithinSchedule ? 'text-green-500' : 'text-red-500'"></i>
              <span :class="scheduleInfo.isWithinSchedule ? 'text-green-600' : 'text-red-600'" class="font-medium">
                {{ scheduleInfo.isWithinSchedule ? 'Buka' : 'Tutup' }}
              </span>
            </div>
          </div>
        </div>
        <button 
          @click="openCreate" 
          :class="[
            'px-4 py-2 rounded-xl shadow-lg transition-all font-semibold',
            scheduleInfo.isWithinSchedule 
              ? 'bg-gradient-to-r from-blue-500 to-blue-700 text-white hover:shadow-2xl' 
              : 'bg-gray-300 text-gray-500 cursor-not-allowed'
          ]"
          :disabled="!scheduleInfo.isWithinSchedule"
        >
          <i class="fa fa-clock mr-2"></i> + Buat PR Foods Baru
        </button>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor PR..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
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
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
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
              <td class="px-6 py-3">{{ pr.requester?.nama_lengkap }}</td>
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
                  <button @click="openDetail(pr.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Detail
                  </button>
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
