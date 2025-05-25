<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-8 px-2">
      <div class="max-w-7xl w-full mx-auto">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-users-cog text-blue-500"></i> User Role Setting
          </h1>
        </div>
        <div class="flex justify-between items-center mb-4">
          <input v-model="search" type="text" placeholder="Search user name..." class="w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="filteredUsers.length === 0">
                <td colspan="5" class="text-center py-10 text-gray-400">Tidak ada data user.</td>
              </tr>
              <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_lengkap }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ user.nama_outlet || '-' }}</td>
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
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] }
});

const search = ref('');
const currentPage = ref(1);
const perPage = 15;
const userRole = ref({});
props.users.forEach(u => { userRole.value[u.id] = u.role_id; });

const filteredUsers = computed(() => {
  if (!search.value) return props.users;
  return props.users.filter(user =>
    user.nama_lengkap && user.nama_lengkap.toLowerCase().includes(search.value.toLowerCase())
  );
});

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / perPage));
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredUsers.value.slice(start, start + perPage);
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