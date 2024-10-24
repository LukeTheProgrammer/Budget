<script setup>
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  Colors,
} from 'chart.js'
import autocolors from 'chartjs-plugin-autocolors'
import ChartDataLabels from 'chartjs-plugin-datalabels'

ChartJS.register(
  ArcElement,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  Colors,
  autocolors,
  ChartDataLabels,
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
    <Doughnut id="my-chart-id" :options="chartOptions" :data="chartData" />
  </div>
</template>
