<template>
  <div class="admin-table-view-controls">
    <v-menu v-model="viewsMenuOpen" :close-on-content-click="false" location="bottom end">
      <template #activator="{ props: activatorProps }">
        <v-btn v-bind="activatorProps" prepend-icon="mdi-tune-variant" variant="text">
          视图设置
          <v-chip v-if="savedViews.length" class="ml-2" size="x-small" variant="tonal">{{ savedViews.length }}</v-chip>
        </v-btn>
      </template>
      <v-card min-width="320" max-width="400">
        <v-card-title class="text-subtitle-1">视图设置</v-card-title>
        <v-divider />
        <v-list v-if="showSavedViews" density="compact">
          <v-list-subheader>已保存视图</v-list-subheader>
          <v-list-item
            v-for="view in savedViews"
            :key="view.id"
            prepend-icon="mdi-bookmark-outline"
            :title="view.name"
            @click="applyView(view)"
          >
            <template #append>
              <v-btn
                :aria-label="`删除视图 ${view.name}`"
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                @click.stop="removeView(view.id)"
              />
            </template>
          </v-list-item>
          <v-list-item
            v-if="!savedViews.length"
            prepend-icon="mdi-bookmark-off-outline"
            subtitle="保存当前筛选、排序和列设置后可快速恢复"
            title="还没有保存视图"
          />
          <v-list-item prepend-icon="mdi-content-save-outline" title="保存当前视图" @click="openSaveDialog($event)" />
        </v-list>
        <v-divider v-if="showSavedViews" />
        <v-list density="compact">
          <v-list-subheader>显示列</v-list-subheader>
          <v-list-item v-for="column in columns" :key="column.key" @click="toggleColumn(column)">
            <template #prepend>
              <v-checkbox-btn
                :aria-label="`${isVisible(column.key) ? '隐藏' : '显示'}${column.title}列`"
                :disabled="column.required || (isVisible(column.key) && visibleColumnKeys.length === 1)"
                :model-value="isVisible(column.key)"
                @click.stop
                @update:model-value="toggleColumn(column)"
              />
            </template>
            <v-list-item-title>{{ column.title }}</v-list-item-title>
            <template v-if="column.required" #append>
              <v-chip size="x-small" variant="outlined">固定</v-chip>
            </template>
          </v-list-item>
        </v-list>
        <v-divider />
        <v-card-actions>
          <v-btn prepend-icon="mdi-restore" variant="text" @click="resetView">恢复默认视图</v-btn>
        </v-card-actions>
      </v-card>
    </v-menu>
  </div>

  <v-dialog @after-leave="restoreSaveDialogFocus" v-model="saveDialogOpen" aria-label="保存当前视图" max-width="480">
    <v-card>
      <v-card-title>保存当前视图</v-card-title>
      <v-divider />
      <v-card-text class="pt-5">
        <v-text-field
          v-model="viewName"
          autofocus
          density="comfortable"
          :hide-details="false"
          counter="30"
          label="视图名称"
          maxlength="30"
          variant="outlined"
          :error-messages="viewNameError"
          @keydown.enter.prevent="saveView"
        />
        <p class="mb-0 text-body-2 text-medium-emphasis">
          当前 URL 中的筛选、排序、每页行数和列设置会保存在这个浏览器中。
        </p>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="saveDialogOpen = false">取消</v-btn>
        <v-btn color="primary" variant="flat" @click="saveView">保存</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useUserStore } from '~/stores/userStore'

export interface AdminTableColumnOption {
  title: string
  key: string
  required?: boolean
}

type StoredQueryValue = string | null | Array<string | null>
interface SavedAdminView {
  id: string
  name: string
  query: Record<string, StoredQueryValue>
  columns: string[]
}

const props = withDefaults(defineProps<{
  storageKey: string
  columns?: AdminTableColumnOption[]
  defaultColumns?: string[]
  showSavedViews?: boolean
  viewQueryKeys?: string[]
}>(), {
  columns: () => [],
  defaultColumns: () => [],
  showSavedViews: true,
  viewQueryKeys: () => [],
})

const emit = defineEmits<{ reset: []; apply: [query: Record<string, StoredQueryValue>] }>()
const visibleColumnKeys = defineModel<string[]>('visibleColumns', { default: () => [] })
const route = useRoute()
const userStore = useUserStore()
const viewsMenuOpen = ref(false)
const saveDialogOpen = ref(false)
const viewName = ref('')
const viewNameError = ref('')
const savedViews = ref<SavedAdminView[]>([])
const localStorageKey = computed(() => userStore.userInfo?.id ? `lovecards.admin.table-view.${userStore.userInfo.id}.${props.storageKey}` : null)
const availableColumnKeys = computed(() => props.columns.map((column) => column.key))
const defaultColumns = computed(() => {
  const allowed = new Set(availableColumnKeys.value)
  const preferred = props.defaultColumns.filter((key) => allowed.has(key))
  const resolved = preferred.length ? preferred : [...availableColumnKeys.value]
  for (const column of props.columns) {
    if (column.required && !resolved.includes(column.key)) resolved.push(column.key)
  }
  return Array.from(new Set(resolved))
})

