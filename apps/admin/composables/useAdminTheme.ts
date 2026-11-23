import { useTheme } from 'vuetify'
import { computed, onBeforeUnmount, watch } from 'vue'

export type AdminThemePreference = 'light' | 'dark' | 'system'

export const adminThemeOptions = [
  { value: 'light', label: '浅色', icon: 'mdi-white-balance-sunny' },
  { value: 'dark', label: '深色', icon: 'mdi-weather-night' },
  { value: 'system', label: '跟随系统', icon: 'mdi-monitor' },
] as const

const STORAGE_KEY = 'lovecards.admin.theme'
const preferenceState = 'lovecards-admin-theme-preference'
const systemDarkState = 'lovecards-admin-system-dark'

function isPreference(value: string | null): value is AdminThemePreference {
  return value === 'light' || value === 'dark' || value === 'system'
}

function savedPreference(): AdminThemePreference {
  if (!import.meta.client) return 'light'
  try {
    const saved = window.localStorage.getItem(STORAGE_KEY)
    return isPreference(saved) ? saved : 'light'
  } catch {
    return 'light'
  }
}

export function useAdminTheme() {
  const preference = useState<AdminThemePreference>(preferenceState, savedPreference)
  const systemDark = useState<boolean>(systemDarkState, () =>
    import.meta.client && window.matchMedia('(prefers-color-scheme: dark)').matches)
  const resolvedTheme = computed(() =>
    preference.value === 'dark' || (preference.value === 'system' && systemDark.value)
      ? 'indigoDarkTheme'
      : 'indigoTheme')
  const preferenceLabel = computed(() => ({
    light: '浅色',
    dark: '深色',
    system: '跟随系统',
  }[preference.value]))

  function setPreference(next: AdminThemePreference): void {
    preference.value = next
    if (import.meta.client) {
      try { window.localStorage.setItem(STORAGE_KEY, next) } catch { /* 浏览器禁用存储时仍可在当前页面切换 */ }
    }
  }

  // 仅由 app.vue 调用，避免布局和登录页重复监听并争用 Vuetify 全局主题。
  function initialize(): void {
    if (!import.meta.client) return
    const vuetifyTheme = useTheme()
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    systemDark.value = mediaQuery.matches
    preference.value = savedPreference()

    const applyTheme = () => {
      vuetifyTheme.change(resolvedTheme.value)
      document.documentElement.style.colorScheme = resolvedTheme.value === 'indigoDarkTheme' ? 'dark' : 'light'
    }
    const onSystemChange = (event: MediaQueryListEvent) => { systemDark.value = event.matches }
    const onStorageChange = (event: StorageEvent) => {
      if (event.key === STORAGE_KEY || event.key === null) preference.value = savedPreference()
    }
    mediaQuery.addEventListener('change', onSystemChange)
    window.addEventListener('storage', onStorageChange)
    const stopWatching = watch(resolvedTheme, applyTheme, { immediate: true })
    onBeforeUnmount(() => {
      stopWatching()
      mediaQuery.removeEventListener('change', onSystemChange)
      window.removeEventListener('storage', onStorageChange)
    })
  }

  return { preference, preferenceLabel, resolvedTheme, setPreference, initialize }
}
