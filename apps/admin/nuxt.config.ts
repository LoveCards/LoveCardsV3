// https://nuxt.com/docs/api/configuration/nuxt-config
import vuetify, { transformAssetUrls } from "vite-plugin-vuetify";

export default defineNuxtConfig({
    nitro: {
        devProxy: {
            "/api": {
                target: "http://192.168.3.142:7001/api", // 后端接口地址
                changeOrigin: true, // 是否改变请求源
                prependPath: true, // 是否自动添加路径
            },
        },
    },
    ssr: false,
    compatibilityDate: "2024-11-01",
    devtools: { enabled: true },
    build: {
        transpile: ["vuetify"],
    },
    modules: [
        (_options, nuxt) => {
            nuxt.hooks.hook("vite:extendConfig", (config) => {
                // @ts-expect-error
                config.plugins.push(vuetify({ autoImport: true }));
            });
        },
        '@pinia/nuxt',
        //...
    ],
    vite: {
        vue: {
            template: {
                transformAssetUrls,
            },
        },
    },
});
