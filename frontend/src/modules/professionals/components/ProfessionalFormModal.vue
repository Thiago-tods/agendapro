<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppColorPicker from '@/components/ui/AppColorPicker.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import { useToast } from '@/composables/useToast'
import { professionalsApi } from '@/modules/professionals/api'
import { COLOR_PALETTE, formatPhone, nullable } from '@/services/forms'
import { isApiError, type ApiError } from '@/services/http'
import type { Professional } from '@/types/api'

/** Profile data only; services and hours are edited on the professional's page. */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  companyId: string
  professional?: Professional | null
}>()
const emit = defineEmits<{ saved: [professional: Professional] }>()

const toast = useToast()
const form = reactive({
  name: '',
  title: '',
  email: '',
  phone: '',
  bio: '',
  color: COLOR_PALETTE[0]!.value,
})
const saving = ref(false)
const error = ref<ApiError | null>(null)

watch(open, (value) => {
  if (!value) return
  const p = props.professional
  form.name = p?.name ?? ''
  form.title = p?.title ?? ''
  form.email = p?.email ?? ''
  form.phone = formatPhone(p?.phone)
  form.bio = p?.bio ?? ''
  form.color = p?.color ?? COLOR_PALETTE[0]!.value
  error.value = null
})

async function submit(): Promise<void> {
  if (saving.value) return
  saving.value = true
  error.value = null
  const payload = {
    name: form.name.trim(),
    title: nullable(form.title),
    email: nullable(form.email),
    phone: nullable(form.phone),
    bio: nullable(form.bio),
    color: form.color,
  }

  try {
    const saved = props.professional
      ? await professionalsApi.update(props.companyId, props.professional.id, payload)
      : await professionalsApi.create(props.companyId, {
          ...payload,
          is_active: true,
          service_ids: [],
        })
    toast.success(props.professional ? 'Profissional atualizado.' : 'Profissional cadastrado.')
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
  <AppModal v-model:open="open" :title="professional ? 'Editar profissional' : 'Novo profissional'">
    <form id="professional-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
      <p v-if="error && !error.isValidation" class="text-label text-danger sm:col-span-2" role="alert">
        {{ error.message }}
      </p>
      <AppInput v-model="form.name" label="Nome" required autocomplete="off" :error="error?.field('name')" />
      <AppInput
        v-model="form.title"
        label="Cargo ou especialidade"
        placeholder="Ex.: Cabeleireira"
        :error="error?.field('title')"
      />
      <AppInput v-model="form.phone" label="Telefone" type="tel" :error="error?.field('phone')" />
      <AppInput v-model="form.email" label="E-mail" type="email" autocomplete="off" :error="error?.field('email')" />
      <div class="sm:col-span-2">
        <AppColorPicker v-model="form.color" label="Cor na agenda" :error="error?.field('color')" />
      </div>
      <div class="sm:col-span-2">
        <AppTextarea v-model="form.bio" label="Descrição" :maxlength="2000" :error="error?.field('bio')" />
      </div>
    </form>
    <template #footer>
      <AppButton variant="ghost" :disabled="saving" @click="open = false">Cancelar</AppButton>
      <AppButton type="submit" form="professional-form" :loading="saving">
        {{ professional ? 'Salvar alterações' : 'Cadastrar profissional' }}
      </AppButton>
    </template>
  </AppModal>
</template>
