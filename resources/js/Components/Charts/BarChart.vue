<script setup>
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, Colors } from 'chart.js'
import autocolors from 'chartjs-plugin-autocolors'
import ChartDataLabels from 'chartjs-plugin-datalabels';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    Colors,
    autocolors,
    ChartDataLabels
)

const props = defineProps({
    chartData: {
        type: Object,
        required: true,
    },
    chartOptions: {
        type: Object,
        default: () => {},
    },
})

// ChartJS.defaults.backgroundColor = 'var(--bs-secondary-color)'
// ChartJS.defaults.borderColor = 'var(--bs-emphasis-color)'

</script>

<template>
    <div>
        <Bar
            id="my-chart-id"
            :options="chartOptions"
            :data="chartData"
        />
    </div>
</template>
