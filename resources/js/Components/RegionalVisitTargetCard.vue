<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    isNight: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: true,
    },
});

const loading = ref(true);
const data = ref(null);
const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? null);

const visible = computed(() => data.value !== null);
const summary = computed(() => data.value?.summary || {});
const outlets = computed(() => data.value?.outlets || []);
const period = computed(() => data.value?.period || {});
const achievementPct = computed(() => {
    const pct = summary.value?.achievement_pct;
    return pct != null ? Number(pct) : null;
});

const progressBarClass = computed(() => {
    const pct = achievementPct.value;
    if (pct == null) return 'bg-slate-400';
    if (pct >= 100) return 'bg-emerald-500';
    if (pct >= 50) return 'bg-amber-500';
    return 'bg-rose-500';
});

function outletStatusClass(status) {
    if (status === 'achieved') {
        return props.isNight ? 'bg-emerald-900/40 text-emerald-200 border-emerald-700' : 'bg-emerald-50 text-emerald-800 border-emerald-200';
    }
    if (status === 'partial') {
        return props.isNight ? 'bg-amber-900/40 text-amber-200 border-amber-700' : 'bg-amber-50 text-amber-900 border-amber-200';
    }
    return props.isNight ? 'bg-slate-700/50 text-slate-300 border-slate-600' : 'bg-slate-50 text-slate-600 border-slate-200';
}

function outletStatusLabel(status) {
    if (status === 'achieved') return 'Tercapai';
    if (status === 'partial') return 'Sebagian';
    return 'Belum';
}

async function load() {
    loading.value = true;
    try {
        const res = await fetch(route('home.regional-visit-summary'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        data.value = json.is_regional && json.data ? json.data : null;
    } catch {
        data.value = null;
    } finally {
        loading.value = false;
    }
}

function openVisitReport() {
    if (!data.value?.period || !userId.value) return;
    router.visit(route('regional.visit-report.index', {
        user_id: userId.value,
        bulan: period.value.bulan,
        tahun: period.value.tahun,
    }));
}

onMounted(load);
</script>

<template>
    <div v-if="loading || visible" class="mt-3">
        <div
            class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500"
            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-amber-200/80'"
        >
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <i class="fa-solid fa-map-location-dot shrink-0" :class="isNight ? 'text-amber-400' : 'text-amber-600'" />
                    <h3 class="font-semibold truncate" :class="[compact ? 'text-sm' : 'text-base', isNight ? 'text-white' : 'text-slate-800']">
                        Target Kunjungan Outlet
                    </h3>
                </div>
                <button
                    v-if="visible && !loading"
                    type="button"
                    class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-lg transition"
                    :class="isNight ? 'text-amber-200 hover:bg-slate-700' : 'text-amber-700 hover:bg-amber-50'"
                    @click="openVisitReport"
                >
                    Detail
                </button>
            </div>

            <div v-if="loading" class="text-sm py-2 text-center" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                Memuat target kunjungan…
            </div>

            <template v-else-if="visible">
                <p class="text-[11px] mb-3" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                    Periode payroll: {{ period.label || '—' }}
                </p>

                <div class="rounded-xl border p-3 mb-3" :class="isNight ? 'border-amber-700/50 bg-amber-950/20' : 'border-amber-200 bg-amber-50/80'">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-medium" :class="isNight ? 'text-amber-200/80' : 'text-amber-800'">Pencapaian</p>
                            <p class="text-2xl font-bold leading-tight" :class="isNight ? 'text-white' : 'text-amber-950'">
                                {{ summary.total_actual ?? 0 }}
                                <span class="text-sm font-semibold opacity-70">/ {{ summary.portfolio_target ?? 0 }}</span>
                            </p>
                            <p class="text-[11px] mt-0.5" :class="isNight ? 'text-slate-400' : 'text-slate-600'">
                                hari kunjungan (target outlet)
                            </p>
                        </div>
                        <div v-if="achievementPct != null" class="text-right">
                            <p class="text-2xl font-bold" :class="achievementPct >= 100 ? 'text-emerald-500' : (achievementPct >= 50 ? 'text-amber-500' : 'text-rose-500')">
                                {{ achievementPct }}%
                            </p>
                        </div>
                    </div>
                    <div v-if="achievementPct != null" class="mt-3 h-2 rounded-full overflow-hidden" :class="isNight ? 'bg-slate-700' : 'bg-white'">
                        <div class="h-full rounded-full transition-all duration-500" :class="progressBarClass" :style="{ width: Math.min(100, achievementPct) + '%' }" />
                    </div>
                    <p v-if="data.has_outlet_targets" class="text-[10px] mt-2" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                        {{ summary.visited_outlet_count ?? 0 }} / {{ summary.configured_outlet_count ?? 0 }} outlet sudah dikunjungi
                    </p>
                </div>

                <div v-if="outlets.length" class="space-y-1.5 max-h-40 overflow-y-auto">
                    <div
                        v-for="row in outlets"
                        :key="row.outlet_id"
                        class="flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2 text-xs"
                        :class="outletStatusClass(row.status)"
                    >
                        <span class="truncate font-medium">{{ row.outlet_name }}</span>
                        <span class="shrink-0 font-semibold tabular-nums">
                            {{ row.actual_visits }}/{{ row.target_visits }}
                        </span>
                    </div>
                </div>

                <p v-else-if="!summary.portfolio_target" class="text-xs italic" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                    Belum ada target kunjungan di Regional Management.
                </p>
            </template>
        </div>
    </div>
</template>
