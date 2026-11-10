<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, type RouteLocationRaw } from 'vue-router'
import { useCurrentCompany } from '@/composables/useCurrentCompany'

defineProps<{ collapsed: boolean; mobileOpen: boolean }>()
defineEmits<{ close: [] }>()

const { companyId } = useCurrentCompany()
const route = useRoute()

function isSection(item: NavItem, exact: boolean): boolean {
  return exact || (item.also ?? []).includes(String(route.name))
}

interface NavItem {
  label: string
  /** SVG path data, 24x24 stroke icon. */
  icon: string
  /** Items without `to` are planned modules not yet available. */
  to?: RouteLocationRaw
  /** Other route names that belong to this section (detail pages, sub-pages). */
  also?: string[]
}

const ICONS = {
  home: 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-16v4h6V4h-6Z',
  agenda: 'M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm3 8h3m-3 4h6',
  customers:
    'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM21 19v-1a4 4 0 0 0-3-3.87M15 4.13a3 3 0 0 1 0 5.74',
  professionals: 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 10a7 7 0 0 1 14 0',
  services: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
  settings:
    'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.5 7.5 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.5 7.5 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.5 7.5 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.5 7.5 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z',
}

/** Links are scoped to the company in the URL; modules not built yet stay listed as "em breve". */
const groups = computed<NavItem[][]>(() => {
  const params = { companyId: companyId.value ?? '' }

  return [
    [
      {
        label: 'Painel',
        icon: ICONS.home,
        to: { name: 'company-home', params },
      },
      { label: 'Agenda', icon: ICONS.agenda, to: { name: 'agenda', params } },
      {
        label: 'Clientes',
        icon: ICONS.customers,
        to: { name: 'customers', params },
        also: ['customer-profile'],
      },
      {
        label: 'Profissionais',
        icon: ICONS.professionals,
        to: { name: 'professionals', params },
        also: ['professional-detail'],
      },
      {
        label: 'Serviços',
        icon: ICONS.services,
        to: { name: 'services', params },
      },
    ],
    [
      {
        label: 'Configurações',
        icon: ICONS.settings,
        to: { name: 'company-settings', params },
        also: ['business-hours', 'team', 'profile'],
      },
    ],
  ]
})

const itemBase =
  'relative flex h-10 items-center gap-3 rounded-input px-3 text-body font-medium transition-colors duration-150'
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 flex w-(--sidebar-width) flex-col border-r border-border bg-surface transition-[width,translate] duration-200 ease-standard lg:translate-x-0"
    :class="[
      mobileOpen ? 'translate-x-0' : '-translate-x-full',
      collapsed ? 'lg:w-(--sidebar-width-collapsed)' : 'lg:w-(--sidebar-width)',
    ]"
    aria-label="Navegação principal"
  >
    <div class="flex h-(--header-height) shrink-0 items-center gap-3 border-b border-border px-5">
      <span
        class="flex size-8 shrink-0 items-center justify-center rounded-input bg-primary font-mono text-label font-semibold text-primary-text"
        aria-hidden="true"
      >
        AP
      </span>
      <span class="truncate text-card-title font-semibold" :class="{ 'lg:sr-only': collapsed }"> AgendaPro </span>
      <button
        type="button"
        class="ml-auto inline-flex size-8 items-center justify-center rounded-input text-text-muted hover:bg-elevated hover:text-text-primary lg:hidden"
        aria-label="Fechar navegação"
        @click="$emit('close')"
      >
        <svg
          class="size-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M6 6l12 12M18 6 6 18" />
        </svg>
      </button>
    </div>

    <nav class="flex flex-1 flex-col gap-5 overflow-x-hidden overflow-y-auto px-3 py-5">
      <ul
        v-for="(group, index) in groups"
        :key="index"
        class="flex flex-col gap-1"
        :class="{ 'mt-auto border-t border-border pt-5': index === 1 }"
      >
        <li v-for="item in group" :key="item.label">
          <RouterLink v-if="item.to" v-slot="{ href, navigate, isExactActive }" :to="item.to" custom>
            <a
              :href="href"
              :aria-current="isSection(item, isExactActive) ? 'page' : undefined"
              :title="collapsed ? item.label : undefined"
              :class="[
                itemBase,
                isSection(item, isExactActive)
                  ? 'bg-primary-soft text-text-primary'
                  : 'text-text-secondary hover:bg-elevated hover:text-text-primary',
              ]"
              @click="navigate"
            >
              <span
                v-if="isSection(item, isExactActive)"
                class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-primary-hover"
                aria-hidden="true"
              />
              <svg
                class="size-[18px] shrink-0"
                :class="{
                  'text-primary-hover': isSection(item, isExactActive),
                }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path :d="item.icon" />
              </svg>
              <span class="truncate" :class="{ 'lg:sr-only': collapsed }">{{ item.label }}</span>
            </a>
          </RouterLink>
          <span
            v-else
            :class="[itemBase, 'cursor-not-allowed text-text-muted']"
            aria-disabled="true"
            :title="collapsed ? `${item.label} (em breve)` : undefined"
          >
            <svg
              class="size-[18px] shrink-0"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path :d="item.icon" />
            </svg>
            <span class="truncate" :class="{ 'lg:sr-only': collapsed }">{{ item.label }}</span>
            <span class="ml-auto text-support font-normal" :class="{ 'lg:sr-only': collapsed }"> em breve </span>
          </span>
        </li>
      </ul>
    </nav>
  </aside>
</template>
