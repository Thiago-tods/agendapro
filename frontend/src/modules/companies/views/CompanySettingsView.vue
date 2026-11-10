<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import { companiesApi } from '@/modules/companies/api'
import { BRAZIL_TIMEZONES } from '@/modules/companies/timezones'
import SettingsTabs from '@/modules/settings/components/SettingsTabs.vue'
import { isApiError, type ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const toast = useToast()
const { company, can } = useCurrentCompany()

const form = reactive({ name: '', timezone: '' })
const saving = ref(false)
const error = ref<ApiError | null>(null)

// UI hint from the backend; the PATCH is authorized server-side regardless.
const canEdit = computed(() => can('manage_company'))

function reset(): void {
  form.name = company.value?.name ?? ''
  form.timezone = company.value?.timezone ?? ''
  error.value = null
}

watch(() => company.value?.id, reset, { immediate: true })

const isDirty = computed(() => form.name !== company.value?.name || form.timezone !== company.value?.timezone)

const timezoneOptions = computed(() => {
  const options = [...BRAZIL_TIMEZONES]
  if (form.timezone && !options.some((o) => o.value === form.timezone)) {
    options.unshift({ value: form.timezone, label: form.timezone })
  }
  return options
})

async function save(): Promise<void> {
  if (!company.value || saving.value) return
  saving.value = true
  error.value = null

  try {
    const updated = await companiesApi.update(company.value.id, {
      name: form.name.trim(),
      timezone: form.timezone,
    })
    auth.replaceCompany(updated)
    reset()
    toast.success('Dados da empresa atualizados.')
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
    if (!caught.isValidation) toast.error(caught.message)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <header>
      <p class="text-label text-text-muted">Configurações</p>
      <h1 class="mt-1 text-page-title">Dados da empresa</h1>
    </header>
    <SettingsTabs />

    <AppCard
      title="Informações gerais"
      description="Usadas na agenda, nos lembretes e na exibição de horários."
      class="max-w-3xl"
    >
      <form class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="save">
        <p
          v-if="!canEdit"
          class="rounded-input border border-border bg-surface px-3 py-2.5 text-label text-text-secondary sm:col-span-2"
        >
          Somente proprietários e administradores podem alterar estes dados.
        </p>

        <div class="sm:col-span-2">
          <AppInput
            v-model="form.name"
            label="Nome da empresa"
            required
            :disabled="!canEdit"
            :error="error?.field('name')"
          />
        </div>

        <AppSelect
          v-model="form.timezone"
          label="Fuso horário"
          :options="timezoneOptions"
          :disabled="!canEdit"
          :error="error?.field('timezone')"
          hint="Todos os horários da agenda são exibidos neste fuso."
        />

        <AppInput label="Moeda" :model-value="company?.currency ?? ''" disabled hint="Real brasileiro (BRL)." />

        <div v-if="canEdit" class="flex justify-end gap-2 border-t border-border pt-5 sm:col-span-2">
          <AppButton variant="ghost" :disabled="!isDirty || saving" @click="reset">Descartar</AppButton>
          <AppButton type="submit" :loading="saving" :disabled="!isDirty">Salvar alterações</AppButton>
        </div>
      </form>
    </AppCard>
  </div>
</template>
