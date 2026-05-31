import { defineConfig } from 'vite'
import dts from 'vite-plugin-dts'

export default defineConfig({
  build: {
    lib: {
      entry: 'src/index.ts',
      name: 'LC',
      formats: ['es', 'cjs', 'umd'],
      fileName: (format) => `lovecards.${format}.js`,
    },
    rollupOptions: {
      external: ['axios'],
      output: {
        globals: {
          axios: 'axios',
        },
      },
    },
  },
  plugins: [dts()],
})
