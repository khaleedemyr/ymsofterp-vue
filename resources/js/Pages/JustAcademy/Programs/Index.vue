<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete } from '@/composables/useJustAcademyUi';

const props = defineProps({ programs: Object, filters: Object });
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const debounced = debounce(() => {
  router.get(route('just-academy.programs.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch(status, debounced);

async function remove(p) {
  const result = await jaConfirmDelete({
    title: 'Hapus program?',
    text: `"${p.title}" akan dihapus permanen.`,
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.programs.destroy', p.id));
}
</script>

<template>
  <JaLayout title="Programs" subtitle="Susun curriculum dari materi & quiz" icon="fa-solid fa-book-open">
    <template #actions>
      <Link :href="route('just-academy.programs.create')" :class="jaUi.btnPrimary">
        <i class="fa-solid fa-plus text-xs" /> Program Baru
      </Link>
    </template>

    <div class="mb-5 flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari program..." :class="jaUi.search" @input="debounced" />
      <select v-model="status" :class="jaUi.select">
        <option value="">Semua status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="archived">Archived</option>
      </select>
    </div>

    <div :class="jaUi.tableWrap">
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Kode</th>
            <th :class="jaUi.th">Judul</th>
            <th :class="jaUi.th">Kategori</th>
            <th :class="jaUi.th">Status</th>
            <th :class="jaUi.th">Item</th>
            <th :class="[jaUi.th, 'text-right']">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in programs.data" :key="p.id" :class="jaUi.tr">
            <td :class="jaUi.td">{{ p.code || '—' }}</td>
            <td :class="[jaUi.td, 'font-semibold text-slate-800']">{{ p.title }}</td>
            <td :class="jaUi.td">{{ p.category?.name || '—' }}</td>
            <td :class="jaUi.td"><span class="capitalize">{{ p.status }}</span></td>
            <td :class="jaUi.td">{{ p.items_count ?? 0 }} item</td>
            <td :class="[jaUi.td, 'text-right']">
              <div class="flex items-center justify-end gap-3">
                <Link :href="route('just-academy.programs.edit', p.id)" :class="jaUi.btnLink">
                  <i class="fa-solid fa-pen-to-square mr-1" />Edit
                </Link>
                <button type="button" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50" @click="remove(p)">
                  <i class="fa-solid fa-trash-can" />Hapus
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>
