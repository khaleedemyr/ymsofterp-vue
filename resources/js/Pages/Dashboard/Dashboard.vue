<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import DashboardFilterBar from './DashboardFilterBar.vue';
import DashboardStats from './DashboardStats.vue';
import DashboardStatusPie from './DashboardStatusPie.vue';
import DashboardTrendLine from './DashboardTrendLine.vue';
import DashboardBarOutlet from './DashboardBarOutlet.vue';
import DashboardLeaderboard from './DashboardLeaderboard.vue';
import DashboardHeatmap from './DashboardHeatmap.vue';
import DashboardOverdueTable from './DashboardOverdueTable.vue';
import DashboardTaskTable from './DashboardTaskTable.vue';
import DashboardActivityList from './DashboardActivityList.vue';
import DashboardMemberBar from './DashboardMemberBar.vue';
import DashboardCategoryDonut from './DashboardCategoryDonut.vue';
import DashboardPriorityDonut from './DashboardPriorityDonut.vue';
import AllTasksModal from './AllTasksModal.vue';
import DoneTasksTable from './DoneTasksTable.vue';
import DoneTasksLeaderboard from './DoneTasksLeaderboard.vue';
import POLatestDetailModal from './POLatestDetailModal.vue';
import AllPOModal from './AllPOModal.vue';
import AllPRModal from './AllPRModal.vue';
import PRLatestDetailModal from './PRLatestDetailModal.vue';
import AllRetailModal from './AllRetailModal.vue';
import RetailLatestDetailModal from './RetailLatestDetailModal.vue';
import AllActivityModal from './AllActivityModal.vue';
import DashboardPolarChart from './DashboardPolarChart.vue';

// Tambahan komponen baru (dummy slot dulu)
// import DashboardCategoryDonut from './DashboardCategoryDonut.vue';
// import DashboardTaskTable from './DashboardTaskTable.vue';
// import DashboardEvidenceSlider from './DashboardEvidenceSlider.vue';
// import DashboardActivityList from './DashboardActivityList.vue';
// import DashboardMemberBar from './DashboardMemberBar.vue';
// import DashboardPOLatest from './DashboardPOLatest.vue';

const stats = ref([]);
const statusData = ref({});
const trendData = ref({});
const barOutletData = ref({});
const leaderboard = ref([]);
const overdueTasks = ref([]);
const heatmap = ref([]);
const latestTasks = ref([]);
const doneTasks = ref([]);
const activityList = ref([]);
const memberBarData = ref([]);
const mediaGallery = ref([]);
const categoryData = ref({});
const priorityData = ref({});
const showAllTasks = ref(false);
const poLatest = ref([]);
const showPODetail = ref(false);
const selectedPO = ref({});
const showAllPO = ref(false);
const prLatest = ref([]);
const showAllPR = ref(false);
const showPRDetail = ref(false);
const selectedPR = ref({});
const retailLatest = ref([]);
const showAllRetail = ref(false);
const showRetailDetail = ref(false);
const selectedRetail = ref({});
const showAllActivity = ref(false);
const taskCompletionStats = ref({});
const taskByDueDateStats = ref({});
const currentFilters = ref({});
const loading = ref(false);

async function fetchDashboardData(filters = {}) {
  let url = '/api/dashboard/maintenance';
  if (Object.keys(filters).length > 0) {
    url = '/api/dashboard/maintenance/filter';
  }
  const res = await axios.get(url, { params: filters });
  stats.value = res.data.stats;
  statusData.value = res.data.statusData;
  trendData.value = res.data.trendData;
  barOutletData.value = res.data.barOutletData;
  leaderboard.value = res.data.leaderboard;
  overdueTasks.value = res.data.overdueTasks;
  heatmap.value = res.data.heatmap;
  latestTasks.value = res.data.latestTasks || [];
  doneTasks.value = res.data.doneTasks || [];
  activityList.value = res.data.activityList || [];
  memberBarData.value = res.data.memberBarData || [];
  mediaGallery.value = res.data.mediaGallery || [];
  categoryData.value = res.data.categoryData || {};
  priorityData.value = res.data.priorityData || {};
}

async function fetchPOLatest(filters = {}) {
  const res = await axios.get('/api/maintenance-po-latest', { params: { perPage: 5, page: 1, ...filters } });
  poLatest.value = res.data.data;
}

async function fetchPRLatest(filters = {}) {
  const res = await axios.get('/api/maintenance-pr-latest', { params: { perPage: 5, page: 1, ...filters } });
  prLatest.value = res.data.data;
}

async function fetchRetailLatest(filters = {}) {
  const res = await axios.get('/api/retail-latest', { params: { perPage: 5, page: 1, ...filters } });
  retailLatest.value = res.data.data;
}

