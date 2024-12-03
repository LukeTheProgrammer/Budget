<script setup>
import { ref, useTemplateRef } from 'vue'
import BsModal from './BsModal.vue'
import VendorAliasForm from '@/Components/Forms/VendorAliasForm.vue'
import LoadingSpinner from '@/Components/LoadingSpinner.vue'

const props = defineProps({
  vendorAliasId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['closed', 'saving', 'saved', 'deleting', 'deleted'])

const modalRef = useTemplateRef('vendorBsModal')
const formRef = useTemplateRef('vendorAliasForm')

const saving = ref(false)
const deleting = ref(false)

const showModal = () => modalRef.value.showModal()
const hideModal = () => modalRef.value.hideModal()

const deleteVendorAlias = () => formRef.value.deleteVendorAlias()
const submitForm = () => formRef.value.submitForm()

const emitClosed = () => emit('closed')

const onDeleting = () => {
  deleting.value = true
  emit('deleting')
}

const onDeleted = () => {
  deleting.value = false
  emit('deleted')
  hideModal()
}

const onSaving = () => {
  saving.value = true
  emit('saving')
}

const onSaved = () => {
  saving.value = false
  emit('saved')
  hideModal()
}

defineExpose({
  showModal,
  hideModal,
})
</script>

<template>
  <div>
    <BsModal ref="vendorBsModal" title="Vendor" @closed="emitClosed">
      <VendorAliasForm
        ref="vendorAliasForm"
        :vendor-alias-id="props.vendorAliasId"
        @deleting="onDeleting"
        @deleted="onDeleted"
        @saving="onSaving"
        @saved="onSaved"
      />
      <template #footer>
        <button type="button" class="btn btn-danger" @click.prevent="deleteVendorAlias">
          <LoadingSpinner v-if="deleting" :small="true" />
          <span v-else>Delete</span>
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" @click.prevent="submitForm">
          <LoadingSpinner v-if="saving" :small="true" />
          <span v-else>Save</span>
        </button>
      </template>
    </BsModal>
  </div>
</template>
