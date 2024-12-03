<script setup>
import { computed, useTemplateRef } from 'vue'
import BsModal from './BsModal.vue'

const props = defineProps({
  vendorAliasId: {
    type: [Number, String, null],
    default: null,
  },
  vendorAliasName: {
    type: String,
    default: '',
  },
  vendorName: {
    type: String,
    default: 'Vendor',
  },
})

const emit = defineEmits(['cancel', 'closed', 'ok'])

const modalRef = useTemplateRef('vendorAliasAddModal')

const showModal = () => modalRef.value.showModal()
const hideModal = () => modalRef.value.hideModal()
const emitClosed = () => emit('closed')
const okClick = () => emit('ok')
const cancelClick = () => emit('cancel')

const isCreate = computed(() => {
  return props.vendorAliasId === null
})

const modalTitle = computed(() => {
  return isCreate.value ? 'Create New Vendor Alias' : 'Associate Vendor Alias'
})

defineExpose({
  showModal,
  hideModal,
})
</script>

<template>
  <div>
    <BsModal ref="vendorAliasAddModal" :title="modalTitle" @closed="emitClosed">
      <template v-if="isCreate">
        <p>
          Do you want to create a new vendor alias {{ props.vendorAliasName }} and associate it to
          {{ props.vendorName }}?
        </p>
      </template>
      <template v-else>
        <p>Do you want to associate the vendor alias {{ props.vendorAliasName }} to {{ props.vendorName }}?</p>
        <p>All other vendor associations will be removed.</p>
      </template>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click.prevent="cancelClick">Cancel</button>
        <button type="button" class="btn btn-primary" @click.prevent="okClick">Ok</button>
      </template>
    </BsModal>
  </div>
</template>
