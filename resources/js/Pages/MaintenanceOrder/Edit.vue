<template>
  <div class="max-w-7xl mx-auto py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-blue-900">Edit Task</h1>
      <div class="flex gap-3">
        <a href="/maintenance-order/list" class="btn-secondary">
          <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
        <a :href="`/maintenance-order/${id}`" class="btn-secondary">
          <i class="fas fa-eye mr-2"></i> View Task
        </a>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
      <p class="mt-4 text-gray-600">Loading task details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
      <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-3"></i>
        <div>
          <h3 class="text-lg font-medium text-red-800">Error Loading Task</h3>
          <p class="text-red-700 mt-1">{{ error }}</p>
        </div>
      </div>
      <div class="mt-4">
        <button @click="loadTask" class="btn-primary">
          <i class="fas fa-redo mr-2"></i> Try Again
        </button>
      </div>
    </div>

    <!-- Edit Form -->
    <div v-else-if="task" class="bg-white rounded-lg shadow p-6">
      <form @submit.prevent="saveTask">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Task Number (Read Only) -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Task Number</label>
            <input type="text" :value="task.task_number" disabled class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500">
          </div>

          <!-- Status (Read Only) -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <span :class="getStatusBadgeClass(task.status)" class="mt-1 inline-block px-3 py-2 text-sm font-medium rounded-md">
              {{ getStatusLabel(task.status) }}
            </span>
          </div>

          <!-- Title -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Title *</label>
            <input 
              v-model="form.title" 
              type="text" 
              required
              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter task title"
            >
          </div>

          <!-- Description -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Description *</label>
            <textarea 
              v-model="form.description" 
              rows="4" 
              required
              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter task description"
            ></textarea>
          </div>

          <!-- Priority -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Priority *</label>
            <select 
              v-model="form.priority_id" 
              required
              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Select Priority</option>
              <option v-for="priority in priorities" :key="priority.id" :value="priority.id">
                {{ priority.priority }}
              </option>
            </select>
          </div>

          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Category *</label>
            <select 
              v-model="form.label_id" 
              required
              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Select Category</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>
          </div>

          <!-- Due Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Due Date *</label>
            <input 
              v-model="form.due_date" 
              type="date" 
              required
              class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            >
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
          <a href="/maintenance-order/list" class="btn-secondary">
            Cancel
          </a>
          <button type="submit" :disabled="saving" class="btn-primary">
            <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-save mr-2"></i>
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

// Props
const props = defineProps({
  id: String
});

// Reactive data
const task = ref(null);
const loading = ref(true);
const error = ref(null);
const saving = ref(false);
const priorities = ref([]);
const categories = ref([]);

// Form data
const form = ref({
  title: '',
  description: '',
  priority_id: '',
  label_id: '',
  due_date: ''
});

// Computed properties
const page = usePage();

// Methods
async function loadTask() {
  try {
    loading.value = true;
    error.value = null;
    
    const [taskRes, prioritiesRes, categoriesRes] = await Promise.all([
      axios.get(`/api/maintenance-order/${props.id}`),
      axios.get('/api/maintenance-priorities'),
      axios.get('/api/maintenance-labels')
    ]);

    task.value = taskRes.data;
    priorities.value = prioritiesRes.data;
    categories.value = categoriesRes.data;

    // Populate form
    form.value = {
      title: task.value.title,
      description: task.value.description,
      priority_id: task.value.priority_id,
      label_id: task.value.label_id,
      due_date: task.value.due_date ? new Date(task.value.due_date).toISOString().split('T')[0] : ''
    };
  } catch (err) {
    console.error('Error loading task:', err);
    error.value = err.response?.data?.error || 'Failed to load task details';
  } finally {
    loading.value = false;
  }
}

async function saveTask() {
  try {
    saving.value = true;
    
    const response = await axios.put(`/api/maintenance-order/${props.id}`, form.value);
    
    if (response.data.success) {
      Swal.fire({
        title: 'Success!',
        text: 'Task updated successfully',
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        window.location.href = `/maintenance-order/${props.id}`;
      });
    } else {
      throw new Error(response.data.error || 'Failed to update task');
    }
  } catch (err) {
    console.error('Error saving task:', err);
    Swal.fire({
      title: 'Error!',
      text: err.response?.data?.error || 'Failed to update task',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  } finally {
    saving.value = false;
  }
}

function getStatusLabel(status) {
  const labels = {
    'TASK': 'To Do',
    'PR': 'Purchase Requisition',
    'PO': 'Purchase Order',
    'IN_PROGRESS': 'In Progress',
    'IN_REVIEW': 'In Review',
    'DONE': 'Done'
  };
  return labels[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    'TASK': 'bg-gray-100 text-gray-800',
    'PR': 'bg-yellow-100 text-yellow-800',
    'PO': 'bg-blue-100 text-blue-800',
    'IN_PROGRESS': 'bg-purple-100 text-purple-800',
    'IN_REVIEW': 'bg-orange-100 text-orange-800',
    'DONE': 'bg-green-100 text-green-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

// Lifecycle
onMounted(() => {
  loadTask();
  document.title = `Edit Task - YMSoft`;
});
</script>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default {
  layout: AppLayout
}
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-secondary {
  @apply px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors;
}
</style>
