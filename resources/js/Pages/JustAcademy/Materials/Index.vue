<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete } from '@/composables/useJustAcademyUi';

const props = defineProps({ materials: Object, filters: Object });
const search = ref(props.filters?.search || '');

const debounced = debounce(() => {
  router.get(route('just-academy.materials.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
}, 400);

watch(search, debounced);

async function remove(m) {
  const result = await jaConfirmDelete({
    title: 'Hapus materi?',
    text: `"${m.title}" akan dihapus dari pustaka.`,
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.materials.destroy', m.id));
}
</script>

<template>
  <JaLayout title="Pustaka Materi" subtitle="Kelola file, video, dan link training" icon="fa-solid fa-file-lines">
    <template #actions>
      <Link :href="route('just-academy.materials.create')" :class="jaUi.btnPrimary">
        <i class="fa-solid fa-plus text-xs" /> Materi Baru
      </Link>
    </template>

    <input v-model="search" type="text" placeholder="Cari materi..." :class="[jaUi.search, 'mb-5']" @input="debounced" />

    <div :class="jaUi.tableWrap">
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Judul</th>
            <th :class="jaUi.th">Tipe</th>
            <th :class="jaUi.th">Status</th>
            <th :class="jaUi.th"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in materials.data" :key="m.id" :class="jaUi.tr">
            <td :class="[jaUi.td, 'font-semibold text-slate-800']">{{ m.title }}</td>
            <td :class="jaUi.td"><span class="uppercase text-xs font-medium text-slate-500">{{ m.type }}</span></td>
            <td :class="jaUi.td">
              <span :class="m.is_active ? jaUi.badgeActive : jaUi.badgeInactive">{{ m.is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </td>
            <td :class="[jaUi.td, 'text-right space-x-4']">
              <Link :href="route('just-academy.materials.edit', m.id)" :class="jaUi.btnLink">Edit</Link>
              <button type="button" :class="jaUi.btnDanger" @click="remove(m)">Hapus</button>
            </td>
          </tr>
          <tr v-if="!materials.data?.length">
            <td colspan="4" :class="jaUi.empty">Belum ada materi.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>
