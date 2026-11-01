// nuxt.config.ts
const appEnvironment = process.env.NUXT_PUBLIC_APP_ENV || 'development'
const configuredApiBase = process.env.NUXT_PUBLIC_API_BASE?.trim()
const publicApiBase = configuredApiBase || '/api'

if (appEnvironment === 'production') {
    const isLoopback = /^(https?:\/\/)?(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?(?:\/|$)/i.test(publicApiBase)
    const isRelative = publicApiBase.startsWith('/')
    const isHttps = publicApiBase.startsWith('https://')
    if (isLoopback || (!isRelative && !isHttps)) {
        throw new Error('Production admin builds require NUXT_PUBLIC_API_BASE to be a relative /api path or an approved HTTPS URL.')
    }
}

export default defineNuxtConfig({
    // 隔离 E2E 的生成目录，避免与用户正在运行的实时预览争用开发锁。
    buildDir: process.env.LOVECARDS_E2E_ISOLATED === '1'
        ? process.env.LOVECARDS_E2E_NUXT_BUILD_DIR
        : undefined,
    app: {
        baseURL: '/admin/',
        head: {
            title: 'LoveCards Admin',
        }
    },
    css: [
        '@mdi/font/css/materialdesignicons.css',
        '~/assets/admin-accessibility.css',
    ],
    // Shared admin primitives are intentionally addressable by their
    // capability-oriented names (AdminPageHeader, AdminAsyncState, ...).
    // Without this explicit directory entry Nuxt prefixes nested paths and
    // legacy pages silently render the guards as unknown custom elements.
    components: [
        { path: '~/components/shared/admin', pathPrefix: false },
    ],
    runtimeConfig: {
        public: {
            gitSha: 'localdev',
            environment: appEnvironment,
            // A relative API path works for the backend-served admin bundle.
            // Local Nuxt development is routed to the backend by nitro.devProxy.
            apiBase: publicApiBase,
            matomoEnabled: false,
        }
    },
    nitro: {
        devProxy: {
            "/api": {
                target: process.env.NUXT_API_PROXY_TARGET || "http://localhost:6001/api",
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
        // The SDK is already built and intentionally owns its transport.
        // Excluding both packages prevents Vite's dev optimizer from walking
        // workspace ancestors on Windows, where restricted user directories
        // can otherwise abort an otherwise valid admin preview.
        optimizeDeps: {
            exclude: ['@lovecards/sdk', 'axios', 'lodash', 'lodash-es'],
        },
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
            // 同类组件只在这里选择原生外观；页面仅覆盖业务上确有区别的状态。
            defaults: {
                VCard: { variant: 'flat', border: true },
                VDialog: { VCard: { variant: 'elevated', border: false } },
                VMenu: { VCard: { variant: 'elevated', border: false } },
                VTextField: { variant: 'outlined', density: 'comfortable' },
                VTextarea: { variant: 'outlined', density: 'comfortable' },
                VSelect: { variant: 'outlined', density: 'comfortable' },
                VAutocomplete: { variant: 'outlined', density: 'comfortable' },
                VCombobox: { variant: 'outlined', density: 'comfortable' },
                VFileInput: { variant: 'outlined', density: 'comfortable' },
                VNumberInput: { variant: 'outlined', density: 'comfortable' },
                VDataTable: { density: 'comfortable', itemsPerPageText: '每页行数' },
                VDataTableServer: { density: 'comfortable', itemsPerPageText: '每页行数' },
                VChip: { size: 'small', variant: 'tonal' },
                VAlert: { variant: 'tonal' },
            },
            theme: {
                defaultTheme: 'indigoTheme',
                themes: {
                    indigoTheme: {
                        dark: false,
                        colors: {
                            background: "#F7F8FB",
                            surface: "#FFFFFF",
                            "surface-variant": "#F5F5F5",
                            "on-background": "#212121",
                            "on-surface": "#212121",
                            "on-surface-variant": "#5F6368",
                            outline: "#E0E0E0",
                            primary: "#3F51B5",
                            "primary-darken-1": "#303F9F",
                            "primary-container": "#E8EAF6",
                            "on-primary": "#FFFFFF",
                            "on-primary-container": "#303F9F",
                            secondary: "#FFC107",
                            // Keep the legacy accent token aligned with the approved Indigo admin visual language.
                            // Existing pages still use accent; mapping it here avoids a pink secondary brand.
                            accent: "#3F51B5",
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
