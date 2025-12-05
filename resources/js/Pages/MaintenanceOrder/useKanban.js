import { ref, computed } from 'vue';
import axios from 'axios';

export default function useKanban() {
  const tasks = ref([]); // data task maintenance order
  
  const tasksByStatus = computed(() => {
    const grouped = {
      'ToDo': [],
      'PR': [],
      'PO': [],
      'InProgress': [],
      'InReview': [],
      'Done': []
    };
    
    console.log('Grouping tasks:', tasks.value);
    
    for (const task of tasks.value) {
      // Map status dari backend ke status di frontend
      let status = task.status;
      console.log('Processing task:', task.id, 'with status:', status);
      
      switch (status) {
        case 'TASK':
          status = 'ToDo';
          break;
        case 'PR':
          status = 'PR';
          break;
        case 'PO':
          status = 'PO';
          break;
        case 'IN_PROGRESS':
          status = 'InProgress';
          break;
        case 'IN_REVIEW':
          status = 'InReview';
          break;
        case 'DONE':
          status = 'Done';
          break;
        default:
          console.warn('Unknown status:', status, 'for task:', task.id);
          status = 'ToDo'; // Default ke ToDo jika status tidak dikenal
      }
      
      console.log('Mapped status:', status, 'for task:', task.id);
      if (!grouped[status]) grouped[status] = [];
      grouped[status].push(task);
    }
    
    console.log('Final grouped tasks:', grouped);
    return grouped;
  });

  async function fetchTasks({ outlet, ruko }) {
    console.log('fetchTasks called with:', { outlet, ruko });
    
    try {
      if (!outlet) {
        console.log('No outlet provided, clearing tasks');
        tasks.value = [];
        return;
      }

      const params = { id_outlet: outlet };
      if (outlet == 1 && ruko) params.id_ruko = ruko;
      
      console.log('Fetching tasks with params:', params);
      const { data } = await axios.get('/api/maintenance-order', { params });
      console.log('Tasks fetched:', data);
      
      tasks.value = Array.isArray(data) ? data : [];
      console.log('Tasks updated, current state:', tasks.value);
    } catch (error) {
      console.error('Error fetching tasks:', error);
      tasks.value = [];
      throw error;
    }
  }

  async function onDropTask({ taskId, toStatus, filter }) {
    console.log('onDropTask called:', { taskId, toStatus, filter });
    try {
      if (!taskId || taskId === 'undefined' || !toStatus) {
        console.error('Invalid task data:', { taskId, toStatus });
        return;
      }

      // Map status dari frontend ke backend
      let backendStatus = toStatus;
      switch (toStatus) {
        case 'ToDo':
          backendStatus = 'TASK';
          break;
        case 'PR':
          backendStatus = 'PR';
          break;
        case 'PO':
          backendStatus = 'PO';
          break;
        case 'InProgress':
          backendStatus = 'IN_PROGRESS';
          break;
        case 'InReview':
          backendStatus = 'IN_REVIEW';
          break;
        case 'Done':
          backendStatus = 'DONE';
          break;
        default:
          console.error('Invalid status:', toStatus);
          return;
      }
      
      console.log('Updating task status:', { taskId, backendStatus });
      const response = await axios.patch(`/api/maintenance-order/${taskId}`, { status: backendStatus });
      if (!response.data.success) {
        throw new Error(response.data.error || 'Failed to update task status');
      }
      console.log('Task status updated successfully');

      // Refresh task list setelah update
      await fetchTasks(filter || {});
    } catch (error) {
      console.error('Error in onDropTask:', error);
      console.error('Error details:', error.response?.data);
      throw error;
    }
  }

  return { tasks, tasksByStatus, fetchTasks, onDropTask };
} 