<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  actionPlans: Object,
  filters: Object
});

const search = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

function applyFilter() {
  router.visit('/ops-kitchen/action-plan-guest-review', {
    method: 'get',
    data: {
      search: search.value,
      date_from: dateFrom.value,
      date_to: dateTo.value
    },
    preserveState: true,
    preserveScroll: true
  });
}

watch([search, dateFrom, dateTo], () => {
  // Debounce bisa ditambah jika ingin
});

const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

function openLightbox(imgs, idx) {
  lightboxImages.value = imgs;
  lightboxIndex.value = idx;
  lightboxVisible.value = true;
}

function goToCreate() {
  router.visit('/ops-kitchen/action-plan-guest-review/create');
}

function goToDetail(id) {
  router.visit(`/ops-kitchen/action-plan-guest-review/${id}`);
}
function goToEdit(id) {
  router.visit(`/ops-kitchen/action-plan-guest-review/${id}/edit`);
}
async function deletePlan(id) {
  const result = await Swal.fire({
    title: 'Hapus Data?',
    text: 'Yakin ingin menghapus data ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(`/ops-kitchen/action-plan-guest-review/${id}`, {
    onSuccess: () => {
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data berhasil dihapus',
        icon: 'success',
        confirmButtonColor: '#3085d6'
      });
    }
  });
}
</script>
<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-list text-blue-500"></i> Action Plan Guest Review
        </h1>
        <div class="flex gap-2">
          <button @click="goToCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Action Plan Guest Review
          </button>
        </div>
      </div>
      <!-- Filter/Search Bar -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6 flex flex-col md:flex-row gap-4 items-center">
        <input v-model="search" @keyup.enter="applyFilter" type="text" placeholder="Cari outlet, PIC, status, problem..." class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <input v-model="dateFrom" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <span>-</span>
        <input v-model="dateTo" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <button @click="applyFilter" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">Filter</button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PIC</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dokumentasi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(plan, i) in props.actionPlans.data" :key="plan.id">
              <td class="px-6 py-4">{{ (props.actionPlans.current_page - 1) * props.actionPlans.per_page + i + 1 }}</td>
              <td class="px-6 py-4">{{ plan.outlet_name || '-' }}</td>
              <td class="px-6 py-4">{{ plan.tanggal }}</td>
              <td class="px-6 py-4">{{ plan.pic_name || '-' }}</td>
              <td class="px-6 py-4">{{ plan.status }}</td>
              <td class="px-6 py-4">
                <template v-if="plan.images && plan.images.length">
                  <div class="flex gap-2">
                    <img
                      v-for="(img, idx) in plan.images"
                      :key="img.id"
                      :src="'/storage/' + img.path"
                      class="w-12 h-12 object-cover rounded cursor-pointer border border-gray-200"
                      @click="openLightbox(plan.images.map(i => '/storage/' + i.path), idx)"
                      :alt="'Dokumentasi ' + (idx+1)"
                    />
                  </div>
                </template>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex gap-2">
                  <button @click="goToDetail(plan.id)" class="text-gray-600 hover:text-blue-600" title="Detail"><i class="fa-solid fa-eye"></i></button>
                  <button @click="goToEdit(plan.id)" class="text-gray-600 hover:text-green-600" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                  <button @click="deletePlan(plan.id)" class="text-gray-600 hover:text-red-600" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                </div>
              </td>
            </tr>
            <tr v-if="!props.actionPlans.data || !props.actionPlans.data.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Belum ada data Action Plan Guest Review.</td>
            </tr>
          </tbody>
        </table>
        <!-- Pagination -->
        <div class="flex justify-end mt-4 gap-2">
          <button
            v-for="link in props.actionPlans.links"
            :key="link.label"
            :disabled="!link.url"
            @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
            v-html="link.label"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
        <VueEasyLightbox
          :visible="lightboxVisible"
          :imgs="lightboxImages"
          :index="lightboxIndex"
          @hide="lightboxVisible = false"
        />
      </div>
    </div>
  </AppLayout>
</template> 