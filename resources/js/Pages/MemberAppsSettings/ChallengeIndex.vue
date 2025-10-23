<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-trophy text-yellow-500"></i>
          Challenge Management
        </h1>
        <div class="flex gap-2">
          <button @click="goCreate" class="bg-gradient-to-r from-yellow-500 to-yellow-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Challenge
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-list text-blue-600"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Challenges</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa-solid fa-check-circle text-green-600"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Active Challenges</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.active }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="p-2 bg-gray-100 rounded-lg">
              <i class="fa-solid fa-pause-circle text-gray-600"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Inactive Challenges</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.inactive }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-4 mb-6 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <input v-model="filterSearch" type="text" class="form-input rounded border px-3 py-2" placeholder="Cari challenge..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select v-model="filterStatus" class="form-select rounded border px-3 py-2">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select v-model="filterType" class="form-select rounded border px-3 py-2">
            <option value="">All Types</option>
            <option v-for="type in challengeTypes" :key="type.id" :value="type.name">{{ type.name }}</option>
          </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold">
          <i class="fa-solid fa-search mr-2"></i>Filter
        </button>
      </form>

      <!-- Challenges Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Challenge</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Validity</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!challenges.data.length">
                <td colspan="6" class="text-center py-10 text-gray-500">Tidak ada challenge.</td>
              </tr>
              <tr v-for="challenge in challenges.data" :key="challenge.id">
                <td class="px-6 py-4">
                  <div>
                    <div class="font-medium text-gray-900">{{ challenge.name }}</div>
                    <div class="text-sm text-gray-500">{{ challenge.description }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ challenge.challenge_type.name }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    challenge.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]">
                    {{ challenge.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  {{ challenge.validity_period_days }} days
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  {{ challenge.creator?.nama_lengkap || '-' }}
                </td>
                <td class="px-6 py-4 text-sm font-medium">
                  <div class="flex space-x-2">
                    <button @click="goDetail(challenge.id)" class="text-blue-600 hover:text-blue-900">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button @click="goEdit(challenge.id)" class="text-yellow-600 hover:text-yellow-900">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="toggleStatus(challenge)" class="text-green-600 hover:text-green-900">
                      <i :class="challenge.is_active ? 'fa-solid fa-pause' : 'fa-solid fa-play'"></i>
                    </button>
                    <button @click="deleteChallenge(challenge)" class="text-red-600 hover:text-red-900">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="challenges.last_page > 1" class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow-sm">
          <button :disabled="challenges.current_page === 1" @click="goToPage(challenges.current_page - 1)" 
            class="px-3 py-1 border bg-white text-blue-700 hover:bg-blue-100 rounded-l disabled:opacity-50">&lt;</button>
          <button v-for="page in paginationPages" :key="page" @click="goToPage(page)" 
            :class="['px-3 py-1 border', page === challenges.current_page ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-100']">
            {{ page }}
          </button>
          <button :disabled="challenges.current_page === challenges.last_page" @click="goToPage(challenges.current_page + 1)" 
            class="px-3 py-1 border bg-white text-blue-700 hover:bg-blue-100 rounded-r disabled:opacity-50">&gt;</button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ref, computed } from 'vue'

const props = defineProps({
  challenges: Object,
  filters: Object,
  challengeTypes: Array,
  stats: Object
})

const filterSearch = ref(props.filters?.search || '')
const filterStatus = ref(props.filters?.status || '')
const filterType = ref(props.filters?.type || '')

function applyFilter() {
  router.visit(route('challenges.index', {
    search: filterSearch.value || undefined,
    status: filterStatus.value || undefined,
    type: filterType.value || undefined,
  }))
}

function goToPage(page) {
  router.visit(route('challenges.index', {
    ...props.filters,
    page
  }))
}

const paginationPages = computed(() => {
  if (!props.challenges || !props.challenges.last_page) return []
  const pages = []
  for (let i = 1; i <= props.challenges.last_page; i++) pages.push(i)
  return pages
})

function goCreate() {
  router.visit(route('challenges.create'))
}

function goDetail(id) {
  router.visit(route('challenges.show', id))
}

function goEdit(id) {
  router.visit(route('challenges.edit', id))
}

function toggleStatus(challenge) {
  const action = challenge.is_active ? 'nonaktifkan' : 'aktifkan'
  Swal.fire({
    title: `Apakah Anda yakin ingin ${action} challenge ini?`,
    text: `Challenge "${challenge.name}" akan ${action}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${action}!`,
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.patch(route('challenges.toggle-status', challenge.id), {}, {
        onSuccess: () => {
          Swal.fire('Berhasil!', `Challenge berhasil ${action}.`, 'success')
        }
      })
    }
  })
}

function deleteChallenge(challenge) {
  Swal.fire({
    title: 'Apakah Anda yakin ingin menghapus challenge ini?',
    text: `Challenge "${challenge.name}" akan dihapus secara permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('challenges.destroy', challenge.id), {
        onSuccess: () => {
          Swal.fire('Berhasil!', 'Challenge berhasil dihapus.', 'success')
        }
      })
    }
  })
}
</script>
