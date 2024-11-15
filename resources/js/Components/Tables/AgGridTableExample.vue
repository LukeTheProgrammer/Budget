<script setup>
import { createApp, onBeforeMount, ref, shallowRef } from 'vue'
import { AgGridVue } from '@ag-grid-community/vue3'
import '@ag-grid-community/styles/ag-grid.css'
import '@ag-grid-community/styles/ag-theme-quartz.css'
import './styles.css'
import { ClientSideRowModelModule } from '@ag-grid-community/client-side-row-model'
import { ModuleRegistry } from '@ag-grid-community/core'

// ModuleRegistry.registerModules([ClientSideRowModelModule])

const columnDefs = ref([
  { field: 'athlete' },
  { field: 'country' },
  { field: 'sport' },
  { field: 'age', minWidth: 100 },
  { field: 'gold', minWidth: 100 },
  { field: 'silver', minWidth: 100 },
  { field: 'bronze', minWidth: 100 },
])

const gridApi = shallowRef()

const defaultColDef = ref({
  flex: 1,
})

const rowData = ref(null)

onBeforeMount(() => {})

const onFilterTextBoxChanged = () => {
  gridApi.value.setGridOption('quickFilterText', document.getElementById('filter-text-box').value)
}

const onGridReady = (params) => {
  gridApi.value = params.api

  const updateData = (data) => (rowData.value = data)

  fetch('https://www.ag-grid.com/example-assets/olympic-winners.json')
    .then((resp) => resp.json())
    .then((data) => updateData(data))
}
</script>

<template>
  <div style="height: 100%">
    <div class="example-wrapper">
      <div class="example-header">
        <span>Quick Filter:</span>
        <input type="text" id="filter-text-box" placeholder="Filter..." v-on:input="onFilterTextBoxChanged()" />
      </div>
      <ag-grid-vue
        style="width: 100%; height: 100%"
        :class="themeClass"
        :columnDefs="columnDefs"
        @grid-ready="onGridReady"
        :defaultColDef="defaultColDef"
        :rowData="rowData"
      />
    </div>
  </div>
</template>
