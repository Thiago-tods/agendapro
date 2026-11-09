<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'

const COLLAPSE_KEY = 'agendapro:sidebar-collapsed'

function readCollapsed(): boolean {
  try {
    return localStorage.getItem(COLLAPSE_KEY) === '1'
  } catch {
    return false
  }
}

const collapsed = ref(readCollapsed())
const mobileOpen = ref(false)
const route = useRoute()

watch(collapsed, (value) => {
  try {
    localStorage.setItem(COLLAPSE_KEY, value ? '1' : '0')
  } catch {
    /* storage unavailable: preference lasts for this session only */
  }
})

watch(
  () => route.fullPath,
  () => (mobileOpen.value = false),
)

const desktopQuery = window.matchMedia('(min-width: 1024px)')

function toggleNavigation(): void {
  if (desktopQuery.matches) collapsed.value = !collapsed.value
  else mobileOpen.value = !mobileOpen.value
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && mobileOpen.value) mobileOpen.value = false
}

function onBreakpointChange(): void {
  if (desktopQuery.matches) mobileOpen.value = false
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  desktopQuery.addEventListener('change', onBreakpointChange)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  desktopQuery.removeEventListener('change', onBreakpointChange)
})
</script>

<template>
  <div class="min-h-dvh bg-background">
    <a
      href="#conteudo"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-input focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-text"
    >
      Pular para o conteúdo
    </a>

    <Transition
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-150"
    >
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-30 bg-black/60 lg:hidden"
        aria-hidden="true"
        @click="mobileOpen = false"
      />
    </Transition>

    <AppSidebar
      id="navegacao-principal"
      :collapsed="collapsed"
      :mobile-open="mobileOpen"
      @close="mobileOpen = false"
    />

    <div
      class="flex min-h-dvh flex-col transition-[padding] duration-200 ease-standard"
      :class="collapsed ? 'lg:pl-(--sidebar-width-collapsed)' : 'lg:pl-(--sidebar-width)'"
    >
      <AppHeader
        :navigation-expanded="mobileOpen || !collapsed"
        @toggle-navigation="toggleNavigation"
      >
        <template #actions>
          <slot name="header-actions" />
        </template>
      </AppHeader>

      <main
        id="conteudo"
        tabindex="-1"
        class="flex-1 px-4 py-6 focus:outline-none sm:px-6 lg:px-8 lg:py-8"
      >
        <div class="mx-auto w-full max-w-[1440px]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
