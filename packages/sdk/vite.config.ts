import { defineConfig } from 'vite'
import dts from 'vite-plugin-dts'

export default defineConfig({
  build: {
    // Linked workspaces are transformed by consumers; stable names avoid auto-import collisions.
    minify: false,
    lib: {
      entry: 'src/index.ts',
      name: 'LC',
      formats: ['es', 'cjs', 'umd'],
      fileName: (format) => {
        if (format === 'cjs') return 'lovecards.cjs'
        return `lovecards.${format}.js`
      },
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
  plugins: [
    dts(),
    {
      name: 'remove-toStringTag',
      generateBundle(options, bundle) {
        for (const fileName of Object.keys(bundle)) {
          const chunk = bundle[fileName];
          if (chunk.type === 'chunk' && fileName.endsWith('.cjs.js')) {
            chunk.code = chunk.code.replace(
              'Object.defineProperty(exports,Symbol.toStringTag,{value:"Module"});',
              ''
            );
          }
        }
      },
    },
  ],
})
