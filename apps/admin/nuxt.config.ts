// nuxt.config.ts
export default defineNuxtConfig({
    app: {
        baseURL: '/admin/',
        head: {
            title: 'LoveCards Admin',
        }
    },
    css: ['@mdi/font/css/materialdesignicons.css'],
    runtimeConfig: {
        public: {
            gitSha: 'localdev',
            // The standalone admin preview has no Nuxt dev proxy. Keep the
            // local backend reachable by default; deployments can override
            // this with NUXT_PUBLIC_API_BASE at build time.
            apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:6001/api',
            matomoEnabled: false,
        }
    },
    nitro: {
        devProxy: {
            "/api": {
                target: process.env.NUXT_API_PROXY_TARGET || "http://127.0.0.1:6001/api",
                changeOrigin: true,
                prependPath: true,
            },
        },
    },
    ssr: false,
    experimental: {
        appManifest: false,
    },
    compatibilityDate: "2024-11-01",
    // The devtools manifest is not served correctly under the /admin baseURL
    // in the local Nuxt server and turns the shell into a 404 error page.
    devtools: { enabled: false },
    build: {
        transpile: ['@lovecards/sdk'],
    },
    vite: {
        server: {
            watch: {
                ignored: ['**/SDK/dist/**'],
            },
        },
    },
    modules: [
        'vuetify-nuxt-module',
        'vuetify-notifier/nuxt',
        '@pinia/nuxt',
        '@nuxt/scripts',
    ],
    scripts: process.env.NUXT_PUBLIC_MATOMO_ENABLED === 'true' ? {
        registry: {
            matomoAnalytics: {
                matomoUrl: 'https://matomo.fatda.cn',
                siteId: '9',
                trackPageView: true,
                enableLinkTracking: true,
            }
        }
    } : undefined,
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
