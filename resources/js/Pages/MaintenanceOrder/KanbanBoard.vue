<template>
  <div class="kanban-board flex flex-col md:flex-row gap-6 overflow-x-auto py-6 px-2 md:px-8 bg-gradient-to-br from-blue-50 via-white to-purple-100 h-[calc(100vh-12rem)]">
    <KanbanColumn
      v-for="(col, idx) in columns"
      :key="col.status"
      :title="col.title"
      :status="col.status"
      :tasks="tasksByStatus[col.status] || []"
      :count="(tasksByStatus[col.status] || []).length"
      @drop-task="onDropTask"
      @taskMoved="refreshTasks"
    >
      <template #header>
        <div class="flex items-center justify-center gap-2">
          <span>{{ col.title }}</span>
          <span class="bg-blue-100 text-blue-700 text-xs font-bold rounded-full px-2 py-0.5">{{ (tasksByStatus[col.status] || []).length }}</span>
          <button v-if="idx === 0" @click="handleCreateTaskClick" class="ml-2 text-blue-500 hover:text-blue-700 text-lg rounded-full p-1 bg-white shadow transition-all" title="Tambah Task">
            <i class="fas fa-plus"></i>
          </button>
        </div>
      </template>
    </KanbanColumn>
    <CreateTaskModal 
      v-if="showCreateModal" 
      :outlet="selectedOutlet"
      :ruko="selectedRuko"
      @close="showCreateModal = false"
      @taskCreated="refreshBoard"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import KanbanColumn from './KanbanColumn.vue';
import useKanban from './useKanban';
import CreateTaskModal from './CreateTaskModal.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  outlet: [String, Number],
  ruko: [String, Number],
});

const { t } = useI18n();
const columns = [
  { status: 'ToDo', title: t('kanban.todo') },
  { status: 'PR', title: t('kanban.pr') },
  { status: 'PO', title: t('kanban.po') },
  { status: 'InProgress', title: t('kanban.in_progress') },
  { status: 'InReview', title: t('kanban.in_review') },
  { status: 'Done', title: t('kanban.done') },
];

const { tasks, tasksByStatus, fetchTasks, onDropTask: dropTask } = useKanban();
const showCreateModal = ref(false);
const selectedOutlet = ref(props.outlet);
const selectedRuko = ref(props.ruko);

async function onDropTask({ taskId, toStatus }) {
  console.log('KanbanBoard onDropTask:', { taskId, toStatus });
  if (!taskId || !toStatus) {
    console.error('Invalid drop data:', { taskId, toStatus });
    Swal.fire('Error', 'Data task tidak valid', 'error');
    return;
  }

  // Jika task akan dipindah ke PO, cek status PR terlebih dahulu
  if (toStatus === 'PO') {
    try {
      // Ambil data PR untuk task ini
      const response = await axios.get(`/api/maintenance-tasks/${taskId}/purchase-requisitions`);
      const prs = response.data;

      // Cek apakah ada PR dengan status DRAFT
      const hasDraftPR = prs.some(pr => pr.status === 'DRAFT');
      
      if (hasDraftPR) {
        Swal.fire({
          title: 'Tidak dapat memindahkan task',
          text: 'Task tidak dapat dipindahkan ke PO karena masih ada PR dengan status DRAFT',
          icon: 'warning'
        });
        return;
      }
    } catch (error) {
      console.error('Error checking PR status:', error);
      Swal.fire('Error', 'Gagal memeriksa status PR', 'error');
      return;
    }
  }

  // Jika task akan dipindah dari PO ke IN_PROGRESS, cek status PO terlebih dahulu
  if (toStatus === 'InProgress') {
    try {
      // Ambil data PO untuk task ini
      const response = await axios.get(`/api/maintenance-tasks/${taskId}/purchase-orders`);
      const pos = response.data;

      // Cek apakah ada PO dengan status DRAFT
      const draftPOs = pos.filter(po => po.status === 'DRAFT');
      
      if (draftPOs.length > 0) {
        const poNumbers = draftPOs.map(po => po.po_number).join(', ');
        Swal.fire({
          title: 'Tidak dapat memindahkan task',
          html: `Task tidak dapat dipindahkan ke In Progress karena masih ada PO dengan status DRAFT:<br><br>${poNumbers}`,
          icon: 'warning'
        });
        return;
      }
    } catch (error) {
      console.error('Error checking PO status:', error);
      Swal.fire('Error', 'Gagal memeriksa status PO', 'error');
      return;
    }
  }

  // VALIDASI EVIDENCE sebelum move ke Done
  if (toStatus === 'Done') {
    try {
      const { data } = await axios.get(`/api/maintenance-evidence/${taskId}`);
      if (!data || data.length === 0) {
        Swal.fire('Tidak bisa selesai', 'Harus upload dulu bukti pekerjaan telah selesai.', 'warning');
        return;
      }
    } catch (error) {
      Swal.fire('Error', 'Gagal memeriksa evidence', 'error');
      return;
    }
  }

  try {
    dropTask({ 
      taskId, 
      toStatus, 
      filter: { outlet: props.outlet, ruko: props.ruko } 
    }).then(() => {
      console.log('Task updated successfully:', { taskId, toStatus });
      refreshTasks();
    }).catch(error => {
      console.error('Error updating task:', error);
      Swal.fire('Error', 'Gagal memperbarui status task', 'error');
    });
  } catch (error) {
    console.error('Error in onDropTask:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat memperbarui task', 'error');
  }
}

async function refreshTasks() {
  console.log('refreshTasks called with:', { outlet: props.outlet, ruko: props.ruko });
  try {
    await fetchTasks({ outlet: props.outlet, ruko: props.ruko });
    console.log('Tasks after refresh:', tasks.value);
    console.log('Tasks by status:', tasksByStatus);
  } catch (error) {
    console.error('Error in refreshTasks:', error);
  }
}

watch(() => props.outlet, (newVal, oldVal) => {
  console.log('Outlet changed:', { old: oldVal, new: newVal });
  selectedOutlet.value = newVal;
  refreshTasks();
}, { immediate: true });

watch(() => props.ruko, (newVal, oldVal) => {
  console.log('Ruko changed:', { old: oldVal, new: newVal });
  selectedRuko.value = newVal;
  refreshTasks();
}, { immediate: true });

function handleCreateTaskClick() {
  if (!props.outlet) {
    Swal.fire('Error', 'Pilih outlet terlebih dahulu!', 'error');
    return;
  }
  if (props.outlet == 1 && !props.ruko) {
    Swal.fire('Error', 'Pilih ruko terlebih dahulu!', 'error');
    return;
  }
  showCreateModal.value = true;
}

const refreshBoard = () => {
  refreshTasks();
};
</script>

<style scoped>
.kanban-board {
  height: calc(100vh - 12rem); /* Tinggi viewport dikurangi header, nav, dll */
  background: linear-gradient(135deg, #e0e7ff 0%, #fff 60%, #f3e8ff 100%);
  transition: background 0.5s;
}
</style> 