import { defineConfig } from 'vite'

/**
 * 单文件浏览器 SDK，供零构建主题使用。
 * 与 npm 产物不同，这个构建把 axios 一并打入，主题不需要额外依赖。
 */
export default defineConfig({
  build: {
    emptyOutDir: false,
    minify: true,
    lib: {
      entry: 'src/index.ts',
      name: 'LC',
      formats: ['iife'],
      fileName: () => 'lovecards.browser.js',
    },
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
      },
    },
  },
})
