import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:6001',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'node',
    include: ['src/test/**/*.test.{js,jsx}'],
  },
});
