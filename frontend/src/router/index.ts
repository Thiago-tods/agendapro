import { createRouter, createWebHistory } from 'vue-router'
import { authGuard } from './guards'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(authGuard)

router.afterEach((to) => {
  const title = [...to.matched].reverse().find((record) => record.meta.title)?.meta.title
  document.title = title ? `${title} · AgendaPro` : 'AgendaPro'
})

export default router
