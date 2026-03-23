<script setup>
import { ref, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  blocks: { type: Object, default: () => ({ data: [] }) },
});

const page = usePage();

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: flash.success,
      confirmButtonText: 'OK',
    });
  }
  if (flash.error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: flash.error,
      confirmButtonText: 'OK',
    });
  }
});

const search = ref('');

function handleSearch() {
  router.get('/web-profile/home-blocks', { search: search.value }, {
    preserveState: true,
    replace: true,
  });
}

async function destroyBlock(id) {
  const row = props.blocks.data.find((b) => b.id === id);
  const result = await Swal.fire({
    title: 'Hapus blok?',
    text: `Hapus blok "${row?.title || id}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (result.isConfirmed) {
    router.delete(`/web-profile/home-blocks/${id}`, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Berhasil', 'Blok dihapus.', 'success'),
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Home Blocks">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Home Page Blocks (Company Profile)</h1>
        <Link href="/web-profile/home-blocks/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fa-solid fa-plus mr-2"></i> Tambah Blok
        </Link>
      </div>

      <div class="mb-4 flex gap-2">
        <TextInput v-model="search" class="flex-1" placeholder="Cari judul..." @keyup.enter="handleSearch" />
        <PrimaryButton @click="handleSearch">Search</PrimaryButton>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Judul</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aktif</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in blocks.data" :key="row.id">
              <td class="px-4 py-2 text-sm">{{ row.sort_order }}</td>
              <td class="px-4 py-2 text-sm uppercase">{{ row.block_type }}</td>
              <td class="px-4 py-2 text-sm">{{ row.title || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ row.is_active ? 'Ya' : 'Tidak' }}</td>
              <td class="px-4 py-2 text-sm text-right">
                <Link :href="`/web-profile/home-blocks/${row.id}/edit`" class="text-blue-600 hover:underline mr-3">Edit</Link>
                <button type="button" class="text-red-600 hover:underline" @click="destroyBlock(row.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!blocks.data || blocks.data.length === 0" class="text-center py-8 text-gray-500">
        Belum ada blok. Klik Tambah Blok.
      </div>

      <div v-if="blocks.links && blocks.links.length > 3" class="mt-4 flex justify-center gap-2">
        <Link
          v-for="link in blocks.links"
          :key="link.label"
          :href="link.url || '#'"
          :class="[
            'px-4 py-2 rounded-lg',
            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : '',
          ]"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>
