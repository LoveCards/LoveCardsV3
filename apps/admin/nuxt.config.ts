// nuxt.config.ts
export default defineNuxtConfig({
    app: {
        baseURL: '/admin'
    },
    nitro: {
        devProxy: {
            "/api": {
                target: "http://192.168.3.142:7001/api",
                changeOrigin: true,
                prependPath: true,
            },
        },
    },
    ssr: false,
    compatibilityDate: "2024-11-01",
    devtools: { enabled: true },
    modules: [
        // 直接使用模块名称
        'vuetify-nuxt-module',
        'vuetify-notifier/nuxt',
        '@pinia/nuxt',
        "vuetify-notifier",
        "nuxt-lodash"
    ],
    notifier: {
        /* module specific options */
        position: 'bottom-right', // 可选配置项
        timeout: 3000
    },
    vuetify: { // 模块的专属配置项
        moduleOptions: {
            /* 模块选项 */
        },
        vuetifyOptions: { // Vuetify 初始化配置
            theme: {
                defaultTheme: 'indigoTheme',
                themes: {
                    indigoTheme: {
                        dark: false,
                        colors: {
                            primary: "#3F51B5",
                            secondary: "#FFC107",
                            accent: "#FF4081",
                            error: "#F44336",
                            info: "#2196F3",
                            success: "#43A047",
                            warning: "#FF9800",
                        }
                    }
                }
            }
        }
    }
});