<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AgGridTable from '@/Components/Tables/AgGridTable.vue'
import AgGridCellButton from '@/Components/Tables/Partials/AgGridCellButton.vue'
import VendorAliasFormModal from '@/Components/Modals/VendorAliasFormModal.vue'

const props = defineProps({
  vendorAliases: {
    type: Array,
    default: () => [],
  },
})

const modalRef = useTemplateRef('vendorAliasModal')
const vendorAliasModalId = ref(null)

const vendorBtnClick = (params) => {
  vendorAliasModalId.value = params.data.id
  nextTick(() => modalRef.value.showModal())
}

const reloadPage = () => location.reload()

const onVendorModalClosed = () => {
  vendorAliasModalId.value = null
}

const colDefs = ref([
  { field: 'name', headerName: 'Vendor Alias Name', cellClass: ['align-middle'] },
  { field: 'vendor', headerName: 'Vendor', cellClass: ['align-middle'] },
  {
    field: 'id',
    headerName: '',
    cellClass: ['align-middle', 'text-end'],
    cellRendererSelector: () => {
      return {
        component: AgGridCellButton,
        params: {
          label: 'Vendor Alias',
          handleClick: function (params) {
            vendorBtnClick(params)
          },
        },
      }
    },
  },
])

const rowData = computed(() => {
  return props.vendorAliases.map((a) => {
    return {
      id: a.id,
      name: a.name,
      vendor: a?.vendor?.name,
    }
  })
})
</script>

<template>
  <Head title="Vendor Aliases" />

  <AppLayout>
    <template #header>
      <h1>Vendor Aliases</h1>
    </template>

    <div style="height: 50vh">
      <!-- <div v-for="(a, ai) in props.vendorAliases" :key="ai">{{ a }}</div> -->
      <AgGridTable :col-defs="colDefs" :row-data="rowData" simple-filter-label="Search Vendor Alaises" />
      <VendorAliasFormModal
        v-if="vendorAliasModalId"
        :vendor-alias-id="vendorAliasModalId"
        ref="vendorAliasModal"
        @closed="onVendorModalClosed"
        @saved="reloadPage"
        @deleted="reloadPage"
      />
    </div>
  </AppLayout>
</template>
