<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-8 px-2">
      <div class="w-full px-4">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-users-cog text-blue-500"></i> User Role Setting
          </h1>
        </div>
        
        <!-- Success/Error Messages -->
        <div v-if="$page.props.flash.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
          <i class="fas fa-check-circle mr-2"></i>
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
          <i class="fas fa-exclamation-circle mr-2"></i>
          {{ $page.props.flash.error }}
        </div>
        <!-- Filter Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select v-model="selectedOutlet" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
              <select v-model="selectedDivision" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Divisi</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
              <select v-model="selectedRole" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Role</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="clearFilters" class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-undo mr-2"></i>
                Clear Filter
              </button>
            </div>
          </div>
        </div>
        
        <div class="flex justify-between items-center mb-4">
          <input v-model="search" type="text" placeholder="Search user name..." class="w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
          
          <!-- Bulk Actions -->
          <div v-if="selectedUsers.length > 0" class="flex items-center gap-3">
            <span class="text-sm text-gray-600">{{ selectedUsers.length }} user(s) selected</span>
            <button @click="showBulkAssignModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
              <i class="fas fa-users-cog"></i>
              Bulk Assign Role
            </button>
            <button @click="clearSelection" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
              <i class="fas fa-times"></i>
              Clear
            </button>
          </div>
        </div>
        
        <!-- Per Page & Pagination Info -->
        <div class="flex justify-between items-center mb-4">
          <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600">Show:</span>
            <select v-model="perPage" @change="currentPage = 1" class="px-3 py-1 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <span class="text-sm text-gray-600">entries</span>
          </div>
          <div class="text-sm text-gray-600">
            Showing {{ ((currentPage - 1) * perPage) + 1 }} to {{ Math.min(currentPage * perPage, filteredUsers.length) }} of {{ filteredUsers.length }} entries
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input 
                    type="checkbox" 
                    :checked="isAllSelected" 
                    @change="toggleSelectAll"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="filteredUsers.length === 0">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data user.</td>
              </tr>
              <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-gray-50" :class="{ 'bg-blue-50': selectedUsers.includes(user.id) }">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input 
                    type="checkbox" 
                    :value="user.id"
                    v-model="selectedUsers"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_lengkap }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_outlet || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_divisi || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_jabatan || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <select v-model="userRole[user.id]" class="border rounded-lg px-2 py-1">
                    <option :value="null">- Pilih Role -</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                  </select>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <button @click="saveRole(user)" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md">
                    <i class="fa-solid fa-save"></i> Simpan
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="flex justify-end mt-4 gap-2">
          <button
            v-for="page in totalPages"
            :key="page"
            @click="currentPage = page"
            :class="[
              'px-3 py-1 rounded-lg border text-sm font-semibold',
              currentPage === page ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50'
            ]"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>
    
    <!-- Bulk Assign Role Modal -->
    <div v-if="showBulkAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md mx-4">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Bulk Assign Role</h3>
          <p class="text-sm text-gray-600 mb-4">Assign role untuk {{ selectedUsers.length }} user yang dipilih</p>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Role</label>
            <select v-model="bulkRoleId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
              <option value="">- Pilih Role -</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
          </div>
          
          <div class="flex justify-end gap-3">
            <button @click="showBulkAssignModal = false" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
              Cancel
            </button>
            <button @click="bulkAssignRole" :disabled="!bulkRoleId || isBulkAssigning" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="isBulkAssigning" class="fas fa-spinner fa-spin"></i>
              <span v-if="isBulkAssigning">Processing...</span>
              <span v-else>Assign Role</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) }
});

const search = ref('');
const currentPage = ref(1);
const perPage = ref(15);
const userRole = ref({});
const selectedOutlet = ref(props.filters.outlet_id || '');
const selectedDivision = ref(props.filters.division_id || '');
const selectedRole = ref(props.filters.role_id || '');

// Bulk selection
const selectedUsers = ref([]);
const showBulkAssignModal = ref(false);
const bulkRoleId = ref('');
const isBulkAssigning = ref(false);

props.users.forEach(u => { userRole.value[u.id] = u.role_id; });

const filteredUsers = computed(() => {
  if (!search.value) return props.users;
  return props.users.filter(user =>
    user.nama_lengkap && user.nama_lengkap.toLowerCase().includes(search.value.toLowerCase())
  );
});

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / perPage.value));
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredUsers.value.slice(start, start + perPage.value);
});

// Bulk selection computed
const isAllSelected = computed(() => {
  return paginatedUsers.value.length > 0 && paginatedUsers.value.every(user => selectedUsers.value.includes(user.id));
});

function saveRole(user) {
  router.put(`/user-roles/${user.id}`, { role_id: userRole.value[user.id] }, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Role user berhasil diupdate!' });
    },
    onError: () => {
      Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal update role user.' });
    }
  });
}

function applyFilters() {
  const params = {};
  if (selectedOutlet.value) params.outlet_id = selectedOutlet.value;
  if (selectedDivision.value) params.division_id = selectedDivision.value;
  if (selectedRole.value) params.role_id = selectedRole.value;
  
  router.get('/user-roles', params, {
    preserveState: true,
    preserveScroll: true
  });
}

function clearFilters() {
  selectedOutlet.value = '';
  selectedDivision.value = '';
  selectedRole.value = '';
  router.get('/user-roles', {}, {
    preserveState: true,
    preserveScroll: true
  });
}

// Bulk selection methods
function toggleSelectAll() {
  if (isAllSelected.value) {
    // Unselect all users in current page
    paginatedUsers.value.forEach(user => {
      const index = selectedUsers.value.indexOf(user.id);
      if (index > -1) {
        selectedUsers.value.splice(index, 1);
      }
    });
  } else {
    // Select all users in current page
    paginatedUsers.value.forEach(user => {
      if (!selectedUsers.value.includes(user.id)) {
        selectedUsers.value.push(user.id);
      }
    });
  }
}

function clearSelection() {
  selectedUsers.value = [];
}

function bulkAssignRole() {
  if (!bulkRoleId.value || selectedUsers.value.length === 0) return;
  
  isBulkAssigning.value = true;
  
  router.post('/user-roles/bulk-assign', {
    user_ids: selectedUsers.value,
    role_id: bulkRoleId.value
  }, {
    onSuccess: () => {
      showBulkAssignModal.value = false;
      selectedUsers.value = [];
      bulkRoleId.value = '';
    },
    onError: () => {
      Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal assign role ke user.' });
    },
    onFinish: () => {
      isBulkAssigning.value = false;
    }
  });
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.3s; }
</style> 