<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import SettingsTabs from '@/modules/settings/components/SettingsTabs.vue'
import WeeklyHoursEditor from '@/modules/settings/components/WeeklyHoursEditor.vue'
import { settingsApi } from '@/modules/settings/api'
import { firstError } from '@/services/forms'
import { isApiError, type ApiError } from '@/services/http'
import type { WeeklyPeriod } from '@/types/api'

const toast = useToast()
const { companyId, company, can } = useCurrentCompany()
const canEdit = computed(() => can('manage_availability'))

const periods = ref<WeeklyPeriod[]>([])
const saved = ref('[]')
const loading = ref(true)
const loadError = ref<string | null>(null)
const saving = ref(false)
const error = ref<ApiError | null>(null)

const isDirty = computed(() => JSON.stringify(periods.value) !== saved.value)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    periods.value = (await settingsApi.businessHours(companyId.value!)).periods
    saved.value = JSON.stringify(periods.value)
  } catch (caught) {
    loadError.value = isApiError(caught) ? caught.message : 'Não foi possível carregar o expediente.'
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  error.value = null
  try {
    periods.value = (await settingsApi.saveBusinessHours(companyId.value!, periods.value)).periods
    saved.value = JSON.stringify(periods.value)
    toast.success('Expediente atualizado. Agendamentos já marcados não foram alterados.')
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    error.value = caught
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-6">
    <header>
      <p class="text-label text-text-muted">Configurações</p>
      <h1 class="mt-1 text-page-title">Expediente</h1>
    </header>
    <SettingsTabs />

    <AppCard
      title="Horário de funcionamento"
      :description="`Horários no fuso ${company?.timezone ?? ''}. Profissionais só atendem dentro deste expediente.`"
      class="max-w-3xl"
    >
      <AppErrorState v-if="loadError" :message="loadError" @retry="load" />
      <AppSkeleton v-else-if="loading" :lines="7" />
      <template v-else>
        <p
          v-if="!canEdit"
          class="mb-3 rounded-input border border-border bg-surface px-3 py-2.5 text-label text-text-secondary"
        >
          Somente proprietários e administradores podem alterar o expediente.
        </p>
        <WeeklyHoursEditor v-model="periods" :disabled="!canEdit" :error="firstError(error)" />
        <div v-if="canEdit" class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
          <AppButton variant="ghost" :disabled="!isDirty || saving" @click="periods = JSON.parse(saved)"
            >Descartar</AppButton
          >
          <AppButton :loading="saving" :disabled="!isDirty" @click="save">Salvar expediente</AppButton>
        </div>
      </template>
    </AppCard>
  </div>
</template>
