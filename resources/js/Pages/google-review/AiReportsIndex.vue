<template>
  <AppLayout>
    <template #default>
      <div class="wrap">
        <div class="head">
          <div>
            <h1 class="title">Laporan klasifikasi AI — Google Review</h1>
            <p class="sub">Riwayat analisis sentimen tersimpan di database. Unduh Excel dari halaman detail.</p>
          </div>
          <Link href="/google-review" class="btn-back">← Scrape review</Link>
        </div>

        <div class="panel">
          <div v-if="!reports.data?.length" class="empty">Belum ada laporan. Dari halaman Google Review, klik «Klasifikasi AI semua & simpan».</div>
          <table v-else class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Tempat</th>
                <th>Outlet</th>
                <th>Jumlah</th>
                <th>Dibuat</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in reports.data" :key="r.id">
                <td>{{ r.id }}</td>
                <td><span class="badge" :class="'st-' + r.status">{{ statusLabel(r.status) }}</span></td>
                <td>{{ r.place_name || '—' }}</td>
                <td>{{ r.nama_outlet || '—' }}</td>
                <td>{{ r.review_count }}</td>
                <td class="muted">{{ r.created_at }}</td>
                <td>
                  <Link :href="`/google-review/ai/reports/${r.id}`" class="link">Buka</Link>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="reports.links?.length > 3" class="pager">
            <Link
              v-for="l in reports.links"
              :key="l.label"
              :href="l.url || '#'"
              class="page-link"
              :class="{ active: l.active, disabled: !l.url }"
              preserve-scroll
              v-html="l.label"
            />
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  reports: { type: Object, required: true },
})

function statusLabel(s) {
  const m = { pending: 'Menunggu', processing: 'Memproses', completed: 'Selesai', failed: 'Gagal' }
  return m[s] || s
}
</script>

<style scoped>
.wrap {
  max-width: 1100px;
  margin: 0 auto;
  padding: 20px 16px 48px;
}
.head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}
.sub {
  margin-top: 4px;
  font-size: 13px;
  color: #6b7280;
}
.btn-back {
  padding: 10px 14px;
  border-radius: 10px;
  background: #f3f4f6;
  color: #374151;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 12px rgba(17, 24, 39, 0.04);
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.table th,
.table td {
  text-align: left;
  padding: 10px 8px;
  border-bottom: 1px solid #f3f4f6;
}
.table th {
  color: #6b7280;
  font-weight: 600;
}
.muted {
  color: #6b7280;
  font-size: 12px;
}
.link {
  color: #2563eb;
  font-weight: 600;
  text-decoration: none;
}
.link:hover {
  text-decoration: underline;
}
.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}
.st-pending,
.st-processing {
  background: #fef3c7;
  color: #92400e;
}
.st-completed {
  background: #d1fae5;
  color: #065f46;
}
.st-failed {
  background: #fecaca;
  color: #991b1b;
}
.empty {
  padding: 24px;
  text-align: center;
  color: #6b7280;
}
.pager {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 16px;
  justify-content: center;
}
.page-link {
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 12px;
  text-decoration: none;
  color: #374151;
}
.page-link.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.page-link.disabled {
  opacity: 0.4;
  pointer-events: none;
}
</style>
