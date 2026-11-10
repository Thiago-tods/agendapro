<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import { useToast } from '@/composables/useToast'
import { customersApi, type CustomerPayload } from '@/modules/customers/api'
import { formatPhone, nullable } from '@/services/forms'
import { isApiError, type ApiError } from '@/services/http'
import type { Customer } from '@/types/api'

/** Creates a customer, or edits `customer` when given. Validation is the API's (422 shown per field). */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ companyId: string; customer?: Customer | null }>()
const emit = defineEmits<{ saved: [customer: Customer] }>()

const toast = useToast()
const form = reactive({
  name: '',
  email: '',
  phone: '',
  birth_date: '',
  notes: '',
})
const saving = ref(false)
const error = ref<ApiError | null>(null)

watch(open, (value) => {
  if (!value) return
  const c = props.customer
  form.name = c?.name ?? ''
  form.email = c?.email ?? ''
  form.phone = formatPhone(c?.phone)
  form.birth_date = c?.birth_date ?? ''
  form.notes = c?.notes ?? ''
  error.value = null
})

async function submit(): Promise<void> {
  if (saving.value) return
  saving.value = true
  error.value = null

  const payload: CustomerPayload = {
    name: form.name.trim(),
    email: nullable(form.email),
    phone: nullable(form.phone),
    birth_date: nullable(form.birth_date),
    notes: nullable(form.notes),
  }

  try {
    const saved = props.customer
      ? await customersApi.update(props.companyId, props.customer.id, payload)
      : await customersApi.create(props.companyId, payload)
    toast.success(props.customer ? 'Cliente atualizado.' : 'Cliente cadastrado.')
    emit('saved', saved)
    open.value = false
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal v-model:open="open" :title="customer ? 'Editar cliente' : 'Novo cliente'">
    <form id="customer-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
      <p v-if="error && !error.isValidation" class="text-label text-danger sm:col-span-2" role="alert">
        {{ error.message }}
      </p>
      <div class="sm:col-span-2">
        <AppInput v-model="form.name" label="Nome" required autocomplete="off" :error="error?.field('name')" />
      </div>
      <AppInput
        v-model="form.phone"
        label="Telefone"
        type="tel"
        placeholder="(11) 98765-4321"
        :error="error?.field('phone')"
      />
      <AppInput v-model="form.email" label="E-mail" type="email" autocomplete="off" :error="error?.field('email')" />
      <AppInput v-model="form.birth_date" label="Data de nascimento" type="date" :error="error?.field('birth_date')" />
      <div class="sm:col-span-2">
        <AppTextarea v-model="form.notes" label="Observações" :maxlength="2000" :error="error?.field('notes')" />
      </div>
    </form>
    <template #footer>
      <AppButton variant="ghost" :disabled="saving" @click="open = false">Cancelar</AppButton>
      <AppButton type="submit" form="customer-form" :loading="saving">
        {{ customer ? 'Salvar alterações' : 'Cadastrar cliente' }}
      </AppButton>
    </template>
  </AppModal>
</template>
