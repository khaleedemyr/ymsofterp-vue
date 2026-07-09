<script setup>
import { computed } from 'vue';

const props = defineProps({
    schedules: {
        type: Object,
        default: () => ({ participant: [], trainer: [] }),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    isNight: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const participantSchedules = computed(() => props.schedules?.participant || []);
const trainerSchedules = computed(() => props.schedules?.trainer || []);
const totalCount = computed(() => participantSchedules.value.length + trainerSchedules.value.length);
const hasSchedules = computed(() => totalCount.value > 0);
const itemLimit = computed(() => (props.compact ? 2 : 3));

function statusClass(status) {
    if (status === 'ongoing') {
        return props.isNight ? 'bg-emerald-700 text-emerald-200' : 'bg-emerald-100 text-emerald-800';
    }
    return props.isNight ? 'bg-indigo-700 text-indigo-200' : 'bg-indigo-100 text-indigo-800';
}
</script>

<template>
    <div v-if="loading || hasSchedules"
         class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500"
         :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap" :class="compact ? 'text-base' : 'text-lg', isNight ? 'text-indigo-400' : 'text-indigo-600'"></i>
                <h3 class="font-semibold" :class="[compact ? 'text-sm' : 'text-lg', isNight ? 'text-white' : 'text-slate-800']">
                    Training Plan Saya
                </h3>
            </div>
            <div v-if="!loading && hasSchedules" class="text-xs px-2 py-1 rounded-full"
                 :class="isNight ? 'bg-indigo-700 text-indigo-200' : 'bg-indigo-100 text-indigo-800'">
                {{ totalCount }} jadwal
            </div>
        </div>

        <div v-if="loading" class="text-sm py-3 text-center" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
            Memuat jadwal training...
        </div>

        <div v-else class="space-y-3">
            <div v-if="participantSchedules.length > 0">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-user-graduate text-xs" :class="isNight ? 'text-blue-400' : 'text-blue-600'"></i>
                    <h4 class="text-xs font-semibold" :class="isNight ? 'text-blue-200' : 'text-blue-800'">Sebagai Peserta</h4>
                </div>
                <div class="space-y-2">
                    <a v-for="schedule in participantSchedules.slice(0, itemLimit)" :key="'p-' + schedule.id"
                       :href="schedule.trainee_url || schedule.url"
                       class="block p-3 rounded-lg border transition-all duration-200 hover:shadow-md"
                       :class="isNight ? 'bg-slate-700/50 border-slate-600 hover:bg-slate-700' : 'bg-blue-50 border-blue-200 hover:bg-blue-100'">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm truncate" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ schedule.program_title || schedule.title }}
                                </div>
                                <div class="text-xs mt-1 space-y-0.5" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <div><i class="fa-regular fa-calendar mr-1"></i>{{ schedule.start_label }}</div>
                                    <div v-if="!compact && schedule.trainer_names && schedule.trainer_names !== '—'">
                                        <i class="fa-solid fa-chalkboard-user mr-1"></i>{{ schedule.trainer_names }}
                                    </div>
                                </div>
                            </div>
                            <span v-if="!compact" class="text-xs px-2 py-0.5 rounded-full" :class="statusClass(schedule.status)">
                                {{ schedule.status_label }}
                            </span>
                        </div>
                    </a>
                </div>
            </div>

            <div v-if="trainerSchedules.length > 0">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-chalkboard-user text-xs" :class="isNight ? 'text-violet-400' : 'text-violet-600'"></i>
                    <h4 class="text-xs font-semibold" :class="isNight ? 'text-violet-200' : 'text-violet-800'">Sebagai Trainer</h4>
                </div>
                <div class="space-y-2">
                    <a v-for="schedule in trainerSchedules.slice(0, itemLimit)" :key="'t-' + schedule.id"
                       :href="schedule.trainer_url || schedule.url"
                       class="block p-3 rounded-lg border transition-all duration-200 hover:shadow-md"
                       :class="isNight ? 'bg-slate-700/50 border-slate-600 hover:bg-slate-700' : 'bg-violet-50 border-violet-200 hover:bg-violet-100'">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm truncate" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ schedule.program_title || schedule.title }}
                                </div>
                                <div class="text-xs mt-1 space-y-0.5" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <div><i class="fa-regular fa-calendar mr-1"></i>{{ schedule.start_label }}</div>
                                    <div v-if="!compact"><i class="fa-solid fa-users mr-1"></i>{{ schedule.participants_count }} peserta</div>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                  :class="isNight ? 'bg-violet-700 text-violet-200' : 'bg-violet-100 text-violet-800'">
                                Trainer
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
