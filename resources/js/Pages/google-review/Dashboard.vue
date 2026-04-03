<template>
  <AppLayout>
    <template #default>
      <div class="wrap">
        <div class="head">
          <div>
            <h1 class="title">Dashboard Google Review + Instagram</h1>
            <p class="sub">Ringkasan data scraping dan klasifikasi AI.</p>
          </div>
          <a href="/google-review" class="btn-back">Buka Scraper</a>
        </div>

        <div class="cards">
          <div class="card">
            <div class="k">Instagram Posts</div>
            <div class="v">{{ cards.instagram_posts }}</div>
          </div>
          <div class="card">
            <div class="k">Instagram Comments</div>
            <div class="v">{{ cards.instagram_comments }}</div>
          </div>
          <div class="card">
            <div class="k">AI Reports Selesai</div>
            <div class="v">{{ cards.ai_reports_completed }}</div>
          </div>
          <div class="card">
            <div class="k">AI Items Total</div>
            <div class="v">{{ cards.ai_items_total }}</div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-title">Sentimen {{ range.sentiment_days }} hari terakhir</div>
          <div class="sent-grid">
            <div>
              <div class="label">Google Reviews</div>
              <ul class="list">
                <li v-for="(v, k) in sentiment.google" :key="`g-${k}`">
                  <span>{{ labelSev(k) }}</span>
                  <strong>{{ v }}</strong>
                </li>
              </ul>
            </div>
            <div>
              <div class="label">Instagram Comments</div>
              <ul class="list">
                <li v-for="(v, k) in sentiment.instagram" :key="`i-${k}`">
                  <span>{{ labelSev(k) }}</span>
                  <strong>{{ v }}</strong>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-title">Tren Harian ({{ range.daily_days }} hari)</div>
          <table class="table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>IG Comments</th>
                <th>AI Classified (IG)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in daily" :key="r.date">
                <td>{{ r.date }}</td>
                <td>{{ r.instagram_comments }}</td>
                <td>{{ r.ai_classified }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="panel">
          <div class="panel-title">Top Profil IG Berdasarkan Jumlah Komentar</div>
          <table class="table">
            <thead>
              <tr>
                <th>Profil</th>
                <th>Total komentar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in topProfiles" :key="r.profile_key">
                <td>{{ r.profile_key }}</td>
                <td>{{ r.total_comments }}</td>
              </tr>
              <tr v-if="!topProfiles.length">
                <td colspan="2" class="muted">Belum ada data.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  cards: { type: Object, required: true },
  sentiment: { type: Object, required: true },
  daily: { type: Array, required: true },
  topProfiles: { type: Array, required: true },
  range: { type: Object, required: true },
})

function labelSev(k) {
  const m = {
    positive: 'Positif',
    neutral: 'Netral',
    mild_negative: 'Negatif ringan',
    negative: 'Negatif',
    severe: 'Sangat parah',
  }
  return m[k] || k
}
</script>

<style scoped>
.wrap { max-width: 1200px; margin: 0 auto; padding: 20px 14px 48px; }
.head { display: flex; justify-content: space-between; align-items: end; margin-bottom: 14px; gap: 12px; }
.title { font-size: 22px; font-weight: 700; }
.sub { color: #6b7280; font-size: 13px; margin-top: 4px; }
.btn-back { background: #eef2ff; color: #3730a3; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 600; }
.cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 12px; }
.k { font-size: 12px; color: #6b7280; }
.v { font-size: 24px; font-weight: 700; color: #111827; margin-top: 4px; }
.panel { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 12px; margin-bottom: 12px; }
.panel-title { font-weight: 700; font-size: 14px; margin-bottom: 10px; }
.sent-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.label { font-size: 12px; color: #6b7280; margin-bottom: 6px; }
.list { list-style: none; padding: 0; margin: 0; }
.list li { display: flex; justify-content: space-between; border-bottom: 1px solid #f3f4f6; padding: 6px 0; font-size: 13px; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th, .table td { text-align: left; padding: 8px; border-bottom: 1px solid #f3f4f6; }
.muted { color: #6b7280; text-align: center; }
@media (max-width: 900px) {
  .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sent-grid { grid-template-columns: 1fr; }
}
</style>

