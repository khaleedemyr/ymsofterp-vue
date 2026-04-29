<template>
  <AppLayout>
    <template #default>
      <div class="page">
        <div class="header">
          <div>
            <div class="title">Google Maps & Instagram</div>
            <div class="subtitle">
              Tab <strong>Google Maps</strong>: scrape &amp; preview review; klasifikasi AI lewat
              <a class="inline-link" href="/google-review/ai/reports">riwayat laporan AI</a>.
              Tab <strong>Instagram</strong>: sinkron post &amp; komentar via Apify (profil tetap di config), simpan ke database.
            </div>
          </div>
          <div class="header-actions">
            <button type="button" class="btn-history" @click="scrollToDashboard">Dashboard</button>
            <a href="/google-review/manual" class="btn-history">Input Manual Review</a>
            <a href="/google-review/ai/reports" class="btn-history">Riwayat laporan AI</a>
          </div>
        </div>

        <div class="tabs" role="tablist">
          <button
            type="button"
            class="tab"
            :class="{ active: activeTab === 'google' }"
            role="tab"
            :aria-selected="activeTab === 'google'"
            @click="activeTab = 'google'"
          >
            Google Maps
          </button>
          <button
            type="button"
            class="tab"
            :class="{ active: activeTab === 'instagram' }"
            role="tab"
            :aria-selected="activeTab === 'instagram'"
            @click="openInstagramTab"
          >
            Instagram
          </button>
        </div>

        <div v-show="activeTab === 'google'" class="tab-panel">
        <div class="panel">
          <div class="controls">
            <div class="control">
              <div class="label">Outlet</div>
              <select v-model="selectedOutlet" class="select" required>
                <option value="" disabled>Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.place_id">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>

            <div class="control">
              <div class="label">Per halaman</div>
              <select v-model.number="perPage" class="select" :disabled="!datasetId || loadingItems">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
                <option :value="200">200</option>
              </select>
            </div>

            <div class="control control-narrow">
              <div class="label">Maks review (Apify)</div>
              <input
                v-model.number="maxApifyReviews"
                type="number"
                min="1"
                max="2000"
                class="input-num"
                :disabled="loading"
                title="Jumlah maksimum yang diminta ke actor Apify (1–2000)"
              />
            </div>

            <div class="control control-narrow">
              <div class="label">Maks review (file scraper)</div>
              <input
                v-model.number="maxScraperReviews"
                type="number"
                min="0"
                max="2000"
                class="input-num"
                :disabled="loading"
                title="0 = ambil semua dari reviews.json, lalu dibatasi maks 2000 untuk AI"
              />
            </div>

            <div class="control control-narrow">
              <div class="label">Dari tanggal</div>
              <input v-model="googleDateFrom" type="date" class="input-num" :disabled="loading || loadingItems" />
            </div>

            <div class="control control-narrow">
              <div class="label">Sampai tanggal</div>
              <input v-model="googleDateTo" type="date" class="input-num" :disabled="loading || loadingItems" />
            </div>
            <div class="control control-wide">
              <div class="label">Preset tanggal</div>
              <div class="date-preset">
                <button type="button" class="btn btn-outline btn-sm" :disabled="loading || loadingItems" @click="applyGooglePreset(7)">7 hari</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="loading || loadingItems" @click="applyGooglePreset(30)">30 hari</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="loading || loadingItems" @click="applyGooglePreset(90)">3 bulan</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="loading || loadingItems" @click="clearGoogleDateRange">Clear</button>
              </div>
            </div>

            <div class="actions">
              <button type="button" class="btn btn-apify" :disabled="!selectedOutlet || loading" @click="fetchApifyReviews">
                Ambil Review Apify
              </button>
              <button
                v-if="datasetId"
                type="button"
                class="btn btn-outline"
                :disabled="exporting"
                @click="exportExcel"
              >
                {{ exporting ? 'Exporting…' : 'Export CSV (mentah)' }}
              </button>
              <button
                type="button"
                class="btn btn-ai"
                :disabled="!canStartFullAi || aiFullSubmitting || loading || loadingItems"
                :title="fullAiTitle"
                @click="startFullAiClassification"
              >
                {{ aiFullSubmitting ? 'Mengirim ke antrian…' : 'Klasifikasi AI semua & simpan' }}
              </button>
            </div>
          </div>

          <div v-if="loading" class="notice notice-loading">
            <span class="spinner" aria-hidden="true"></span>
            <span>Proses scrape berjalan…</span>
          </div>
          <div v-if="error" class="notice notice-error">{{ error }}</div>
        </div>

        <div v-if="placeInfo" class="panel place">
          <div class="place-title">{{ placeInfo.name }}</div>
          <div class="place-meta">
            <div v-if="placeInfo.address" class="muted">Alamat: {{ placeInfo.address }}</div>
            <div v-if="placeInfo.rating" class="muted">Rating: {{ placeInfo.rating }}</div>
            <div v-if="meta.total" class="muted">Total review: {{ meta.total }}</div>
            <div v-if="datasetId && apifyRequestedMax != null" class="muted">Target scrape Apify: {{ apifyRequestedMax }} review</div>
            <div v-if="datasetId" class="muted">Dataset: {{ datasetId }}</div>
          </div>
        </div>

        <div v-if="reviews.length" class="panel">
          <div class="table-head">
            <div class="table-title">Preview review (per halaman)</div>
            <div class="pager">
              <button type="button" class="pager-btn" :disabled="meta.page <= 1 || loadingItems" @click="goToPage(meta.page - 1)">
                Prev
              </button>
              <div class="pager-info">
                Hal {{ meta.page }} / {{ meta.lastPage }}
              </div>
              <button type="button" class="pager-btn" :disabled="meta.page >= meta.lastPage || loadingItems" @click="goToPage(meta.page + 1)">
                Next
              </button>
            </div>
          </div>

          <div v-if="loadingItems" class="notice notice-loading">
            <span class="spinner" aria-hidden="true"></span>
            <span>Loading halaman review…</span>
          </div>

          <div class="review-list">
            <div v-for="review in reviews" :key="reviewKey(review)" class="review-card">
              <div class="review-header">
                <div class="avatar">
                  <img v-if="review.profile_photo" :src="review.profile_photo" alt="profile" />
                  <div v-else class="avatar-fallback">{{ initials(review.author) }}</div>
                </div>
                <div class="info">
                  <div class="top">
                    <div class="author">{{ review.author || '-' }}</div>
                    <div class="rating">
                      <span v-for="n in 5" :key="n" class="star" :class="{ off: n > Math.floor(Number(review.rating) || 0) }">
                        ★
                      </span>
                      <span class="rating-num">{{ review.rating ? `(${review.rating})` : '' }}</span>
                    </div>
                  </div>
                  <div class="date">{{ review.date }}</div>
                </div>
              </div>
              <div class="review-text">{{ review.text || '-' }}</div>
            </div>
          </div>

          <div class="table-foot">
            <div class="muted">
              Menampilkan {{ reviews.length }} item di halaman ini
              <span v-if="datasetId">(total dataset {{ meta.total }}, per halaman {{ meta.perPage }})</span>.
            </div>
            <div class="pager">
              <button type="button" class="pager-btn" :disabled="meta.page <= 1 || loadingItems" @click="goToPage(1)">
                First
              </button>
              <button type="button" class="pager-btn" :disabled="meta.page >= meta.lastPage || loadingItems" @click="goToPage(meta.lastPage)">
                Last
              </button>
            </div>
          </div>
        </div>

        <div v-else-if="datasetId && !loadingItems" class="panel empty">
          Belum ada data review untuk ditampilkan.
        </div>

        <div id="dashboard-inline" class="panel dashboard-panel">
          <div class="table-head">
            <div class="table-title">Dashboard & AI Insights</div>
          </div>
          <div class="dash-cards">
            <div class="dash-card"><span>IG Posts</span><strong>{{ dash.cards.instagram_posts }}</strong></div>
            <div class="dash-card"><span>IG Comments</span><strong>{{ dash.cards.instagram_comments }}</strong></div>
            <div class="dash-card"><span>AI Reports</span><strong>{{ dash.cards.ai_reports_completed }}</strong></div>
            <div class="dash-card"><span>AI Items</span><strong>{{ dash.cards.ai_items_total }}</strong></div>
          </div>
          <div class="insight-grid">
            <div class="insight-box" v-for="(ins, idx) in dash.aiInsights" :key="`ins-${idx}`">
              <div class="ins-title">{{ ins.title }}</div>
              <div class="ins-detail">{{ ins.detail }}</div>
            </div>
          </div>
          <div class="dash-grid">
            <div class="insight-box">
              <div class="ins-title">Spike Mingguan - IG Comments</div>
              <div class="ins-detail">
                7 hari ini: <strong>{{ dash.weeklySpike.instagram_comments.current_7d }}</strong>,
                7 hari sebelumnya: <strong>{{ dash.weeklySpike.instagram_comments.previous_7d }}</strong>,
                perubahan: <strong>{{ dash.weeklySpike.instagram_comments.change_pct }}%</strong>
              </div>
            </div>
            <div class="insight-box">
              <div class="ins-title">Spike Mingguan - IG Negatif</div>
              <div class="ins-detail">
                7 hari ini: <strong>{{ dash.weeklySpike.instagram_negative.current_7d }}</strong>,
                7 hari sebelumnya: <strong>{{ dash.weeklySpike.instagram_negative.previous_7d }}</strong>,
                perubahan: <strong>{{ dash.weeklySpike.instagram_negative.change_pct }}%</strong>
              </div>
            </div>
          </div>
          <div class="panel subpanel">
            <div class="label">Rekomendasi Aksi Otomatis</div>
            <table class="mini-table">
              <thead>
                <tr><th>Channel</th><th>Topik</th><th>Aksi</th></tr>
              </thead>
              <tbody>
                <tr v-for="(a, idx) in dash.recommendedActions" :key="`ra-${idx}`">
                  <td>{{ a.channel }}</td>
                  <td>{{ a.topic }}</td>
                  <td>{{ a.action }}</td>
                </tr>
                <tr v-if="!dash.recommendedActions?.length">
                  <td colspan="3" class="muted">Belum cukup data untuk rekomendasi.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="dash-grid">
            <div>
              <div class="label">Sentimen Google ({{ dash.range.sentiment_days }} hari)</div>
              <div v-for="(v, k) in dash.sentiment.google" :key="`g-${k}`" class="bar-row bar-clickable" @click="openDrilldown('google', 'sentiment', k)">
                <span class="bar-label">{{ sevLabel(k) }}</span>
                <div class="bar"><div class="bar-fill" :style="{ width: `${barPct(v, dashGoogleMax)}%` }"></div></div>
                <strong class="bar-val">{{ v }}</strong>
              </div>
            </div>
            <div>
              <div class="label">Sentimen Instagram ({{ dash.range.sentiment_days }} hari)</div>
              <div v-for="(v, k) in dash.sentiment.instagram" :key="`i-${k}`" class="bar-row bar-clickable" @click="openDrilldown('instagram', 'sentiment', k)">
                <span class="bar-label">{{ sevLabel(k) }}</span>
                <div class="bar"><div class="bar-fill ig" :style="{ width: `${barPct(v, dashInstagramMax)}%` }"></div></div>
                <strong class="bar-val">{{ v }}</strong>
              </div>
            </div>
            <div>
              <div class="label">Top Isu Negatif Google</div>
              <div v-if="dash.topNegativeTopics.google?.length">
                <div v-for="row in dash.topNegativeTopics.google" :key="`tg-${row.topic}`" class="bar-row bar-clickable" @click="openDrilldown('google', 'topic', row.topic)">
                  <span class="bar-label">{{ row.topic }}</span>
                  <div class="bar"><div class="bar-fill" :style="{ width: `${barPct(row.total, dashTopicGoogleMax)}%` }"></div></div>
                  <strong class="bar-val">{{ row.total }}</strong>
                </div>
              </div>
              <div v-else class="muted">Belum ada data.</div>
            </div>
            <div>
              <div class="label">Top Isu Negatif Instagram</div>
              <div v-if="dash.topNegativeTopics.instagram?.length">
                <div v-for="row in dash.topNegativeTopics.instagram" :key="`ti-${row.topic}`" class="bar-row bar-clickable" @click="openDrilldown('instagram', 'topic', row.topic)">
                  <span class="bar-label">{{ row.topic }}</span>
                  <div class="bar"><div class="bar-fill ig" :style="{ width: `${barPct(row.total, dashTopicInstagramMax)}%` }"></div></div>
                  <strong class="bar-val">{{ row.total }}</strong>
                </div>
              </div>
              <div v-else class="muted">Belum ada data.</div>
            </div>
          </div>
          <div class="dash-grid mt10">
            <div>
              <div class="label">Tren 14 Hari (Komentar vs Klasifikasi)</div>
              <table class="mini-table">
                <thead>
                  <tr><th>Tanggal</th><th>IG Komentar</th><th>AI Klasifikasi</th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in dash.daily" :key="`d-${r.date}`">
                    <td>{{ r.date }}</td>
                    <td>{{ r.instagram_comments }}</td>
                    <td>{{ r.ai_classified }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div>
              <div class="label">Profil IG Dengan Rasio Negatif Tertinggi</div>
              <table class="mini-table">
                <thead>
                  <tr><th>Profil</th><th>Negatif</th><th>Total</th><th>Rasio</th></tr>
                </thead>
                <tbody>
                  <tr v-for="r in dash.profileRisk" :key="`pr-${r.profile}`">
                    <td>{{ r.profile }}</td>
                    <td>{{ r.negative_count }}</td>
                    <td>{{ r.total_count }}</td>
                    <td>{{ r.negative_rate }}%</td>
                  </tr>
                  <tr v-if="!dash.profileRisk?.length">
                    <td colspan="4" class="muted">Belum cukup data untuk analisa profil.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div v-if="drilldown.open" class="drill-modal-backdrop" @click.self="closeDrilldown">
          <div class="drill-modal">
            <div class="drill-head">
              <div>
                <div class="table-title">Detail {{ drilldown.channel }} - {{ drilldown.metric }}: {{ drilldown.key }}</div>
                <div class="muted">Total: {{ drilldown.total }} | Hal: {{ drilldown.page }}/{{ drilldown.lastPage }}</div>
              </div>
              <div class="drill-head-actions">
                <button type="button" class="btn btn-outline btn-sm" @click="exportDrilldownCsv">Export CSV</button>
                <button type="button" class="btn btn-outline btn-sm" @click="closeDrilldown">Tutup</button>
              </div>
            </div>
            <div class="drill-filter">
              <input v-model="drilldown.q" type="text" class="input-num" placeholder="Cari author/text/summary..." @keyup.enter="searchDrilldown" />
              <button type="button" class="btn btn-outline btn-sm" @click="searchDrilldown">Cari</button>
              <select v-model.number="drilldown.perPage" class="select select-sm" @change="loadDrilldownPage(1)">
                <option :value="50">50</option>
                <option :value="100">100</option>
                <option :value="120">120</option>
                <option :value="200">200</option>
              </select>
              <select v-model="drilldown.sort" class="select select-sm" @change="loadDrilldownPage(1)">
                <option value="date_desc">Date terbaru</option>
                <option value="date_asc">Date terlama</option>
                <option value="severity_desc">Severity Z-A</option>
                <option value="severity_asc">Severity A-Z</option>
              </select>
              <button type="button" class="btn btn-outline btn-sm" @click="loadDrilldownPage(Math.max(1, drilldown.page - 1))" :disabled="drilldown.page <= 1 || drilldown.loading">Prev</button>
              <button type="button" class="btn btn-outline btn-sm" @click="loadDrilldownPage(Math.min(drilldown.lastPage, drilldown.page + 1))" :disabled="drilldown.page >= drilldown.lastPage || drilldown.loading">Next</button>
            </div>
            <div v-if="drilldown.loading" class="notice notice-loading">Memuat detail...</div>
            <div v-else class="drill-body">
              <table class="mini-table">
                <thead>
                  <tr>
                    <th>Author</th>
                    <th>Date</th>
                    <th>Severity</th>
                    <th>Summary</th>
                    <th>Text</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="it in drilldown.items" :key="`dd-${it.id}`">
                    <td>{{ it.author || '-' }}</td>
                    <td>{{ it.review_date || '-' }}</td>
                    <td>{{ sevLabel(it.severity) }}</td>
                    <td v-html="highlightText(it.summary_id || '-')"></td>
                    <td v-html="highlightText(it.text || '-')"></td>
                  </tr>
                  <tr v-if="!drilldown.items.length">
                    <td colspan="5" class="muted">Tidak ada data.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        </div>

        <div v-show="activeTab === 'instagram'" class="tab-panel ig-tab">
          <div class="panel">
            <div class="ig-stats">
              <span>Post tersimpan: <strong>{{ instagramStatsLocal.posts }}</strong></span>
              <span>Komentar tersimpan: <strong>{{ instagramStatsLocal.comments }}</strong></span>
              <button type="button" class="btn btn-outline btn-sm" :disabled="igBusy" @click="refreshInstagramStats">Refresh angka</button>
            </div>
            <template v-if="instagramProfiles.length">
              <div class="ig-profiles">
                <div class="label">Pilih profil (untuk post &amp; komentar)</div>
                <div class="ig-checkboxes">
                  <label v-for="p in instagramProfiles" :key="p.key" class="ig-check">
                    <input v-model="igSelectedKeys" type="checkbox" :value="p.key" />
                    <span>{{ p.label }}</span>
                  </label>
                </div>
              </div>
              <div class="ig-date-range">
                <div class="control control-narrow">
                  <div class="label">Dari tanggal</div>
                  <input v-model="igDateFrom" type="date" class="input-num" :disabled="igBusy" />
                </div>
                <div class="control control-narrow">
                  <div class="label">Sampai tanggal</div>
                  <input v-model="igDateTo" type="date" class="input-num" :disabled="igBusy" />
                </div>
              </div>
              <div class="date-preset">
                <button type="button" class="btn btn-outline btn-sm" :disabled="igBusy" @click="applyInstagramPreset(7)">7 hari</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="igBusy" @click="applyInstagramPreset(30)">30 hari</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="igBusy" @click="applyInstagramPreset(90)">3 bulan</button>
                <button type="button" class="btn btn-outline btn-sm" :disabled="igBusy" @click="clearInstagramDateRange">Clear</button>
              </div>
            </template>
            <div v-else class="notice notice-muted">Belum ada profil di config.</div>
            <div class="ig-actions">
              <button
                type="button"
                class="btn btn-apify"
                :disabled="igBusy || igSelectedKeys.length === 0"
                title="Antrian: tarik posting /p/... dari Apify lalu upsert ke tabel instagram_posts"
                @click="instagramSyncPosts"
              >
                {{ igBusy && igBusyAction === 'posts' ? 'Mengantri…' : 'Sinkron posting (Apify)' }}
              </button>
              <button
                type="button"
                class="btn btn-success"
                :disabled="igBusy || igSelectedKeys.length === 0"
                title="Butuh post di DB dulu. Komentar di-batch per URL (bisa lama)."
                @click="instagramSyncComments"
              >
                {{ igBusy && igBusyAction === 'comments' ? 'Mengantri…' : 'Sinkron komentar (Apify)' }}
              </button>
              <button
                type="button"
                class="btn btn-ai"
                :disabled="igBusy || igSelectedKeys.length === 0"
                title="Klasifikasi AI komentar Instagram dari database (tanpa scrape ulang)."
                @click="startInstagramAiClassification"
              >
                Klasifikasi AI komentar DB
              </button>
            </div>
            <div v-if="igMessage" class="notice notice-loading">{{ igMessage }}</div>
            <div v-if="igError" class="notice notice-error">{{ igError }}</div>
            <div v-if="igProgress.status && igProgress.status !== 'not_found'" class="ig-progress-wrap">
              <div class="ig-progress-head">
                <span>Status: {{ igProgress.status }}</span>
                <strong>{{ igProgress.percent }}%</strong>
              </div>
              <div class="ig-progress-bar">
                <div class="ig-progress-fill" :style="{ width: `${igProgress.percent}%` }"></div>
              </div>
              <div class="ig-progress-msg">{{ igProgress.message }}</div>
            </div>
          </div>

          <div class="panel">
            <div class="table-head">
              <div class="table-title">Posting terbaru (database)</div>
              <button type="button" class="btn btn-outline btn-sm" :disabled="igListLoading" @click="loadInstagramRecentPosts">
                {{ igListLoading ? 'Memuat…' : 'Muat ulang' }}
              </button>
            </div>
            <div v-if="!igRecentPosts.length && !igListLoading" class="empty muted">Kosong — jalankan sinkron posting dulu.</div>
            <div v-else class="ig-post-table-wrap">
              <table class="ig-table">
                <thead>
                  <tr>
                    <th>Media</th>
                    <th>Profil</th>
                    <th>Owner</th>
                    <th>Shortcode</th>
                    <th>Caption</th>
                    <th>Likes</th>
                    <th>Views</th>
                    <th>Komentar (scraped)</th>
                    <th>Link</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in igRecentPosts" :key="row.id">
                    <td>
                      <img v-if="row.media_url" :src="row.media_url" alt="media" class="ig-thumb" />
                      <span v-else class="muted">-</span>
                    </td>
                    <td>{{ row.profile_key }}</td>
                    <td>{{ row.owner_username || '-' }}</td>
                    <td><code>{{ row.short_code }}</code></td>
                    <td class="ig-caption" :title="row.caption || ''">{{ row.caption || '-' }}</td>
                    <td>{{ row.likes_count || 0 }}</td>
                    <td>{{ row.views_count || 0 }}</td>
                    <td>{{ row.comments_count }}</td>
                    <td>
                      <a :href="row.post_url" target="_blank" rel="noopener" class="inline-link">Buka</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const outlets = page.props.outlets || []
const instagramProfiles = page.props.instagramProfiles || []
const dash = page.props.dashboardData || {
  cards: { instagram_posts: 0, instagram_comments: 0, ai_reports_completed: 0, ai_items_total: 0 },
  sentiment: {
    google: { positive: 0, neutral: 0, mild_negative: 0, negative: 0, severe: 0 },
    instagram: { positive: 0, neutral: 0, mild_negative: 0, negative: 0, severe: 0 },
  },
  daily: [],
  topProfiles: [],
  topNegativeTopics: { google: [], instagram: [] },
  profileRisk: [],
  aiInsights: [],
  weeklySpike: {
    instagram_comments: { current_7d: 0, previous_7d: 0, change_pct: 0 },
    instagram_negative: { current_7d: 0, previous_7d: 0, change_pct: 0 },
  },
  recommendedActions: [],
  range: { sentiment_days: 30, daily_days: 14 },
}

const activeTab = ref('google')
const instagramStatsLocal = ref({
  posts: Number(page.props.instagramStats?.posts ?? 0),
  comments: Number(page.props.instagramStats?.comments ?? 0),
})
const igSelectedKeys = ref(instagramProfiles.map((p) => p.key))
const igBusy = ref(false)
const igBusyAction = ref('')
const igMessage = ref('')
const igError = ref('')
const igRecentPosts = ref([])
const igListLoading = ref(false)
const igProgress = ref({ status: '', message: '', done: 0, total: 1, percent: 0 })
let igProgressTimer = null
const selectedOutlet = ref('')
const loading = ref(false)
const error = ref('')
const result = ref(page.props.result || null)

const datasetId = ref('')
const placeInfo = ref(null)
const reviews = ref([])
const meta = ref({ page: 1, perPage: 20, total: 0, lastPage: 1 })
const perPage = ref(20)
const maxApifyReviews = ref(500)
const maxScraperReviews = ref(0)
const googleDateFrom = ref('')
const googleDateTo = ref('')
const igDateFrom = ref('')
const igDateTo = ref('')
const apifyRequestedMax = ref(null)
const loadingItems = ref(false)
const exporting = ref(false)
const lastFetchSource = ref('')
const aiFullSubmitting = ref(false)
const drilldown = ref({
  open: false,
  loading: false,
  channel: '',
  metric: '',
  key: '',
  q: '',
  page: 1,
  lastPage: 1,
  total: 0,
  perPage: 120,
  sort: 'date_desc',
  items: [],
})

const selectedOutletRecord = computed(() => outlets.find((o) => o.place_id === selectedOutlet.value))
const dashGoogleMax = computed(() => Math.max(1, ...Object.values(dash.sentiment.google || {})))
const dashInstagramMax = computed(() => Math.max(1, ...Object.values(dash.sentiment.instagram || {})))
const dashTopicGoogleMax = computed(() => Math.max(1, ...((dash.topNegativeTopics?.google || []).map((r) => Number(r.total || 0)))))
const dashTopicInstagramMax = computed(() => Math.max(1, ...((dash.topNegativeTopics?.instagram || []).map((r) => Number(r.total || 0)))))

const canStartFullAi = computed(() => {
  if (datasetId.value) return true
  if (reviews.value.length && (lastFetchSource.value === 'places' || lastFetchSource.value === 'scraper')) {
    return true
  }
  return false
})

const fullAiTitle = computed(() => {
  if (datasetId.value) {
    return `Klasifikasi semua ${meta.value.total || '?'} review dari dataset Apify (bukan hanya halaman ini)`
  }
  if (reviews.value.length) {
    return `Klasifikasi ${reviews.value.length} review di memori (API / scraper)`
  }
  return ''
})

watch(
  () => page.props.result,
  (val) => {
    result.value = val
    loading.value = false

    if (val && val.success && val.dataset_id) {
      lastFetchSource.value = 'apify'
      apifyRequestedMax.value = val.max_reviews != null ? Number(val.max_reviews) : null
      datasetId.value = val.dataset_id
      placeInfo.value = val.place || null
      meta.value = {
        page: 1,
        perPage: perPage.value,
        total: Number(val.item_count || 0),
        lastPage: Math.max(1, Math.ceil(Number(val.item_count || 0) / perPage.value)),
      }
      goToPage(1)
      return
    }

    // API path sets place+reviews directly.
    if (val && val.success && val.reviews) {
      lastFetchSource.value = 'places'
      apifyRequestedMax.value = null
      datasetId.value = ''
      placeInfo.value = val.place || null
      reviews.value = Array.isArray(val.reviews) ? val.reviews : []
      meta.value = { page: 1, perPage: reviews.value.length || perPage.value, total: reviews.value.length || 0, lastPage: 1 }
    }
  }
)

function clampApifyMax() {
  const n = Number(maxApifyReviews.value)
  if (!Number.isFinite(n) || n < 1) return 200
  return Math.min(2000, Math.max(1, Math.floor(n)))
}

function sevLabel(k) {
  const m = {
    positive: 'Positif',
    neutral: 'Netral',
    mild_negative: 'Negatif ringan',
    negative: 'Negatif',
    severe: 'Sangat parah',
  }
  return m[k] || k
}

function barPct(v, max) {
  return Math.max(0, Math.min(100, Math.round((Number(v || 0) / Number(max || 1)) * 100)))
}

function scrollToDashboard() {
  activeTab.value = 'google'
  const el = document.getElementById('dashboard-inline')
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function openDrilldown(channel, metric, key) {
  drilldown.value = { ...drilldown.value, open: true, loading: true, channel, metric, key, q: '', page: 1, lastPage: 1, total: 0, perPage: 120, sort: 'date_desc', items: [] }
  await loadDrilldownPage(1)
}

async function loadDrilldownPage(pageNumber = 1) {
  drilldown.value = { ...drilldown.value, loading: true, page: pageNumber }
  try {
    const params = new URLSearchParams({
      channel: String(drilldown.value.channel),
      metric: String(drilldown.value.metric),
      key: String(drilldown.value.key),
      days: String(dash.range?.sentiment_days || 30),
      limit: String(drilldown.value.perPage || 120),
      page: String(pageNumber),
      q: String(drilldown.value.q || ''),
      sort: String(drilldown.value.sort || 'date_desc'),
    })
    const res = await fetch(`/google-review/dashboard/drilldown?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || data.success === false) {
      throw new Error(data.error || `HTTP ${res.status}`)
    }
    drilldown.value = {
      ...drilldown.value,
      items: Array.isArray(data.items) ? data.items : [],
      page: Number(data.meta?.page || 1),
      lastPage: Number(data.meta?.last_page || 1),
      total: Number(data.meta?.total || 0),
      perPage: Number(data.meta?.per_page || 120),
    }
  } catch (e) {
    drilldown.value = { ...drilldown.value, items: [], page: 1, lastPage: 1, total: 0, perPage: drilldown.value.perPage || 120 }
    error.value = e.message || 'Gagal memuat detail bar chart'
  } finally {
    drilldown.value = { ...drilldown.value, loading: false }
  }
}

async function searchDrilldown() {
  await loadDrilldownPage(1)
}

function exportDrilldownCsv() {
  const params = new URLSearchParams({
    channel: String(drilldown.value.channel),
    metric: String(drilldown.value.metric),
    key: String(drilldown.value.key),
    days: String(dash.range?.sentiment_days || 30),
    q: String(drilldown.value.q || ''),
      sort: String(drilldown.value.sort || 'date_desc'),
  })
  window.location.href = `/google-review/dashboard/drilldown/export?${params.toString()}`
}

function closeDrilldown() {
  drilldown.value = { ...drilldown.value, open: false }
}

function highlightText(text) {
  const t = String(text || '')
  const q = String(drilldown.value.q || '').trim()
  if (!q) return t
  const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const re = new RegExp(`(${escaped})`, 'ig')
  return t.replace(re, '<mark>$1</mark>')
}

function fmtDate(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function dateRangeInvalid(from, to) {
  return Boolean(from && to && from > to)
}

function applyRangePreset(setFrom, setTo, days) {
  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - Math.max(0, Number(days) - 1))
  setFrom(fmtDate(start))
  setTo(fmtDate(end))
}

function applyGooglePreset(days) {
  applyRangePreset((v) => { googleDateFrom.value = v }, (v) => { googleDateTo.value = v }, days)
}

function clearGoogleDateRange() {
  googleDateFrom.value = ''
  googleDateTo.value = ''
}

function applyInstagramPreset(days) {
  applyRangePreset((v) => { igDateFrom.value = v }, (v) => { igDateTo.value = v }, days)
}

function clearInstagramDateRange() {
  igDateFrom.value = ''
  igDateTo.value = ''
}

function fetchReviews() {
  lastFetchSource.value = 'places'
  apifyRequestedMax.value = null
  datasetId.value = ''
  loading.value = true
  error.value = ''
  router.post(
    '/google-review/fetch',
    { place_id: selectedOutlet.value },
    {
      preserveState: true,
      onError: (err) => {
        error.value = err.error || 'Terjadi kesalahan'
        loading.value = false
      }
    }
  )
}

function fetchScrapedReviews() {
  lastFetchSource.value = 'scraper'
  loading.value = true
  error.value = ''
  fetch('/scraped-reviews')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        apifyRequestedMax.value = null
        datasetId.value = ''
        placeInfo.value = { name: 'Review Scraper', address: '', rating: '' }
        let list = Array.isArray(data.reviews) ? data.reviews : []
        const lim = Number(maxScraperReviews.value)
        if (Number.isFinite(lim) && lim > 0) {
          list = list.slice(0, Math.min(2000, Math.floor(lim)))
        } else {
          list = list.slice(0, 2000)
        }
        reviews.value = list
        meta.value = { page: 1, perPage: reviews.value.length || perPage.value, total: reviews.value.length || 0, lastPage: 1 }
      } else {
        error.value = data.error || 'Gagal ambil review scraper'
      }
      loading.value = false
    })
    .catch(() => {
      error.value = 'Gagal ambil review scraper'
      loading.value = false
    })
}

function fetchApifyReviews() {
  if (dateRangeInvalid(googleDateFrom.value, googleDateTo.value)) {
    error.value = 'Range tanggal Google tidak valid: "Sampai tanggal" harus sama/lebih besar dari "Dari tanggal".'
    return
  }
  lastFetchSource.value = 'apify'
  loading.value = true
  error.value = ''
  router.post(
    '/google-review/fetch-apify',
    {
      place_id: selectedOutlet.value,
      max_reviews: clampApifyMax(),
      date_from: googleDateFrom.value || null,
      date_to: googleDateTo.value || null,
    },
    {
      preserveState: true,
      onError: (err) => {
        error.value = err.error || 'Gagal ambil review Apify'
        loading.value = false
      }
    }
  )
}

watch(perPage, () => {
  if (!datasetId.value) return
  goToPage(1)
})

function goToPage(pageNumber) {
  if (!datasetId.value) return
  loadingItems.value = true
  error.value = ''

  const params = new URLSearchParams({
    dataset_id: datasetId.value,
    page: String(pageNumber),
    per_page: String(perPage.value),
  })
  if (googleDateFrom.value) params.set('date_from', googleDateFrom.value)
  if (googleDateTo.value) params.set('date_to', googleDateTo.value)

  fetch(`/google-review/apify/items?${params.toString()}`)
    .then(res => res.json())
    .then((data) => {
      if (!data.success) {
        error.value = data.error || 'Gagal load items'
        return
      }
      reviews.value = Array.isArray(data.reviews) ? data.reviews : []
      meta.value = data.meta || meta.value
    })
    .catch(() => {
      error.value = 'Gagal load items'
    })
    .finally(() => {
      loadingItems.value = false
    })
}

function exportExcel() {
  if (!datasetId.value) return
  exporting.value = true
  const params = new URLSearchParams({ dataset_id: datasetId.value })
  if (googleDateFrom.value) params.set('date_from', googleDateFrom.value)
  if (googleDateTo.value) params.set('date_to', googleDateTo.value)
  window.location.href = `/google-review/apify/export?${params.toString()}`
  setTimeout(() => (exporting.value = false), 1500)
}

async function startFullAiClassification() {
  if (!canStartFullAi.value || aiFullSubmitting.value) return
  if (dateRangeInvalid(googleDateFrom.value, googleDateTo.value)) {
    error.value = 'Range tanggal Google tidak valid: "Sampai tanggal" harus sama/lebih besar dari "Dari tanggal".'
    return
  }
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  let body
  const r = placeInfo.value?.rating
  const placePayload = {
    name: placeInfo.value?.name,
    address: placeInfo.value?.address,
    rating: r !== undefined && r !== null && r !== '' ? String(r) : null,
  }
  if (datasetId.value) {
    body = {
      source: 'apify_dataset',
      dataset_id: datasetId.value,
      place_id: selectedOutlet.value || null,
      id_outlet: selectedOutletRecord.value?.id ?? null,
      nama_outlet: selectedOutletRecord.value?.nama_outlet ?? null,
      place: placePayload,
      date_from: googleDateFrom.value || null,
      date_to: googleDateTo.value || null,
    }
  } else if (reviews.value.length && (lastFetchSource.value === 'places' || lastFetchSource.value === 'scraper')) {
    body = {
      source: lastFetchSource.value === 'places' ? 'places_api' : 'scraper_inline',
      place_id: selectedOutlet.value || null,
      id_outlet: selectedOutletRecord.value?.id ?? null,
      nama_outlet: selectedOutletRecord.value?.nama_outlet ?? null,
      place: placePayload,
      reviews: reviews.value,
    }
  } else {
    error.value = 'Ambil review dulu (Apify, API Google, atau Scraper).'
    return
  }

  aiFullSubmitting.value = true
  error.value = ''
  try {
    const res = await fetch('/google-review/ai/reports', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
      credentials: 'same-origin',
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      throw new Error(data.error || data.message || `HTTP ${res.status}`)
    }
    router.visit(`/google-review/ai/reports/${data.id}`)
  } catch (e) {
    error.value = e.message || 'Gagal membuat laporan AI'
  } finally {
    aiFullSubmitting.value = false
  }
}

function reviewKey(review) {
  return `${review.time || ''}:${review.author || ''}:${(review.text || '').slice(0, 20)}`
}

function initials(name) {
  const n = String(name || '').trim()
  if (!n) return '?'
  const parts = n.split(/\s+/).slice(0, 2)
  return parts.map(p => p.charAt(0).toUpperCase()).join('')
}

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

async function refreshInstagramStats() {
  try {
    const res = await fetch('/google-review/instagram/stats', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const data = await res.json().catch(() => ({}))
    if (data.success) {
      instagramStatsLocal.value = { posts: Number(data.posts), comments: Number(data.comments) }
    }
  } catch {
    /* abaikan */
  }
}

async function loadInstagramRecentPosts() {
  igListLoading.value = true
  try {
    const res = await fetch('/google-review/instagram/recent-posts?limit=30', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const data = await res.json().catch(() => ({}))
    igRecentPosts.value = Array.isArray(data.posts) ? data.posts : []
  } finally {
    igListLoading.value = false
  }
}

function openInstagramTab() {
  activeTab.value = 'instagram'
  stopInstagramProgressPolling()
  refreshInstagramStats()
  loadInstagramRecentPosts()
}

function stopInstagramProgressPolling() {
  if (igProgressTimer) {
    clearInterval(igProgressTimer)
    igProgressTimer = null
  }
}

async function pollInstagramProgress(operationId) {
  if (!operationId) return
  stopInstagramProgressPolling()
  const fetchProgress = async () => {
    try {
      const res = await fetch(`/google-review/instagram/progress?operation_id=${encodeURIComponent(operationId)}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
      const data = await res.json().catch(() => ({}))
      if (!res.ok || data.success === false) {
        return
      }
      const done = Number(data.progress_done || 0)
      const total = Math.max(1, Number(data.progress_total || 1))
      const percent = Math.max(0, Math.min(100, Math.round((done / total) * 100)))
      igProgress.value = {
        status: data.status || '',
        message: data.message || '',
        done,
        total,
        percent,
      }
      if (['completed', 'completed_with_errors', 'failed'].includes(data.status)) {
        stopInstagramProgressPolling()
        await refreshInstagramStats()
        await loadInstagramRecentPosts()
      }
    } catch {
      // ignore transient errors
    }
  }

  await fetchProgress()
  igProgressTimer = setInterval(fetchProgress, 2500)
}

async function instagramSyncPosts() {
  if (!igSelectedKeys.value.length) return
  if (dateRangeInvalid(igDateFrom.value, igDateTo.value)) {
    igError.value = 'Range tanggal Instagram tidak valid: "Sampai tanggal" harus sama/lebih besar dari "Dari tanggal".'
    return
  }
  igBusy.value = true
  igBusyAction.value = 'posts'
  igMessage.value = ''
  igError.value = ''
  igProgress.value = { status: 'queued', message: 'Mengirim request sinkron posting...', done: 0, total: 1, percent: 0 }
  try {
    const res = await fetch('/google-review/instagram/sync-posts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        profile_keys: igSelectedKeys.value,
        date_from: igDateFrom.value || null,
        date_to: igDateTo.value || null,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      throw new Error(data.message || data.error || `HTTP ${res.status}`)
    }
    igMessage.value = data.message || 'Job diantrikan.'
    await pollInstagramProgress(data.operation_id)
    if (data.ran_sync) {
      await refreshInstagramStats()
      await loadInstagramRecentPosts()
    }
  } catch (e) {
    igError.value = e.message || 'Gagal mengantrikan sinkron posting'
  } finally {
    igBusy.value = false
    igBusyAction.value = ''
  }
}

async function instagramSyncComments() {
  if (!igSelectedKeys.value.length) return
  if (dateRangeInvalid(igDateFrom.value, igDateTo.value)) {
    igError.value = 'Range tanggal Instagram tidak valid: "Sampai tanggal" harus sama/lebih besar dari "Dari tanggal".'
    return
  }
  igBusy.value = true
  igBusyAction.value = 'comments'
  igMessage.value = ''
  igError.value = ''
  igProgress.value = { status: 'queued', message: 'Mengirim request sinkron komentar...', done: 0, total: 1, percent: 0 }
  try {
    const res = await fetch('/google-review/instagram/sync-comments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        profile_keys: igSelectedKeys.value,
        date_from: igDateFrom.value || null,
        date_to: igDateTo.value || null,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      throw new Error(data.message || data.error || `HTTP ${res.status}`)
    }
    igMessage.value = data.message || 'Job diantrikan.'
    await pollInstagramProgress(data.operation_id)
    if (data.ran_sync) {
      await refreshInstagramStats()
      await loadInstagramRecentPosts()
    }
  } catch (e) {
    igError.value = e.message || 'Gagal mengantrikan sinkron komentar'
  } finally {
    igBusy.value = false
    igBusyAction.value = ''
  }
}

async function startInstagramAiClassification() {
  if (!igSelectedKeys.value.length) return
  if (dateRangeInvalid(igDateFrom.value, igDateTo.value)) {
    igError.value = 'Range tanggal Instagram tidak valid: "Sampai tanggal" harus sama/lebih besar dari "Dari tanggal".'
    return
  }
  igBusy.value = true
  igBusyAction.value = 'ai'
  igMessage.value = ''
  igError.value = ''
  try {
    const res = await fetch('/google-review/ai/reports', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        source: 'instagram_comments_db',
        place: { name: 'Instagram Comments (Database)' },
        place_id: null,
        id_outlet: null,
        nama_outlet: null,
        profile_keys: igSelectedKeys.value,
        date_from: igDateFrom.value || null,
        date_to: igDateTo.value || null,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      throw new Error(data.error || data.message || `HTTP ${res.status}`)
    }
    router.visit(`/google-review/ai/reports/${data.id}`)
  } catch (e) {
    igError.value = e.message || 'Gagal membuat laporan AI komentar Instagram'
  } finally {
    igBusy.value = false
    igBusyAction.value = ''
  }
}
</script>

<style scoped>
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 18px 16px 40px;
}
.header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.header-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.btn-history {
  flex-shrink: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #ede9fe;
  color: #5b21b6;
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
  border: 1px solid #ddd6fe;
}
.btn-history:hover {
  background: #ddd6fe;
}
.inline-link {
  color: #2563eb;
  font-weight: 600;
  text-decoration: underline;
}
.title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}
.subtitle {
  margin-top: 2px;
  font-size: 13px;
  color: #6b7280;
}
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
  box-shadow: 0 2px 12px rgba(17, 24, 39, 0.04);
  margin-bottom: 12px;
}
.controls {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.control {
  min-width: 280px;
}
.control-narrow {
  min-width: 140px;
  max-width: 180px;
}
.control-wide {
  min-width: 260px;
}
.date-preset {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.dashboard-panel {
  margin-top: 8px;
}
.dash-cards {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}
.dash-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px;
  display: flex;
  justify-content: space-between;
}
.dash-card span { font-size: 12px; color: #6b7280; }
.dash-card strong { font-size: 18px; color: #111827; }
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.bar-row {
  display: grid;
  grid-template-columns: 110px 1fr 44px;
  gap: 8px;
  align-items: center;
  margin: 6px 0;
}
.bar-clickable {
  cursor: pointer;
}
.bar-clickable:hover {
  background: #f8fafc;
  border-radius: 6px;
}
.bar-label { font-size: 12px; color: #374151; }
.bar {
  height: 10px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}
.bar-fill {
  height: 10px;
  background: linear-gradient(90deg, #3b82f6, #2563eb);
}
.bar-fill.ig {
  background: linear-gradient(90deg, #10b981, #059669);
}
.bar-val { font-size: 12px; color: #111827; text-align: right; }
.insight-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}
.insight-box {
  border: 1px solid #dbeafe;
  background: #eff6ff;
  border-radius: 8px;
  padding: 10px;
}
.ins-title {
  font-size: 12px;
  font-weight: 700;
  color: #1e3a8a;
  margin-bottom: 4px;
}
.ins-detail {
  font-size: 12px;
  color: #1f2937;
}
.mt10 {
  margin-top: 10px;
}
.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.mini-table th,
.mini-table td {
  border-bottom: 1px solid #e5e7eb;
  padding: 6px;
  text-align: left;
}
.mini-table th {
  color: #6b7280;
  font-weight: 700;
}
.subpanel {
  margin-top: 10px;
  margin-bottom: 10px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.drill-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}
.drill-modal {
  width: min(1200px, 96vw);
  max-height: 88vh;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}
.drill-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid #e5e7eb;
}
.drill-head-actions {
  display: flex;
  gap: 8px;
}
.drill-filter {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 10px 14px;
  border-bottom: 1px solid #e5e7eb;
}
.drill-filter .input-num {
  max-width: 360px;
}
.select-sm {
  width: auto;
  min-width: 130px;
  padding: 8px 10px;
}
.drill-body {
  padding: 10px 14px 14px;
  max-height: 72vh;
  overflow: auto;
}
.input-num {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  outline: none;
  font-size: 14px;
}
.input-num:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}
.input-num:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}
.select {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  outline: none;
}
.select:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}
.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.btn {
  border: 1px solid transparent;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.05s ease, opacity 0.2s ease, box-shadow 0.2s ease;
  user-select: none;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn:active:not(:disabled) {
  transform: translateY(1px);
}
.btn-primary {
  background: #2563eb;
  color: #fff;
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.18);
}
.btn-success {
  background: #059669;
  color: #fff;
  box-shadow: 0 6px 18px rgba(5, 150, 105, 0.16);
}
.btn-apify {
  background: #7c3aed;
  color: #fff;
  box-shadow: 0 6px 18px rgba(124, 58, 237, 0.16);
}
.btn-outline {
  background: #fff;
  border-color: #d1d5db;
  color: #111827;
}
.btn-ai {
  background: linear-gradient(135deg, #6366f1, #7c3aed);
  color: #fff;
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.22);
}
.code-inline {
  font-size: 11px;
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 4px;
  color: #374151;
}
.notice {
  margin-top: 12px;
  font-size: 13px;
  color: #374151;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px 12px;
}
.notice-loading {
  display: flex;
  align-items: center;
  gap: 10px;
}
.spinner {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 2px solid rgba(17, 24, 39, 0.18);
  border-top-color: rgba(37, 99, 235, 0.9);
  animation: spin 0.85s linear infinite;
  flex: 0 0 auto;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.notice-error {
  background: #fef2f2;
  border-color: #fecaca;
  color: #991b1b;
}
.notice-muted {
  background: #f9fafb;
  border-color: #e5e7eb;
  color: #6b7280;
}
.place-title {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}
.place-meta {
  margin-top: 6px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.muted {
  font-size: 12px;
  color: #6b7280;
}
.table-head,
.table-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.table-title {
  font-weight: 700;
  color: #111827;
}
.pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pager-btn {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 10px;
  padding: 8px 10px;
  font-weight: 600;
  cursor: pointer;
}
.pager-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pager-info {
  font-size: 12px;
  color: #6b7280;
  min-width: 92px;
  text-align: center;
}
.review-list {
  margin-top: 12px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.review-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px 14px;
}
.review-header {
  display: flex;
  gap: 12px;
  align-items: center;
}
.avatar {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  background: #f3f4f6;
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.avatar-fallback {
  font-weight: 800;
  color: #374151;
  font-size: 14px;
}
.info {
  min-width: 0;
  flex: 1;
}
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.author {
  font-weight: 800;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rating {
  display: flex;
  align-items: center;
  gap: 2px;
  color: #f59e0b;
  font-size: 12px;
  flex: 0 0 auto;
}
.star.off {
  color: #e5e7eb;
}
.rating-num {
  color: #6b7280;
  margin-left: 6px;
  font-size: 12px;
}
.date {
  margin-top: 2px;
  font-size: 12px;
  color: #6b7280;
}
.review-text {
  margin-top: 10px;
  font-size: 13px;
  color: #111827;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}
.empty {
  color: #6b7280;
  font-size: 13px;
}
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.tab {
  border: 1px solid #d1d5db;
  background: #f9fafb;
  color: #374151;
  border-radius: 10px;
  padding: 10px 16px;
  font-weight: 700;
  cursor: pointer;
  font-size: 14px;
}
.tab.active {
  background: #111827;
  color: #fff;
  border-color: #111827;
}
.tab-panel {
  min-height: 120px;
}
.ig-stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px 20px;
  margin-bottom: 14px;
  font-size: 14px;
}
.ig-profiles {
  margin-bottom: 12px;
}
.ig-checkboxes {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  margin-top: 8px;
}
.ig-check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
}
.ig-date-range {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  margin-bottom: 10px;
}
.ig-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 8px;
}
.ig-progress-wrap {
  margin-top: 10px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 10px;
  padding: 10px;
}
.ig-progress-head {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #374151;
  margin-bottom: 6px;
}
.ig-progress-bar {
  width: 100%;
  height: 10px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}
.ig-progress-fill {
  height: 10px;
  background: linear-gradient(90deg, #2563eb, #22c55e);
  transition: width 0.25s ease;
}
.ig-progress-msg {
  font-size: 12px;
  margin-top: 6px;
  color: #6b7280;
}
.btn-sm {
  padding: 6px 10px;
  font-size: 13px;
}
.ig-post-table-wrap {
  overflow-x: auto;
  margin-top: 10px;
}
.ig-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ig-table th,
.ig-table td {
  border: 1px solid #e5e7eb;
  padding: 8px 10px;
  text-align: left;
}
.ig-table th {
  background: #f9fafb;
  font-weight: 700;
}
.ig-thumb {
  width: 56px;
  height: 56px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}
.ig-caption {
  max-width: 300px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
@media (max-width: 1024px) {
  .dash-cards {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .insight-grid {
    grid-template-columns: 1fr;
  }
  .dash-grid {
    grid-template-columns: 1fr;
  }
}
</style> 