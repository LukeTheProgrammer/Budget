<script setup>
import 'ag-grid-community/styles/ag-grid.css'
import 'ag-grid-community/styles/ag-theme-quartz.css'
import { computed, ref, shallowRef } from 'vue'
import { AgGridVue } from 'ag-grid-vue3'

const props = defineProps({
  simpleFilter: {
    type: Boolean,
    default: true,
  },
  simpleFilterLabel: {
    type: String,
    default: 'Table Filter',
  },
  rowSelection: {
    type: Boolean,
    default: true,
  },
  colDefs: {
    type: Array,
    default: () => [],
  },
  rowData: {
    type: Array,
    default: () => [],
  },
  rowHeight: {
    type: Number,
    default: 50,
  },
})

// const rowData = ref([
//   { make: 'Tesla', model: 'Model Y', price: 64950, electric: true },
//   { make: 'Ford', model: 'F-Series', price: 33850, electric: false },
//   { make: 'Toyota', model: 'Corolla', price: 29600, electric: false },
// ])

// const colDefs = ref([{ field: 'make' }, { field: 'model' }, { field: 'price' }, { field: 'electric' }])

const autoSizeStrategy = ref({
  type: 'fitGridWidth',
  defaultMinWidth: 100,
  columnLimits: [],
})

const agRowSelection = computed(() => {
  const config = {
    mode: 'multiRow',
  }
  return props.rowSelection ? config : {}
})

const gridApi = shallowRef()

const onAgGridReady = (params) => {
  gridApi.value = params.api
}

const onSimpleFilterInput = (e) => {
  gridApi.value.setGridOption('quickFilterText', e.target.value)
}
</script>

<template>
  <div v-if="props.simpleFilter" class="form-floating mb-3">
    <input
      type="text"
      class="form-control"
      id="ag-simple-filter"
      :placeholder="props.simpleFilterLabel"
      @input="onSimpleFilterInput"
    />
    <label for="ag-simple-filter">{{ props.simpleFilterLabel }}</label>
  </div>
  <AgGridVue
    style="width: 100%; height: 100%"
    class="ag-theme-quartz-auto-dark"
    :autoSizeStrategy="autoSizeStrategy"
    :columnDefs="colDefs"
    :rowData="rowData"
    :rowSelection="agRowSelection"
    :rowHeight="rowHeight"
    @grid-ready="onAgGridReady"
  />
</template>
