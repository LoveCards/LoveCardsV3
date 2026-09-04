<template>
  <div class="admin-table-view-controls">
    <span v-if="resultCount !== null" class="text-body-2 text-medium-emphasis" aria-live="polite">
      共 {{ resultCount }} 条结果
    </span>
    <v-spacer />

    <v-menu v-model="viewsMenuOpen" :close-on-content-click="false" location="bottom end">
      <template #activator="{ props: activatorProps }">
        <v-btn v-bind="activatorProps" min-height="44" prepend-icon="mdi-bookmark-multiple-outline" variant="text">
          保存视图
          <v-chip v-if="savedViews.length" class="ml-2" size="x-small" variant="tonal">{{ savedViews.length }}</v-chip>
        </v-btn>
      </template>
      <v-card min-width="320" max-width="420">
        <v-card-title class="text-subtitle-1">已保存视图</v-card-title>
        <v-divider />
        <v-list v-if="savedViews.length" density="compact" lines="two">
          <v-list-item
            v-for="view in savedViews"
            :key="view.id"
            prepend-icon="mdi-bookmark-outline"
            :title="view.name"
            subtitle="恢复筛选、排序和列设置"
            @click="applyView(view)"
          >
            <template #append>
              <v-btn
                :aria-label="`删除视图 ${view.name}`"
                color="error"
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                @click.stop="removeView(view.id)"
              />
            </template>
          </v-list-item>
        </v-list>
        <v-empty-state
          v-else
          class="py-5"
          icon="mdi-bookmark-off-outline"
          title="还没有保存视图"
          text="保存当前筛选与列设置，之后可一键恢复。"
        />
        <v-divider />
        <v-card-actions>
          <v-btn prepend-icon="mdi-content-save-outline" variant="text" @click="openSaveDialog">
            保存当前视图
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-menu>

    <v-menu v-model="columnsMenuOpen" :close-on-content-click="false" location="bottom end">
      <template #activator="{ props: activatorProps }">
        <v-btn v-bind="activatorProps" min-height="44" prepend-icon="mdi-view-column-outline" variant="text">
          列设置
        </v-btn>
      </template>
      <v-card min-width="280" max-width="360">
        <v-card-title class="text-subtitle-1">显示列</v-card-title>
        <v-divider />
        <v-list density="compact">
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
      </v-card>
    </v-menu>

    <v-btn min-height="44" prepend-icon="mdi-filter-remove-outline" variant="text" @click="resetView">
      重置视图
    </v-btn>
  </div>

  <v-dialog v-model="saveDialogOpen" aria-label="保存当前视图" max-width="480">
    <v-card>
      <v-card-title>保存当前视图</v-card-title>
      <v-divider />
      <v-card-text class="pt-5">
        <v-text-field
          v-model="viewName"
          autofocus
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
import type { LocationQueryRaw } from 'vue-router'
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
  resultCount?: number | null
}>(), {
  columns: () => [],
  defaultColumns: () => [],
  resultCount: null,
})

const emit = defineEmits<{ reset: [] }>()
const visibleColumnKeys = defineModel<string[]>('visibleColumns', { default: () => [] })
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const viewsMenuOpen = ref(false)
const columnsMenuOpen = ref(false)
const saveDialogOpen = ref(false)
const viewName = ref('')
const viewNameError = ref('')
const savedViews = ref<SavedAdminView[]>([])
const localStorageKey = computed(() => `lovecards.admin.table-view.${userStore.userInfo?.id ?? 'anonymous'}.${props.storageKey}`)
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
  if (!import.meta.client) return
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
  visibleColumnKeys.value = normalizedVisibleColumns(visibleColumnKeys.value)
  if (!import.meta.client) return
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

function currentQuery(): Record<string, StoredQueryValue> {
  const query: Record<string, StoredQueryValue> = {}
  for (const [key, value] of Object.entries(route.query)) {
    if (value === undefined || key === 'page') continue
    query[key] = Array.isArray(value) ? [...value] : value
  }
  return query
}

function openSaveDialog() {
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

async function applyView(view: SavedAdminView) {
  visibleColumnKeys.value = normalizedVisibleColumns(view.columns)
  viewsMenuOpen.value = false
  persist()
  const query: LocationQueryRaw = { ...view.query, page: '1' }
  await router.replace({ query, hash: route.hash })
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
  emit('reset')
  persist()
  await router.replace({ query: {}, hash: route.hash })
}

onMounted(hydrate)
watch(() => props.columns, () => {
  visibleColumnKeys.value = normalizedVisibleColumns(visibleColumnKeys.value)
}, { deep: true })
</script>

<style scoped>
.admin-table-view-controls {
  display: flex;
  min-height: 52px;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  padding: 4px 12px;
}

@media (max-width: 599.98px) {
  .admin-table-view-controls :deep(.v-spacer) {
    display: none;
  }

  .admin-table-view-controls > span {
    width: 100%;
    padding-inline: 4px;
  }
}
</style>
