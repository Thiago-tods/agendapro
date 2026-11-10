import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig } from 'vitest/config'
import viteConfig from './vite.config.ts'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      include: ['src/**/__tests__/*.spec.ts'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      restoreMocks: true,
    },
  }),
)