async function fetchActivityLatest(filters = {}) {
  const res = await axios.get('/api/activity-latest', { params: { perPage: 5, page: 1, ...filters } });
  activityList.value = res.data.data;
}

async function fetchTaskCompletionStats(filters = {}) {
  const res = await axios.get('/api/dashboard/task-completion-stats', { params: filters });
  taskCompletionStats.value = res.data;
}

async function fetchTaskByDueDateStats(filters = {}) {
  const res = await axios.get('/api/dashboard/task-by-due-date-stats', { params: filters });
  taskByDueDateStats.value = res.data;
}

async function loadAllDashboardData(filters = {}) {
  loading.value = true;
  await Promise.all([
    fetchDashboardData(filters),
    fetchPOLatest(filters),
    fetchPRLatest(filters),
    fetchRetailLatest(filters),
    fetchActivityLatest(filters),
    fetchTaskCompletionStats(filters),
    fetchTaskByDueDateStats(filters),
  ]);
  loading.value = false;
}

onMounted(() => {
  // Default: 30 hari ke belakang
  document.title = 'MT Dashboard - YMSoft';
  const today = new Date();
  const prior = new Date();
  prior.setDate(today.getDate() - 29);
  function toInputDate(d) { return d.toISOString().slice(0, 10); }
  const filters = { startDate: toInputDate(prior), endDate: toInputDate(today) };
  currentFilters.value = filters;
  loadAllDashboardData(filters);
});

function onFilterChange(filters) {
  currentFilters.value = filters;
  loadAllDashboardData(filters);
}

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function openPOLightbox(po, media) {
  // Implementasikan lightbox jika ingin preview
}

function openPODetail(po) {
  selectedPO.value = po;
  showPODetail.value = true;
}

function openAllPO() {
  showAllPO.value = true;
}

function openPODetailFromAll(po) {
  showAllPO.value = false;
  selectedPO.value = po;
  showPODetail.value = true;
}

function openAllPR() {
  showAllPR.value = true;
}

function openPRDetail(pr) {
  selectedPR.value = pr;
  showPRDetail.value = true;
}

function openPRDetailFromAll(pr) {
  showAllPR.value = false;
  selectedPR.value = pr;
  showPRDetail.value = true;
}

function openAllRetail() {
  showAllRetail.value = true;
}

function openRetailDetail(retail) {
  selectedRetail.value = retail;
  showRetailDetail.value = true;
}

function openRetailDetailFromAll(retail) {
  showAllRetail.value = false;
  selectedRetail.value = retail;
  showRetailDetail.value = true;
}

function openAllActivity() {
  showAllActivity.value = true;
}
</script>

