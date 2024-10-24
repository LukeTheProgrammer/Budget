<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CategoryBarChart from '@/Components/ReportCharts/CategoryBarChart.vue'
import CategoryDoughnutChart from '@/Components/ReportCharts/CategoryDoughnutChart.vue'

const props = defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <Head title="Reports" />

  <AppLayout>
    <template #header>
      <h1>Reports</h1>
    </template>

    <div class="row">
      <div class="col-6">
        <div class="card p-4">
          <h2>Category Doughnut Chart</h2>
          <CategoryDoughnutChart />
        </div>
      </div>

      <div class="col-6">
        <div class="card p-4">
          <h2>Category Bar Chart</h2>
          <CategoryBarChart />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
