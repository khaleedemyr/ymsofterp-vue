<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-scissors text-blue-500"></i> Log Potong Stock
        </h1>
        <Link :href="route('stock-cut.form')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
          <i class="fa-solid fa-plus mr-1"></i> Tambah Potong Stock
        </Link>
      </div>
      <div class="bg-white rounded-xl shadow-xl p-6">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-4 py-2">Tanggal</th>
              <th class="px-4 py-2">Outlet</th>
              <th class="px-4 py-2">User</th>
              <th class="px-4 py-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id">
              <td class="px-4 py-2">{{ log.tanggal }}</td>
              <td class="px-4 py-2">{{ log.outlet_nama }}</td>
              <td class="px-4 py-2">{{ log.user_nama }}</td>
              <td class="px-4 py-2">
                <button @click="rollback(log.id)" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">
                  <i class="fa-solid fa-undo"></i> Undo
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'

const logs = ref([])

onMounted(async () => {
  const res = await axios.get('/stock-cut/logs')
  logs.value = res.data
})

async function rollback(id) {
  if (!confirm('Yakin ingin rollback potong stock ini?')) return
  await axios.post('/stock-cut/rollback', { id })
  logs.value = logs.value.filter(l => l.id !== id)
}
</script> 