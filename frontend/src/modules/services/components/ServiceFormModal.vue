<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppColorPicker from '@/components/ui/AppColorPicker.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import { useToast } from '@/composables/useToast'
import { professionalsApi } from '@/modules/professionals/api'
import { servicesApi, type ServicePayload } from '@/modules/services/api'
import { centsToInput, COLOR_PALETTE, nullable, parseMoney } from '@/services/forms'
import { ApiError, isApiError } from '@/services/http'
import type { Professional, Service } from '@/types/api'

/** Creates or edits a service, including which professionals perform it. */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ companyId: string; serviceId?: string | null }>()
const emit = defineEmits<{ saved: [service: Service] }>()

const toast = useToast()
const form = reactive({
  name: '',
  category: '',
  description: '',
  price: '',
  duration: '60',
  buffer: '0',
  color: COLOR_PALETTE[0]!.value,
  professionalIds: [] as string[],
})
const team = ref<Professional[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref<ApiError | null>(null)

watch(open, async (value) => {
  if (!value) return
  error.value = null
  loading.value = true
  try {
    const [professionals, service] = await Promise.all([
      professionalsApi.list(props.companyId, { status: 'all', per_page: 100 }),
      props.serviceId ? servicesApi.get(props.companyId, props.serviceId) : Promise.resolve(null),
    ])
    team.value = professionals.data
    form.name = service?.name ?? ''
    form.category = service?.category ?? ''
    form.description = service?.description ?? ''
    form.price = service ? centsToInput(service.price_cents) : ''
    form.duration = String(service?.duration_minutes ?? 60)
    form.buffer = String(service?.buffer_minutes ?? 0)
    form.color = service?.color ?? COLOR_PALETTE[0]!.value
    form.professionalIds = (service?.professionals ?? []).map((p) => p.id)
  } catch (caught) {
    error.value = isApiError(caught) ? caught : new ApiError(0, 'Não foi possível carregar o formulário.')
  } finally {
    loading.value = false
  }
})

async function submit(): Promise<void> {
  if (saving.value) return
  const price = parseMoney(form.price)
  if (price === null) {
    error.value = new ApiError(422, 'Verifique os dados informados.', {
      price_cents: ['Informe o preço, por exemplo 80,00.'],
    })
    return
  }

  saving.value = true
  error.value = null
  const payload: Omit<ServicePayload, 'is_active'> = {
    name: form.name.trim(),
    category: nullable(form.category),
    description: nullable(form.description),
    price_cents: price,
    duration_minutes: Number(form.duration),
    buffer_minutes: Number(form.buffer || 0),
    color: form.color,
    professional_ids: form.professionalIds,
  }

  try {
    const saved = props.serviceId
      ? await servicesApi.update(props.companyId, props.serviceId, payload)
      : await servicesApi.create(props.companyId, {
          ...payload,
          is_active: true,
        })
    toast.success(props.serviceId ? 'Serviço atualizado.' : 'Serviço cadastrado.')
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
  <AppModal v-model:open="open" :title="serviceId ? 'Editar serviço' : 'Novo serviço'">
    <div v-if="loading" aria-busy="true"><AppSkeleton :lines="6" /></div>
    <form v-else id="service-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
      <p v-if="error && !error.isValidation" class="text-label text-danger sm:col-span-2" role="alert">
        {{ error.message }}
      </p>
      <AppInput v-model="form.name" label="Nome" required autocomplete="off" :error="error?.field('name')" />
      <AppInput v-model="form.category" label="Categoria" placeholder="Ex.: Cabelo" :error="error?.field('category')" />
      <AppInput
        v-model="form.price"
        label="Preço (R$)"
        placeholder="80,00"
        required
        :error="error?.field('price_cents')"
      />
      <AppInput
        v-model="form.duration"
        label="Duração (min)"
        type="number"
        :step="5"
        min="5"
        required
        :error="error?.field('duration_minutes')"
      />
      <AppInput
        v-model="form.buffer"
        label="Intervalo após (min)"
        type="number"
        :step="5"
        min="0"
        hint="Tempo de preparo bloqueado na agenda."
        :error="error?.field('buffer_minutes')"
      />
      <div class="sm:col-span-2">
        <AppColorPicker v-model="form.color" label="Cor" :error="error?.field('color')" />
      </div>
      <div class="sm:col-span-2">
        <AppTextarea
          v-model="form.description"
          label="Descrição"
          :maxlength="2000"
          :error="error?.field('description')"
        />
      </div>
      <fieldset class="sm:col-span-2">
        <legend class="mb-1.5 text-label font-medium text-text-secondary">Profissionais que realizam</legend>
        <p v-if="team.length === 0" class="text-label text-text-muted">Nenhum profissional cadastrado.</p>
        <div class="grid gap-1 sm:grid-cols-2">
          <label
            v-for="p in team"
            :key="p.id"
            class="flex items-center gap-2 rounded-input px-2 py-1.5 hover:bg-elevated/50"
            :class="{ 'opacity-60': !p.is_active }"
          >
            <input
              v-model="form.professionalIds"
              type="checkbox"
              :value="p.id"
              class="size-4 accent-(--color-primary)"
            />
            <span class="size-2 rounded-full" :style="{ backgroundColor: p.color }" aria-hidden="true" />
            <span class="truncate text-body">{{ p.name }}</span>
            <span v-if="!p.is_active" class="text-support text-text-muted">inativo</span>
          </label>
        </div>
        <p v-if="error?.field('professional_ids')" class="mt-1 text-support text-danger" role="alert">
          {{ error.field('professional_ids') }}
        </p>
      </fieldset>
    </form>
    <template #footer>
      <AppButton variant="ghost" :disabled="saving" @click="open = false">Cancelar</AppButton>
      <AppButton type="submit" form="service-form" :loading="saving" :disabled="loading">
        {{ serviceId ? 'Salvar alterações' : 'Cadastrar serviço' }}
      </AppButton>
    </template>
  </AppModal>
</template>
