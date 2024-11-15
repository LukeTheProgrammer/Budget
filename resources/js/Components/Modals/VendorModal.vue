<script setup>
import { useTemplateRef } from 'vue'
import BsModal from './BsModal.vue'

const props = defineProps({
  vendorId: {
    type: [Number, String, null],
    default: null,
  },
})

const modalRef = useTemplateRef('vendorBsModal')

const showModal = () => modalRef.value.showModal()
const hideModal = () => modalRef.value.hideModal()

defineExpose({
  showModal,
  hideModal,
})
</script>

<template>
  <div>
    <BsModal ref="vendorBsModal" title="Vendor">
      {{ props.vendorId }}
    </BsModal>
  </div>
</template>

<style>
.modal.show {
  display: block;
  background: rgba(0, 0, 0, 0.5);
}
</style>
