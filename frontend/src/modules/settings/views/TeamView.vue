<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppConfirmDialog from '@/components/ui/AppConfirmDialog.vue'
import AppErrorState from '@/components/ui/AppErrorState.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useCurrentCompany } from '@/composables/useCurrentCompany'
import { useToast } from '@/composables/useToast'
import SettingsTabs from '@/modules/settings/components/SettingsTabs.vue'
import { settingsApi } from '@/modules/settings/api'
import { firstError } from '@/services/forms'
import { isApiError, type ApiError } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import type { CompanyMember, CompanyRole } from '@/types/api'

const auth = useAuthStore()
const toast = useToast()
const { companyId, company } = useCurrentCompany()

const members = ref<CompanyMember[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const changing = ref<string | null>(null)
const removing = ref<CompanyMember | null>(null)
const busy = ref(false)

const isOwner = computed(() => company.value?.membership?.role === 'owner')

const ROLE_HELP: Record<CompanyRole, string> = {
  owner: 'Acesso total, inclusive gerenciar proprietários.',
  admin: 'Gerencia empresa, catálogo, expediente, equipe e financeiro.',
  receptionist: 'Gerencia clientes e agendamentos.',
  professional: 'Consulta agenda, clientes e catálogo.',
}
const ROLE_LABEL: Record<CompanyRole, string> = {
  owner: 'Proprietário',
  admin: 'Administrador',
  receptionist: 'Recepcionista',
  professional: 'Profissional',
}

/** Owners can grant any role; others never see "owner" as an option (the API enforces it too). */
const roleOptions = computed(() =>
  (Object.keys(ROLE_LABEL) as CompanyRole[])
    .filter((role) => isOwner.value || role !== 'owner')
    .map((value) => ({ value, label: ROLE_LABEL[value] })),
)

function canTouch(member: CompanyMember): boolean {
  return member.user.id !== auth.user?.id && (isOwner.value || member.role !== 'owner')
}

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    members.value = await settingsApi.members(companyId.value!)
  } catch (caught) {
    loadError.value = isApiError(caught) ? caught.message : 'Não foi possível carregar a equipe.'
  } finally {
    loading.value = false
  }
}

async function changeRole(member: CompanyMember, role: string): Promise<void> {
  if (role === member.role) return
  changing.value = member.id
  try {
    const updated = await settingsApi.changeRole(companyId.value!, member.id, role as CompanyRole)
    members.value = members.value.map((m) => (m.id === updated.id ? updated : m))
    toast.success(`${member.user.name} agora é ${updated.role_label.toLowerCase()}.`)
  } catch (caught) {
    toast.error(isApiError(caught) ? (firstError(caught) ?? caught.message) : 'Não foi possível alterar o papel.')
    members.value = [...members.value] // re-render the select with the saved role
  } finally {
    changing.value = null
  }
}

async function remove(): Promise<void> {
  if (!removing.value) return
  busy.value = true
  try {
    await settingsApi.removeMember(companyId.value!, removing.value.id)
    toast.success(`${removing.value.user.name} foi removido da equipe.`)
    members.value = members.value.filter((m) => m.id !== removing.value?.id)
    removing.value = null
  } catch (caught) {
    toast.error(isApiError(caught) ? (firstError(caught) ?? caught.message) : 'Não foi possível remover.')
  } finally {
    busy.value = false
  }
}

// --- Add member ------------------------------------------------------------------------
const adding = ref(false)
const addForm = reactive({
  email: '',
  name: '',
  password: '',
  role: 'receptionist' as CompanyRole,
  isNew: true,
})
const addError = ref<ApiError | null>(null)
const addBusy = ref(false)

function openAdd(): void {
  Object.assign(addForm, {
    email: '',
    name: '',
    password: '',
    role: 'receptionist',
    isNew: true,
  })
  addError.value = null
  adding.value = true
}

