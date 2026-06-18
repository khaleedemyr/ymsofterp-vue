<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({ schedules: Object, filters: Object });
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const debounced = debounce(() => {
  router.get(route('just-academy.schedules.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch(status, debounced);
</script>

<template>
  <JaLayout title="Jadwal Training" subtitle="Kelola jadwal offline dan peserta" icon="fa-solid fa-calendar-days">
    <template #actions>
      <Link :href="route('just-academy.schedules.create')" :class="jaUi.btnPrimary">
        <i class="fa-solid fa-plus text-xs" /> Jadwal Baru
      </Link>
    </template>

    <div class="mb-5 flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari jadwal..." :class="jaUi.search" @input="debounced" />
      <select v-model="status" :class="jaUi.select">
        <option value="">Semua status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="ongoing">Ongoing</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <div :class="jaUi.tableWrap">
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Judul</th>
            <th :class="jaUi.th">Program</th>
            <th :class="jaUi.th">Waktu</th>
            <th :class="jaUi.th">Peserta</th>
            <th :class="jaUi.th">Status</th>
            <th :class="jaUi.th"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in schedules.data" :key="s.id" :class="jaUi.tr">
            <td :class="[jaUi.td, 'font-semibold text-slate-800']">{{ s.title }}</td>
            <td :class="jaUi.td">{{ s.program?.title }}</td>
            <td :class="jaUi.td">{{ s.start_at }}</td>
            <td :class="jaUi.td">{{ s.participants_count }}</td>
            <td :class="jaUi.td"><span class="capitalize">{{ s.status }}</span></td>
            <td :class="[jaUi.td, 'text-right']">
              <Link :href="route('just-academy.schedules.show', s.id)" :class="jaUi.btnLink">Detail</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>
