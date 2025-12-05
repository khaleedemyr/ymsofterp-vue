<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Good Receive Outlet Supplier
        </h1>
        <button @click="router.visit('/good-receive-outlet-supplier/create')" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Good Receive
        </button>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari GR, RO, atau Outlet..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. GR</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. RO</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Petugas</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!goodReceives.data || !goodReceives.data.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Belum ada data Good Receive.</td>
              </tr>
              <tr v-for="gr in goodReceives.data" :key="gr.id" class="hover:bg-blue-50 transition shadow-sm">
                <td class="px-6 py-3">{{ gr.receive_date }}</td>
                <td class="px-6 py-3">{{ gr.gr_number }}</td>
                <td class="px-6 py-3">{{ gr.ro_number }}</td>
                <td class="px-6 py-3">{{ gr.outlet_name }}</td>
                <td class="px-6 py-3">{{ gr.received_by_name }}</td>
                <td class="px-6 py-3">
                  <span :class="{
                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-green-100 text-green-800': gr.status === 'completed',
                    'bg-yellow-100 text-yellow-800': gr.status === 'draft',
                    'bg-gray-100 text-gray-800': !gr.status
                  }">
                    {{ gr.status || 'Draft' }}
                  </span>
                </td>
                <td class="px-6 py-3">
                  <div class="flex gap-2">
                    <button
                      class="px-2 py-1 text-blue-600 hover:underline"
                      @click="goToDetail(gr.id)"
                    >
                      Detail
                    </button>
                    <button @click="hapus(gr.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                      <i class="fa-solid fa-trash mr-1"></i> Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-700">
          Menampilkan {{ goodReceives.from }} - {{ goodReceives.to }} dari {{ goodReceives.total }} data
        </div>
        <div class="flex gap-2">
          <button
            v-for="link in goodReceives.links"
            :key="link.label"
            @click="goToPage(link.url)"
            v-html="link.label"
            :class="{
              'px-3 py-1 rounded': true,
              'bg-blue-500 text-white': link.active,
              'bg-gray-100 text-gray-700 hover:bg-gray-200': !link.active,
              'opacity-50 cursor-not-allowed': !link.url
            }"
            :disabled="!link.url"
          ></button>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <!-- Form component removed - using direct page navigation instead -->

  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
// Form component removed - using direct page navigation instead
import Swal from 'sweetalert2';

const props = defineProps({
  goodReceives: Object,
  filters: Object
});

const search = ref(props.filters?.search || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
// showForm removed - using direct page navigation instead

function onSearchInput() {
  router.get(
    route('good-receive-outlet-supplier.index'),
    { search: search.value, from: from.value, to: to.value },
    { preserveState: true, replace: true }
  );
}

function onDateChange() {
  router.get(
    route('good-receive-outlet-supplier.index'),
    { search: search.value, from: from.value, to: to.value },
    { preserveState: true, replace: true }
  );
}

async function openDetail(id) {
  try {
    const res = await fetch(`/good-receive-outlet-supplier/${id}`);
    if (!res.ok) throw new Error('Gagal fetch detail');
    // detailGR.value = await res.json();
  } catch (e) {
    Swal.fire('Error', 'Gagal mengambil detail Good Receive', 'error');
  }
}

function hapus(id) {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus Good Receive ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/good-receive-outlet-supplier/${id}`)
        .then(response => {
          if (response.data.success) {
            Swal.fire('Berhasil', 'Good Receive berhasil dihapus', 'success');
            router.reload();
          } else {
            throw new Error(response.data.message);
          }
        })
        .catch(error => {
          Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus Good Receive', 'error');
        });
    }
  });
}

// handleFormSuccess removed - using direct page navigation instead

function goToPage(url) {
  if (url) {
    router.get(url, { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
  }
}

function goToDetail(id) {
  console.log('Detail ID:', id);
  if (!id) {
    Swal.fire('Error', 'ID Good Receive tidak ditemukan', 'error');
    return;
  }
  router.visit(`/good-receive-outlet-supplier/${id}`);
}
</script> 