<template>
    <div class="relative w-40 h-40 rounded-full bg-gradient-to-br from-slate-50 to-slate-100 border-4 border-slate-200 shadow-2xl flex items-center justify-center backdrop-blur-sm">
        <!-- Hour markers - very simple -->
        <div v-for="n in 12" :key="n" :style="{
            position: 'absolute',
            width: n % 3 === 0 ? '2px' : '1px',
            height: n % 3 === 0 ? '14px' : '6px',
            background: n % 3 === 0 ? '#3b82f6' : '#94a3b8',
            top: n % 3 === 0 ? '10px' : '14px',
            left: '50%',
            transform: `translateX(-50%) rotate(${n*30}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '1px',
            zIndex: 1,
        }"></div>
        
        <!-- Hour numbers - positioned outside markers -->
        <div v-for="n in 12" :key="`num-${n}`" :style="{
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: `translate(-50%, -50%) rotate(${n*30}deg) translateY(-50px) rotate(${-n*30}deg)`,
            fontSize: '9px',
            fontWeight: '600',
            color: '#374151',
            textShadow: '0 1px 2px rgba(255, 255, 255, 0.8)',
            zIndex: 2,
            pointerEvents: 'none',
        }">{{ n }}</div>
        
        <!-- Hour hand -->
        <div :style="{
            position: 'absolute',
            width: '4px',
            height: '35px',
            background: 'linear-gradient(to bottom, #1e40af, #3b82f6)',
            top: '50%',
            left: '50%',
            transform: `translate(-50%, -100%) rotate(${getAngle(date.getHours()%12,12)+date.getMinutes()/2}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
            boxShadow: '0 2px 6px rgba(30, 64, 175, 0.4)',
            zIndex: 5,
        }"></div>
        
        <!-- Minute hand -->
        <div :style="{
            position: 'absolute',
            width: '3px',
            height: '45px',
            background: 'linear-gradient(to bottom, #059669, #10b981)',
            top: '50%',
            left: '50%',
            transform: `translate(-50%, -100%) rotate(${getAngle(date.getMinutes(),60)}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
            boxShadow: '0 2px 6px rgba(5, 150, 105, 0.4)',
            zIndex: 6,
        }"></div>
        
        <!-- Second hand -->
        <div :style="{
            position: 'absolute',
            width: '1px',
            height: '50px',
            background: 'linear-gradient(to bottom, #dc2626, #ef4444)',
            top: '50%',
            left: '50%',
            transform: `translate(-50%, -100%) rotate(${getAngle(date.getSeconds(),60)}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '1px',
            boxShadow: '0 1px 3px rgba(220, 38, 38, 0.5)',
            zIndex: 7,
        }"></div>
        
        <!-- Center dot with gradient -->
        <div class="absolute w-4 h-4 bg-gradient-to-br from-blue-600 to-purple-600 rounded-full shadow-lg border-2 border-white z-10 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></div>
        
        <!-- Inner decorative ring -->
        <div class="absolute w-32 h-32 rounded-full border border-slate-200/30 z-0"></div>
        
    </div>
</template>

<script setup>
const props = defineProps({
    date: {
        type: Date,
        required: true
    }
});

const getAngle = (unit, max) => (unit / max) * 360;

</script>

<style scoped>
/* Clean and minimal styling for date display */
</style> 