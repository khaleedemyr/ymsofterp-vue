<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  template: Object,
});

function edit() {
  router.visit(route('kpi-templates.edit', props.template.id));
}

function back() {
  router.visit(route('kpi-templates.index'));
}
</script>

<template>
  <AppLayout title="Preview KPI Template">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
          <button class="text-gray-500" @click="back"><i class="fa-solid fa-arrow-left"></i></button>
          <div>
            <h1 class="text-2xl font-bold">{{ template.name }}</h1>
            <p class="text-sm text-gray-500 font-mono">{{ template.code }} · v{{ template.version }} · {{ template.template_status }}</p>
          </div>
        </div>
        <button class="bg-rose-600 text-white px-4 py-2 rounded-xl" @click="edit">Edit Template</button>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h2 class="font-semibold mb-2">Jabatan</h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="j in template.jabatans" :key="j.id_jabatan" class="px-3 py-1 bg-rose-100 text-rose-800 rounded-full text-sm">
            {{ j.nama_jabatan }}
          </span>
          <span v-if="!template.jabatans?.length" class="text-gray-400">Belum di-assign</span>
        </div>
        <p v-if="template.description" class="mt-4 text-sm text-gray-600">{{ template.description }}</p>
      </div>

      <div v-for="strategy in template.strategies" :key="strategy.id" class="bg-white rounded-2xl shadow mb-4 overflow-hidden">
        <div class="bg-rose-700 text-white px-4 py-3 flex justify-between">
          <span class="font-semibold">{{ strategy.key_strategy?.name }}</span>
          <span class="text-rose-100">Bobot: {{ strategy.weight_percent }}%</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">KPI</th>
                <th class="px-4 py-2 text-left">Target</th>
                <th class="px-4 py-2 text-left">Formula</th>
                <th class="px-4 py-2 text-left">Parameter</th>
                <th class="px-4 py-2 text-right">Bobot %</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="item in strategy.items" :key="item.id">
                <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                <td class="px-4 py-3">{{ item.target_value || '—' }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ item.formula || '—' }}</td>
                <td class="px-4 py-3">
                  <span v-for="p in item.parameters" :key="p.id" class="inline-block mr-1 mb-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs">
                    {{ p.code }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">{{ item.weight_percent }}%</td>
              </tr>
              <tr v-if="!strategy.items?.length">
                <td colspan="5" class="px-4 py-4 text-center text-gray-400">Tidak ada KPI item.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
