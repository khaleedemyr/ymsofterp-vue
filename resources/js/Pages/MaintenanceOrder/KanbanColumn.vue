<template>
  <div class="kanban-column bg-white/80 rounded-2xl shadow-2xl flex-1 min-w-[260px] max-w-xs flex flex-col border border-gray-100 hover:shadow-3xl transition-all duration-300 backdrop-blur-md h-full"
       @dragover.prevent="onDragOver"
       @dragleave.prevent="onDragLeave"
       @drop.prevent="onDrop">
    <div class="p-4 font-bold text-center border-b text-gray-700 text-base tracking-wide uppercase bg-gradient-to-r from-blue-100/60 to-purple-100/40 rounded-t-2xl">
      <slot name="header">
        {{ title }}
      </slot>
    </div>
    <div class="flex-1 p-3 space-y-3 overflow-y-auto min-h-0">
      <slot>
        <KanbanCard v-for="task in tasks" :key="task.id_order" :task="task" :status="status" @taskMoved="$emit('taskMoved')" />
      </slot>
    </div>
  </div>
</template>

<script setup>
import KanbanCard from './KanbanCard.vue';
const props = defineProps({
  title: String,
  status: String,
  tasks: Array,
  count: Number,
});
const emit = defineEmits(['drop-task', 'taskMoved']);

function onDragOver(e) {
  e.preventDefault();
  e.currentTarget.classList.add('drag-over');
}

function onDragLeave(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
}

function onDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
  const taskId = e.dataTransfer.getData('text/plain');
  console.log('Received taskId in drop:', taskId);
  
  if (!taskId || taskId === 'undefined') {
    console.error('Invalid task ID received in drop event:', taskId);
    return;
  }

  console.log('Dropping task:', taskId, 'to status:', props.status);
  emit('drop-task', { 
    taskId: parseInt(taskId, 10), // Konversi kembali ke number
    toStatus: props.status 
  });
}
</script>

<style scoped>
.kanban-column {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 6px 0 rgba(80, 0, 200, 0.07);
  border-radius: 1.25rem;
  transition: box-shadow 0.3s;
  background: rgba(255,255,255,0.85);
  position: relative;
}
.kanban-column:hover {
  box-shadow: 0 16px 48px 0 rgba(31, 38, 135, 0.18), 0 3px 12px 0 rgba(80, 0, 200, 0.10);
}

.drag-over {
  background: rgba(255,255,255,0.95);
  box-shadow: inset 0 0 0 2px #4f46e5;
}

.drag-over::after {
  content: '';
  position: absolute;
  inset: 0;
  border: 2px dashed #4f46e5;
  border-radius: 1.25rem;
  pointer-events: none;
}
</style> 