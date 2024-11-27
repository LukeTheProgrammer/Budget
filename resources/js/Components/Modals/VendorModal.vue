<script setup>
import { ref, useTemplateRef } from 'vue'
import BsModal from './BsModal.vue'
import VendorForm from '@/Components/Forms/VendorForm.vue'
import LoadingSpinner from '@/Components/LoadingSpinner.vue'

const props = defineProps({
  vendorId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['closed', 'saving', 'saved'])

const modalRef = useTemplateRef('vendorBsModal')
const formRef = useTemplateRef('vendorForm')

const showModal = () => modalRef.value.showModal()
const hideModal = () => modalRef.value.hideModal()

const submitForm = () => formRef.value.submitForm()

const emitClosed = () => emit('closed')

const saving = ref(false)

const onSaving = () => {
  saving.value = true
  emit('saving')
}

const onSaved = () => {
  saving.value = false
  emit('saved')
  setTimeout(() => hideModal(), 500)
}

defineExpose({
  showModal,
  hideModal,
})
</script>

<template>
  <div>
    <BsModal ref="vendorBsModal" title="Vendor" @closed="emitClosed">
      <VendorForm ref="vendorForm" :vendor-id="props.vendorId" @saving="onSaving" @saved="onSaved" />
      <template #footer>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" @click.prevent="submitForm">
          <LoadingSpinner v-if="saving" :small="true" />
          <span v-else>Submit</span>
        </button>
      </template>
    </BsModal>
  </div>
</template>

<style>
.modal.show {
  display: block;
  background: rgba(0, 0, 0, 0.5);
}
</style>