<template>
  <div class="dashboard p-4 md:p-6 bg-gray-50 min-h-screen relative">
    <!-- Overlay Spinner -->
    <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
      <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>
    <!-- Header & Filter -->
    <div class="mb-4 md:mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight mb-1">MAINTENANCE</h1>
        <div class="text-xs text-gray-500">Data Dashboard</div>
      </div>
    </div>
    <DashboardFilterBar @filter-change="onFilterChange" />

    <!-- Statistik Ringkas -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 mb-4 md:mb-6">
      <DashboardStats :stats="stats" />
    </div>

    <!-- Chart Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
      <div class="bg-white rounded-xl p-4 shadow flex flex-col">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Task Status</h2>
        </div>
        <DashboardStatusPie :data="statusData" />
      </div>
      <div class="bg-white rounded-xl p-4 shadow flex flex-col">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Kategori Maintenance</h2>
          <button class="text-xs text-gray-400">...</button>
        </div>
        <DashboardCategoryDonut :data="categoryData" />
      </div>
      <div class="bg-white rounded-xl p-4 shadow flex flex-col">
        <DashboardPriorityDonut :data="priorityData" />
      </div>
    </div>

    <!-- Tambahan 2 chart baru -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
      <div class="bg-white rounded-xl p-4 shadow flex flex-col">
        <h2 class="text-base font-semibold mb-2">Task Completion</h2>
        <DashboardPolarChart :data="{ 'Belum Selesai': taskCompletionStats.not_done || 0, 'Selesai': taskCompletionStats.done || 0 }" :colors="['#2563eb', '#22c55e']" />
      </div>
      <div class="bg-white rounded-xl p-4 shadow flex flex-col">
        <h2 class="text-base font-semibold mb-2">Task by Due Date</h2>
        <DashboardPolarChart :data="{ 'Tepat Waktu': taskByDueDateStats.on_time || 0, 'Telat': taskByDueDateStats.late || 0 }" :colors="['#22c55e', '#ef4444']" />
      </div>
    </div>

    <!-- Tasks Terbaru Table & Tasks Selesai -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
      <div class="bg-white rounded-xl p-4 shadow">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Tasks Terbaru</h2>
          <button class="text-xs text-blue-500" @click="showAllTasks = true">Lihat Semua Tasks</button>
        </div>
        <DashboardTaskTable />
      </div>
      <div class="bg-white rounded-xl p-4 shadow">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Tasks Selesai</h2>
        </div>
        <DoneTasksTable />
      </div>
    </div>

    <!-- Aktivitas Maintenance & Tasks per Member -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
      <div class="bg-white rounded-xl p-4 shadow">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Aktivitas Maintenance</h2>
        </div>
        <DashboardTrendLine :data="trendData" />
      </div>
      <div class="bg-white rounded-xl p-4 shadow">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Leaderboard Tasks Selesai</h2>
        </div>
        <DoneTasksLeaderboard class="mt-0" />
      </div>
    </div>

    <!-- Purchase Orders Terbaru (full width) -->
    <div class="bg-white rounded-xl p-4 shadow mb-6">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-base font-semibold">Purchase Orders Terbaru</h2>
        <button class="text-xs text-blue-500" @click="openAllPO">Lihat Semua PO</button>
      </div>
      <div v-if="poLatest.length === 0" class="h-48 flex items-center justify-center text-gray-300">Tidak ada data PO terbaru</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm rounded-xl overflow-hidden">
          <thead class="bg-gray-100">
            <tr>
              <th class="py-2 px-3 text-left font-bold">PO Number</th>
              <th class="py-2 px-3 text-left font-bold">Task Number</th>
              <th class="py-2 px-3 text-left font-bold">Title</th>
              <th class="py-2 px-3 text-left font-bold">Supplier</th>
              <th class="py-2 px-3 text-left font-bold">Outlet</th>
              <th class="py-2 px-3 text-left font-bold">Status</th>
              <th class="py-2 px-3 text-right font-bold">Total</th>
              <th class="py-2 px-3 text-left font-bold">Tanggal</th>
              <th class="py-2 px-3 text-center font-bold">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="po in poLatest" :key="po.id" class="hover:bg-blue-50 transition">
              <td class="py-2 px-3 font-semibold text-blue-700">{{ po.po_number }}</td>
              <td class="py-2 px-3">{{ po.task_number || '-' }}</td>
              <td class="py-2 px-3">{{ po.title || '-' }}</td>
              <td class="py-2 px-3">{{ po.supplier_name || '-' }}</td>
              <td class="py-2 px-3">{{ po.nama_outlet || '-' }}</td>
              <td class="py-2 px-3">{{ po.status }}</td>
              <td class="py-2 px-3 text-right">{{ formatRupiah(po.total_amount) }}</td>
              <td class="py-2 px-3">{{ po.created_at ? po.created_at.substring(0,10) : '-' }}</td>
              <td class="py-2 px-3 text-center">
                <button class="p-2 rounded hover:bg-blue-100 transition" @click="openPODetail(po)">
                  <i class="fas fa-eye text-blue-600"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Purchase Requisitions Terbaru (full width) -->
    <div class="bg-white rounded-xl p-4 shadow mb-6">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-base font-semibold">Purchase Requisitions Terbaru</h2>
        <button class="text-xs text-blue-500" @click="openAllPR">Lihat Semua PR</button>
      </div>
      <div v-if="prLatest.length === 0" class="h-48 flex items-center justify-center text-gray-300">Tidak ada data PR terbaru</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm rounded-xl overflow-hidden">
          <thead class="bg-gray-100">
            <tr>
              <th class="py-2 px-3 text-left font-bold">PR Number</th>
              <th class="py-2 px-3 text-left font-bold">Task Number</th>
              <th class="py-2 px-3 text-left font-bold">Title</th>
              <th class="py-2 px-3 text-left font-bold">Outlet</th>
              <th class="py-2 px-3 text-left font-bold">Status</th>
              <th class="py-2 px-3 text-right font-bold">Total</th>
              <th class="py-2 px-3 text-left font-bold">Tanggal</th>
              <th class="py-2 px-3 text-left font-bold">Created By</th>
              <th class="py-2 px-3 text-center font-bold">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pr in prLatest" :key="pr.id" class="hover:bg-blue-50 transition">
              <td class="py-2 px-3 font-semibold text-blue-700">{{ pr.pr_number }}</td>
              <td class="py-2 px-3">{{ pr.task_number || '-' }}</td>
              <td class="py-2 px-3">{{ pr.title || '-' }}</td>
              <td class="py-2 px-3">{{ pr.nama_outlet || '-' }}</td>
              <td class="py-2 px-3">{{ pr.status }}</td>
              <td class="py-2 px-3 text-right">{{ formatRupiah(pr.total_amount) }}</td>
              <td class="py-2 px-3">{{ pr.created_at ? pr.created_at.substring(0,10) : '-' }}</td>
              <td class="py-2 px-3">{{ pr.created_by || '-' }}</td>
              <td class="py-2 px-3 text-center">
                <button class="p-2 rounded hover:bg-blue-100 transition" @click="openPRDetail(pr)">
                  <i class="fas fa-eye text-blue-600"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Retail Terbaru (full width) -->
    <div class="bg-white rounded-xl p-4 shadow mb-6">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-base font-semibold">Retail Terbaru</h2>
        <button class="text-xs text-blue-500" @click="openAllRetail">Lihat Semua Retail</button>
      </div>
      <div v-if="retailLatest.length === 0" class="h-48 flex items-center justify-center text-gray-300">Tidak ada data retail terbaru</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm rounded-xl overflow-hidden">
          <thead class="bg-gray-100">
            <tr>
              <th class="py-2 px-3 text-left font-bold">Nama Toko</th>
              <th class="py-2 px-3 text-left font-bold">Alamat</th>
              <th class="py-2 px-3 text-left font-bold">Outlet</th>
              <th class="py-2 px-3 text-left font-bold">Task Number</th>
              <th class="py-2 px-3 text-left font-bold">Title Task</th>
              <th class="py-2 px-3 text-left font-bold">Created By</th>
              <th class="py-2 px-3 text-left font-bold">Tanggal</th>
              <th class="py-2 px-3 text-right font-bold">Total Amount</th>
              <th class="py-2 px-3 text-center font-bold">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in retailLatest" :key="r.id" class="hover:bg-blue-50 transition">
              <td class="py-2 px-3 font-semibold text-blue-700">{{ r.nama_toko }}</td>
              <td class="py-2 px-3">{{ r.alamat_toko || '-' }}</td>
              <td class="py-2 px-3">{{ r.nama_outlet || '-' }}</td>
              <td class="py-2 px-3">{{ r.task_number || '-' }}</td>
              <td class="py-2 px-3">{{ r.title || '-' }}</td>
              <td class="py-2 px-3">{{ r.created_by || '-' }}</td>
              <td class="py-2 px-3">{{ r.created_at ? r.created_at.substring(0,10) : '-' }}</td>
              <td class="py-2 px-3 text-right">{{ formatRupiah(r.total_amount) }}</td>
              <td class="py-2 px-3 text-center">
                <button class="p-2 rounded hover:bg-blue-100 transition" @click="openRetailDetail(r)">
                  <i class="fas fa-eye text-blue-600"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Aktivitas Terbaru & Overdue Table -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
      <div class="bg-white rounded-xl p-4 shadow">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-base font-semibold">Aktivitas Terbaru</h2>
          <button class="text-xs text-blue-500" @click="openAllActivity">Lihat Semua</button>
        </div>
        <div v-if="activityList.length === 0" class="h-48 flex items-center justify-center text-gray-300">Tidak ada aktivitas</div>
        <div v-else>
          <div v-for="a in activityList" :key="a.id" class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-indigo-200 flex items-center justify-center font-bold text-white text-lg">{{ a.user_initials }}</div>
            <div>
              <div class="font-semibold text-indigo-800">{{ a.user_name }} <span class="text-xs text-gray-400 font-normal">{{ a.type }}</span></div>
              <div class="text-sm text-gray-600">{{ a.description }}</div>
              <div class="text-xs text-gray-400">{{ a.time_ago }}</div>
            </div>
          </div>
        </div>
      </div>
      <div>
        <DashboardOverdueTable :tasks="overdueTasks" />
      </div>
    </div>
    <AllTasksModal :show="showAllTasks" @close="showAllTasks = false" />
    <POLatestDetailModal :po="selectedPO" :show="showPODetail" @close="showPODetail = false" />
    <AllPOModal :show="showAllPO" @close="showAllPO = false" @open-detail="openPODetailFromAll" />
    <AllPRModal :show="showAllPR" @close="showAllPR = false" @open-detail="openPRDetailFromAll" />
    <PRLatestDetailModal :pr="selectedPR" :show="showPRDetail" @close="showPRDetail = false" />
    <AllRetailModal :show="showAllRetail" @close="showAllRetail = false" @open-detail="openRetailDetailFromAll" />
    <RetailLatestDetailModal :retail="selectedRetail" :show="showRetailDetail" @close="showRetailDetail = false" />
    <AllActivityModal :show="showAllActivity" @close="showAllActivity = false" />
  </div>
</template>

<style scoped>
.dashboard {
  min-height: 100vh;
  background: #f3f4f6;
}
</style> 