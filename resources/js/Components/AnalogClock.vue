<template>
    <div class="relative w-32 h-32 rounded-full bg-white border-4 border-blue-300 shadow flex items-center justify-center">
        <div v-for="n in 12" :key="n" :style="{
            position: 'absolute',
            width: '4px',
            height: n % 3 === 0 ? '18px' : '10px',
            background: n % 3 === 0 ? '#1a237e' : '#b0bec5',
            top: '6px',
            left: '50%',
            transform: `translateX(-50%) rotate(${n*30}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
        }"></div>
        <div :style="{
            position: 'absolute',
            width: '3px',
            height: '38px',
            background: '#1a237e',
            top: '26px',
            left: '50%',
            transform: `translateX(-50%) rotate(${getAngle(date.getHours()%12,12)+date.getMinutes()/2}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
        }"></div>
        <div :style="{
            position: 'absolute',
            width: '2px',
            height: '48px',
            background: '#3949ab',
            top: '16px',
            left: '50%',
            transform: `translateX(-50%) rotate(${getAngle(date.getMinutes(),60)}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
        }"></div>
        <div :style="{
            position: 'absolute',
            width: '1px',
            height: '54px',
            background: '#e53935',
            top: '10px',
            left: '50%',
            transform: `translateX(-50%) rotate(${getAngle(date.getSeconds(),60)}deg)`,
            transformOrigin: 'bottom center',
            borderRadius: '2px',
        }"></div>
        <div class="absolute w-4 h-4 bg-blue-700 rounded-full"></div>
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