function normalizedVisibleColumns(keys: readonly string[]): string[] {
  const allowed = new Set(availableColumnKeys.value)
  const normalized = keys.filter((key) => allowed.has(key))
  for (const column of props.columns) {
    if (column.required && !normalized.includes(column.key)) normalized.push(column.key)
  }
  return normalized.length ? Array.from(new Set(normalized)) : [...defaultColumns.value]
}

function persist() {
  if (!import.meta.client || !localStorageKey.value) return
  try {
    window.localStorage.setItem(localStorageKey.value, JSON.stringify({
      columns: normalizedVisibleColumns(visibleColumnKeys.value),
      views: savedViews.value,
    }))
  } catch {
    // 浏览器禁用本地存储时，视图仍在当前页面会话中可用。
  }
}

function hydrate() {
  visibleColumnKeys.value = [...defaultColumns.value]
  savedViews.value = []
  if (!import.meta.client || !localStorageKey.value) return
  try {
    const raw = window.localStorage.getItem(localStorageKey.value)
    if (!raw) return
    const parsed = JSON.parse(raw) as { columns?: unknown; views?: unknown }
    if (Array.isArray(parsed.columns)) {
      visibleColumnKeys.value = normalizedVisibleColumns(parsed.columns.filter((item): item is string => typeof item === 'string'))
    }
    if (Array.isArray(parsed.views)) {
      savedViews.value = parsed.views.filter((item): item is SavedAdminView => Boolean(
        item && typeof item === 'object'
        && 'id' in item && typeof item.id === 'string'
        && 'name' in item && typeof item.name === 'string'
        && 'query' in item && item.query && typeof item.query === 'object'
        && 'columns' in item && Array.isArray(item.columns),
      )).slice(0, 12)
    }
  } catch {
    savedViews.value = []
  }
}

function normalizeQuery(source: Record<string, unknown>): Record<string, StoredQueryValue> {
  const query: Record<string, StoredQueryValue> = {}
  const allowed = new Set(props.viewQueryKeys)
  for (const [key, value] of Object.entries(source)) {
    if (!allowed.has(key) || key === 'page' || key === 'inspect') continue
    if (typeof value === 'string' || value === null) query[key] = value
    else if (Array.isArray(value) && value.every((item) => typeof item === 'string' || item === null)) {
      query[key] = [...value]
    }
  }
  return query
}
function currentQuery(): Record<string, StoredQueryValue> {
  return normalizeQuery(route.query as Record<string, unknown>)
}

function openSaveDialog(event: Event) {
  const menuId = (event.currentTarget as HTMLElement).closest<HTMLElement>('.v-menu')?.id
  saveDialogTrigger = menuId
    ? [...document.querySelectorAll<HTMLElement>('[aria-controls]')]
      .find(element => element.getAttribute('aria-controls') === menuId) ?? null
    : null
  viewsMenuOpen.value = false
  viewName.value = ''
  viewNameError.value = ''
  saveDialogOpen.value = true
}

function saveView() {
  const name = viewName.value.trim()
  if (!name) {
    viewNameError.value = '请输入视图名称。'
    return
  }
  const duplicate = savedViews.value.find((view) => view.name.toLocaleLowerCase() === name.toLocaleLowerCase())
  const next: SavedAdminView = {
    id: duplicate?.id ?? `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    name,
    query: currentQuery(),
    columns: normalizedVisibleColumns(visibleColumnKeys.value),
  }
  savedViews.value = [next, ...savedViews.value.filter((view) => view.id !== next.id)].slice(0, 12)
  saveDialogOpen.value = false
  persist()
}

function applyView(view: SavedAdminView) {
  visibleColumnKeys.value = normalizedVisibleColumns(view.columns)
  viewsMenuOpen.value = false
  persist()
  emit('apply', normalizeQuery(view.query))
}

function removeView(id: string) {
  savedViews.value = savedViews.value.filter((view) => view.id !== id)
  persist()
}

function isVisible(key: string): boolean {
  return visibleColumnKeys.value.includes(key)
}

function toggleColumn(column: AdminTableColumnOption) {
  if (column.required) return
  const next = new Set(visibleColumnKeys.value)
  if (next.has(column.key)) {
    if (next.size === 1) return
    next.delete(column.key)
  } else {
    next.add(column.key)
  }
  visibleColumnKeys.value = normalizedVisibleColumns([...next])
  persist()
}

async function resetView() {
  visibleColumnKeys.value = [...defaultColumns.value]
  viewsMenuOpen.value = false
  emit('reset')
  persist()
}

onMounted(hydrate)
watch(localStorageKey, hydrate)
watch(() => props.columns, () => {
  visibleColumnKeys.value = normalizedVisibleColumns(visibleColumnKeys.value)
}, { deep: true })
const restoreDialogFocus = useAdminDialogFocusReturn(saveDialogOpen)
let saveDialogTrigger: HTMLElement | null = null
function restoreSaveDialogFocus() {
  restoreDialogFocus()
  if (saveDialogTrigger?.isConnected) saveDialogTrigger.focus()
  saveDialogTrigger = null
}
</script>

<style scoped>
.admin-table-view-controls {
  display: flex;
  min-height: 40px;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