async function submitAdd(): Promise<void> {
  addBusy.value = true
  addError.value = null
  try {
    const member = await settingsApi.addMember(companyId.value!, {
      email: addForm.email.trim(),
      role: addForm.role,
      ...(addForm.isNew ? { name: addForm.name.trim(), password: addForm.password } : {}),
    })
    members.value = [...members.value, member].sort((a, b) => a.user.name.localeCompare(b.user.name, 'pt-BR'))
    toast.success(`${member.user.name} foi adicionado à equipe.`)
    adding.value = false
  } catch (caught) {
    if (!isApiError(caught)) throw caught
    addError.value = caught
  } finally {
    addBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-label text-text-muted">Configurações</p>
        <h1 class="mt-1 text-page-title">Equipe e permissões</h1>
      </div>
      <AppButton @click="openAdd">Adicionar pessoa</AppButton>
    </header>
    <SettingsTabs />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
      <AppCard title="Membros" padding="none">
        <AppErrorState v-if="loadError" :message="loadError" @retry="load" />
        <div v-else-if="loading" class="p-5"><AppSkeleton :lines="5" /></div>
        <ul v-else class="divide-y divide-border">
          <li v-for="member in members" :key="member.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
            <div class="min-w-0 flex-1 basis-48">
              <p class="flex items-center gap-2 truncate font-medium">
                {{ member.user.name }}
                <AppBadge v-if="member.user.id === auth.user?.id" tone="primary">Você</AppBadge>
              </p>
              <p class="truncate text-label text-text-muted">
                {{ member.user.email }}
              </p>
            </div>
            <div v-if="canTouch(member)" class="flex items-center gap-2">
              <div class="w-44">
                <AppSelect
                  :model-value="member.role"
                  :label="`Papel de ${member.user.name}`"
                  hide-label
                  :options="roleOptions"
                  :disabled="changing === member.id"
                  @update:model-value="(role) => changeRole(member, role)"
                />
              </div>
              <AppButton
                variant="ghost"
                size="sm"
                :aria-label="`Remover ${member.user.name}`"
                @click="removing = member"
                >Remover</AppButton
              >
            </div>
            <AppBadge v-else>{{ member.role_label }}</AppBadge>
          </li>
        </ul>
      </AppCard>

      <AppCard title="O que cada papel pode fazer">
        <dl class="flex flex-col gap-3">
          <div v-for="(help, role) in ROLE_HELP" :key="role">
            <dt class="text-label font-medium">{{ ROLE_LABEL[role] }}</dt>
            <dd class="text-label text-text-muted">{{ help }}</dd>
          </div>
        </dl>
      </AppCard>
    </div>

    <AppModal
      v-model:open="adding"
      title="Adicionar pessoa"
      description="Quem já tem conta no AgendaPro entra só com o e-mail."
    >
      <form id="member-form" class="grid gap-4" novalidate @submit.prevent="submitAdd">
        <p v-if="addError && !addError.isValidation" class="text-label text-danger" role="alert">
          {{ addError.message }}
        </p>
        <AppInput
          v-model="addForm.email"
          label="E-mail"
          type="email"
          required
          autocomplete="off"
          :error="addError?.field('email')"
        />
        <AppSelect
          v-model="addForm.role"
          label="Papel"
          :options="roleOptions"
          :error="addError?.field('role')"
          :hint="ROLE_HELP[addForm.role]"
        />
        <label class="flex items-center gap-2 text-body">
          <input v-model="addForm.isNew" type="checkbox" class="size-4 accent-(--color-primary)" />
          Criar conta para esta pessoa
        </label>
        <template v-if="addForm.isNew">
          <AppInput v-model="addForm.name" label="Nome" required autocomplete="off" :error="addError?.field('name')" />
          <AppInput
            v-model="addForm.password"
            label="Senha inicial"
            type="password"
            required
            autocomplete="new-password"
            hint="Mínimo de 8 caracteres, com letras e números. Peça para a pessoa trocar no primeiro acesso."
            :error="addError?.field('password')"
          />
        </template>
      </form>
      <template #footer>
        <AppButton variant="ghost" :disabled="addBusy" @click="adding = false">Cancelar</AppButton>
        <AppButton type="submit" form="member-form" :loading="addBusy">Adicionar</AppButton>
      </template>
    </AppModal>

    <AppConfirmDialog
      :open="removing !== null"
      title="Remover da equipe"
      :message="`${removing?.user.name ?? ''} perderá o acesso a ${company?.name ?? 'esta empresa'}. A conta e o histórico de ações são mantidos.`"
      confirm-label="Remover"
      :loading="busy"
      @update:open="(v) => !v && (removing = null)"
      @confirm="remove"
    />
  </div>
</template>
