<template>
  <AdminPageHeader
    title="卡片"
    description="查看卡片内容，处理待审核或异常内容。"
    :breadcrumbs="[{ title: '内容' }]"
  >
    <template #actions>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="tableLoading" @click="getTableData">刷新</v-btn>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      v-model:action-capacity="actionCapacity"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :action-headers="TableHeaders"
      :action-labels="cardActionLabels"
      :selection-width="48"
      preference-key="content-cards"
      :result-count="resultCount"
      :result-state="tableLoading ? 'loading' : tableError ? 'stale' : 'ready'"
      :view-query-keys="['list_rows', 'search_value', 'order_key', 'order_desc', 'filters']"
      @apply-view="applySavedView"
      @reset-view="resetTableView"
    >
      <template #search>
        <div class="d-flex align-center ga-2"><v-text-field v-model="searchDraft" clearable :label="searchInputLabel" prepend-inner-icon="mdi-magnify" single-line @keydown.enter.prevent="submitSearch" @click:clear="clearSearch" /><v-btn variant="outlined" @click="submitSearch">查询</v-btn></div>
      </template>
      <template #actions>
        <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="openSearchDialog">搜索与排序</v-btn>
      </template>
      <template v-if="tableSelected.length" #selection>
        <v-row dense align="center" aria-live="polite">
          <v-col cols="12" sm="auto"><span class="text-body-2">已选择 {{ tableSelected.length }} 项</span></v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select v-model="BatchOperate" :items="TableBatchOptions" :disabled="!canMutateRows" item-title="title" item-value="value" label="批量操作" />
          </v-col>
          <v-col cols="auto">
            <AdminActionGuard :capability="batchCapability" scope="all">
              <v-btn variant="outlined" :disabled="!canMutateRows || BatchOperate === ''" @click="openBatchCardDialog">应用操作</v-btn>
            </AdminActionGuard>
          </v-col>
          <v-col cols="auto"><v-btn variant="text" @click="tableSelected = []">取消选择</v-btn></v-col>
        </v-row>
      </template>

      <v-alert
        v-if="tableError && tableItems.length"
        class="ma-3"
        type="error"
        variant="tonal"
        title="刷新卡片列表失败"
        :text="tableError"
      >
        <template #append>
          <v-btn variant="outlined" @click="getTableData">重试</v-btn>
        </template>
      </v-alert>

      <v-data-table-server
        v-model="tableSelected"
        v-model:items-per-page="tableListRows"
        v-model:page="tableCurrentPage"
        v-model:sort-by="tableSortBy"
        class="admin-resource-table"
        density="comfortable"
        hover
        item-value="id"
        items-per-page-text="每页行数"
        :headers="TableHeaders"
        :items="tableItems"
        :items-length="tableTotalItems"
        :items-per-page-options="TableListRowsOptions"
        :loading="tableLoading"

        show-select
      >
        <template #loading>
          <v-skeleton-loader type="table-row-divider@6" />
        </template>

        <template #[`item.tags`]="{ item }">
          <div class="d-flex flex-wrap ga-1 py-2">
            <v-chip v-for="value in renderTags(item.tags)" :key="value" size="x-small" variant="tonal">
              <AdminTableSearchHighlight :text="value" :query="highlightQuery('tags')" />
            </v-chip>
            <span v-if="renderTags(item.tags).length === 0" class="text-medium-emphasis">—</span>
          </div>
        </template>

        <template #[`item.id`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">#<AdminTableSearchHighlight :text="item.id" :query="highlightQuery('id')" /></span>
        </template>

        <template #[`item.user_id`]="{ item }">
          <AdminTableSearchHighlight :text="`${item.username?.trim() || '用户'} #${item.user_id}`" :query="highlightQuery('user_id')" />
        </template>

        <template #[`item.content`]="{ item }">
          <AdminContentSummary :content="item.content" :author="item.username" :user-id="item.user_id" :highlight-query="highlightQuery('content')" />
        </template>

        <template #[`item.cover`]="{ item }">
          <v-img
            v-if="item.cover"
            :width="96"
            aspect-ratio="16/9"
            class="rounded"
            cover
            :alt="`${item.username || '用户'}的卡片封面`"
            :src="item.cover"
          />
          <v-icon v-else color="medium-emphasis" icon="mdi-image-off-outline" aria-label="无封面" />
        </template>

        <template #[`item.operate`]="{ item }">
          <AdminTableRowActions :actions="cardRowActions(item)" :capacity="actionCapacity" more-label="更多卡片操作" />
        </template>

        <template #[`item.is_top`]="{ item }">
          <span class="text-body-2 text-medium-emphasis"><AdminTableSearchHighlight :text="Number(item.is_top) === 1 ? '置顶' : '—'" :query="highlightQuery('is_top')" /></span>
        </template>

        <template #[`item.created_at`]="{ item }">
          <span class="text-body-2 text-medium-emphasis"><AdminTableTime :value="item.created_at" :query="highlightQuery('created_at')" /></span>
        </template>

        <template #[`item.status`]="{ item }">
          <AdminContentStatus :status="item.status" :pinned="Number(item.is_top) === 1 && !visibleColumns.includes('is_top')" :highlight-query="highlightQuery('status')" />
        </template>

        <template v-for="field in ['goods', 'comments', 'views', 'post_ip']" :key="field" #[`item.${field}`]="{ item }">
          <AdminTableSearchHighlight :text="tableSearchCell(item, field)" :query="highlightQuery(field)" />
        </template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-card-text-outline'"
            :title="tableError ? '无法加载卡片' : '暂无卡片'"
            :text="tableError || '当前筛选条件下没有可显示的卡片。可以调整关键词或高级筛选条件。'"
          >
            <template #actions>
              <v-btn v-if="tableError" color="primary" variant="outlined" @click="getTableData">重试</v-btn>
              <v-btn v-else variant="text" prepend-icon="mdi-filter-remove-outline" @click="clearFilters">清除筛选</v-btn>
            </template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>

  <CardInspectDialog v-model="CardInspectDialog_state" :card-id="CardInspectDialog_id" :initial-mode="CardInspectDialog_mode" :source-path="route.fullPath" @saved="getTableData" />
  <AdminDangerActionDialog
    :model-value="Boolean(pendingCardCommand)"
    :title="pendingCardCommand?.action.label ?? '确认卡片操作'"
    :action-label="pendingCardCommand?.action.label ?? '确认执行'"
    :impact="pendingCardCommand?.action.impact ?? ''"
    :resource-label="pendingCardCommand ? `卡片 #${pendingCardCommand.id}` : ''"
    :loading="cardCommandBusy"
    :error="cardCommandError"
    @update:model-value="closeCardCommand"
    @confirm="runCardCommand"
  />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteCardDialog_state"
    v-model:deleteData="DeleteCardDialog_data"
    :deleteFun="DeleteCardFun"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchCardDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchCardDialog_operate"
    :batchFun="BatchCardFun"
    :impact-description="cardBatchImpact"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchCardsDialog_state"
    :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    :initial-filter="searchDialogFilter"
    KeysMessages="默认[内容]"
  />
</template>

<script setup lang="ts">
import AdminContentSummary from '~/components/content/AdminContentSummary.vue';
import AdminContentStatus from '~/components/content/AdminContentStatus.vue';
import type { Card, CardsBatchMethod } from '@lovecards/sdk';
import { cardGovernanceActions, type CardGovernanceAction } from '~/components/cards/cardGovernanceActions';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import CardInspectDialog from '~/components/cards/CardInspectDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import { useApi } from '~/lib/api';
import { useTagsStore } from '@/stores/tagsStore';
import { useUserStore } from '~/stores/userStore';
import SelectUtils from '~/utils/select';
import { tableSearchValue as mapTableSearchValue } from '~/utils/tableSearchValue';
import { tableSearchCell } from '~/composables/useAdminTableSearch';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const notifier = useAdminNotifier();
const route = useRoute();
const router = useRouter();

const positiveQueryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};
const filterInteger = (value: unknown): number | null => {
  if (typeof value !== 'number' && (typeof value !== 'string' || !/^\d+$/.test(value))) return null;
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) ? parsed : null;
};
const normalizeFilters = (value: unknown): Record<string, unknown> => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  const raw = value as Record<string, unknown>;
  const result: Record<string, unknown> = {};
  if (Array.isArray(raw.search_keys)) {
    const keys = [...new Set(raw.search_keys.filter((key): key is string =>
      typeof key === 'string' && SearchKeys.some((option) => option.value === key)))];
    if (keys.length) result.search_keys = keys;
  }
  const showDeleted = filterInteger(raw.show_deleted);
  if (showDeleted !== null && showDeleted >= 0 && showDeleted <= 2) result.show_deleted = showDeleted;
  const tagId = filterInteger(raw.tag_id);
  if (tagId !== null && tagId > 0) result.tag_id = tagId;
  if (typeof raw.order_key === 'string' && AllTableHeaders.some((header) =>
    header.value === raw.order_key && header.sortable)) {
    result.order_key = raw.order_key;
    if (typeof raw.order_desc === 'boolean') result.order_desc = raw.order_desc;
  }
  return result;
};
const queryFilters = (value: unknown): Record<string, unknown> => {
  if (typeof value !== 'string' || !value) return {};
  try { return normalizeFilters(JSON.parse(value)); }
  catch { return {}; }
};

const AllTableHeaders = [
  { title: '内容', value: 'content', sortable: false, minWidth: 320 },
  { title: 'ID', value: 'id', sortable: true },
  { title: '用户', value: 'user_id', sortable: true },
  { title: '封面', value: 'cover', sortable: false },
  { title: '自定义字段', value: 'data', sortable: false },
  { title: '点赞数', value: 'goods', sortable: true },
  { title: '评论数', value: 'comments', sortable: true },
  { title: '浏览量', value: 'views', sortable: true },
  { title: '标签', value: 'tags', sortable: false },
  { title: '发布时间', value: 'created_at', sortable: true, minWidth: 176 },
  { title: 'IP', value: 'post_ip', sortable: true },
  { title: '置顶', value: 'is_top', sortable: true },
  { title: '状态', value: 'status', sortable: true, minWidth: 112 },
  { title: '操作', value: 'operate', sortable: false, minWidth: 144 },
];
const defaultColumns = ['content', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['content', 'operate'].includes(header.value) }));
const SearchKeys = AllTableHeaders.slice(0, -1).map((header) => header.value === 'user_id' ? { ...header, title: '用户 ID' } : header);
const BaseTableBatchOptions = [
  { title: '切换置顶状态', value: 'top' },
  { title: '切换封禁状态', value: 'ban' },
  { title: '切换隐藏状态', value: 'hide' },
  { title: '通过审核', value: 'approve' },
  { title: '删除', value: 'delete' },
] satisfies Array<{ title: string; value: CardsBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const canDeleteCard = computed(() => userStore.hasGrant('cards.delete', 'all'));
const canViewCard = computed(() => userStore.hasGrant('cards.read', 'all'));
const batchCapabilityFor = (method: CardsBatchMethod | '') => {
  if (method === 'delete') return 'cards.delete';
  if (method === 'top' || method === 'unset_top') return 'cards.pin';
  if (method === 'approve' || method === 'ban') return 'cards.approve';
  return 'cards.update';
};
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

const tagsStore = useTagsStore();
const Tags = computed(() => Array.isArray(tagsStore.tags) ? tagsStore.tags : []);
const tagNamesById = computed(() => new Map(Tags.value.map((tag) => [Number(tag.id), tag.name])));
const normalizeTagIds = (tags: unknown): string[] => {
  if (Array.isArray(tags)) return tags.map(String);
  if (typeof tags !== 'string' || tags.trim() === '') return [];
  try {
    const parsed = JSON.parse(tags);
    return Array.isArray(parsed) ? parsed.map(String) : [];
  } catch {
    return [];
  }
};
const renderTags = (tags: unknown) => normalizeTagIds(tags).map((item) => {
  const name = tagNamesById.value.get(Number(item));
  return name ? `${name} #${item}` : `标签 #${item}`;
});
const canUpdateCard = computed(() => userStore.hasGrant('cards.update', 'all'));
const canApproveCard = computed(() => userStore.hasGrant('cards.approve', 'all'));
const canPinCard = computed(() => userStore.hasGrant('cards.pin', 'all'));
const cardActionLabels = computed(() => [...new Set(['查看卡片', ...(canUpdateCard.value ? ['编辑卡片'] : []), ...tableItems.value.flatMap(item => cardGovernanceActions(item, { approve: canApproveCard.value, update: canUpdateCard.value, pin: canPinCard.value }).map(action => action.label)), ...(canDeleteCard.value ? ['删除卡片'] : [])])]);
const actionCapacity = ref(1);
const CardInspectDialog_state = ref(false);
const CardInspectDialog_id = ref<number | null>(null);
const CardInspectDialog_mode = ref<'view' | 'edit'>('view');
const openCardInspect = (id: number, mode: 'view' | 'edit' = 'view') => {
  CardInspectDialog_id.value = id;
  CardInspectDialog_mode.value = mode;
  CardInspectDialog_state.value = true;
};
const cardRowActions = (item: Card) => [
  ...(canViewCard.value ? [{ key: 'view', label: '查看卡片', onClick: () => openCardInspect(item.id) }] : []),
  ...(canUpdateCard.value
    ? [{ key: 'edit', label: '编辑卡片', disabled: !canMutateRows.value, onClick: () => openCardInspect(item.id, 'edit') }]
    : []),
  ...cardGovernanceActions(item, { approve: canApproveCard.value, update: canUpdateCard.value, pin: canPinCard.value })
    .map(action => ({ key: action.method, label: action.label, disabled: !canMutateRows.value, onClick: () => void openCardCommand(item.id, action) })),
  ...(canDeleteCard.value ? [{ key: 'delete', label: '删除卡片', disabled: !canMutateRows.value, onClick: () => openDeleteCardDialog(item) }] : []),
];

const pendingCardCommand = ref<{ id: number; action: CardGovernanceAction } | null>(null);
const cardCommandBusy = ref(false);
const cardCommandError = ref('');
const cardCommandCapabilities = () => ({ approve: canApproveCard.value, update: canUpdateCard.value, pin: canPinCard.value });
const matchingCardCommand = async (id: number, expected: CardGovernanceAction) => {
  const current = await client.cards.getManaged(id);
  return cardGovernanceActions(current, cardCommandCapabilities()).find(action => action.method === expected.method && action.label === expected.label);
};
const openCardCommand = async (id: number, action: CardGovernanceAction) => {
  if (!canMutateRows.value) return;
  cardCommandBusy.value = true;
  cardCommandError.value = '';
  try {
    const current = await matchingCardCommand(id, action);
    if (!current) throw new Error('卡片状态已变化，请刷新后重新选择操作。');
    pendingCardCommand.value = { id, action: current };
  } catch (cause) {
    cardCommandError.value = cause instanceof Error ? cause.message : '无法核对卡片当前状态。';
    notifier.error(cardCommandError.value);
    void getTableData();
  } finally {
    cardCommandBusy.value = false;
  }
};
const closeCardCommand = (open: boolean) => { if (!open && !cardCommandBusy.value) pendingCardCommand.value = null; };
const runCardCommand = async () => {
  const current = pendingCardCommand.value;
  if (!current || cardCommandBusy.value || !canMutateRows.value) return;
  cardCommandBusy.value = true;
  cardCommandError.value = '';
  try {
    if (!await matchingCardCommand(current.id, current.action)) throw new Error('卡片状态已变化，请刷新后重新选择操作。');
    await client.cards.batchManaged({ method: current.action.method, ids: [current.id] });
    pendingCardCommand.value = null;
    await getTableData();
  } catch (cause) {
    cardCommandError.value = cause instanceof Error ? cause.message : '卡片操作失败，请稍后重试。';
  } finally {
    cardCommandBusy.value = false;
  }
};

const tableItems = ref<Card[]>([]);
const tableSelected = ref<number[]>([]);
const tableTotalItems = ref(0);
const resultCount = ref<number | null>(null);
let lastQueryKey = '';
const defaultListRows = Number(TableListRowsOptions[0]?.value ?? 10);
const requestedListRows = positiveQueryInt(route.query.list_rows, defaultListRows);
const tableCurrentPage = ref(positiveQueryInt(route.query.page, 1));
const tableListRows = ref(TableListRowsOptions.some((option) => option.value === requestedListRows) ? requestedListRows : defaultListRows);
const tableSortBy = ref<TableSortItem[]>(typeof route.query.order_key === 'string' && AllTableHeaders.some((header) => header.value === route.query.order_key && header.sortable)
  ? [{ key: route.query.order_key, order: ['1', 'true', 'desc'].includes(String(route.query.order_desc)) ? 'desc' : 'asc' }]
  : []);
const tableSearchValue = ref<string | undefined>(typeof route.query.search_value === 'string' ? route.query.search_value : undefined);
const searchDraft = ref<string | undefined>(tableSearchValue.value);
const tableSearchFilter = ref<Record<string, unknown>>(queryFilters(route.query.filters));
const { searchDialogFilter, applySearchDialog } = useAdminTableSearch(tableSortBy, tableSearchFilter);
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(tableSearchValue, tableSearchFilter, SearchKeys, 'content', '搜索卡片内容');
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
const canMutateRows = computed(() => !tableLoading.value && !tableError.value);
let latestRequest = 0;

const errorMessage = (error: unknown) => {
  if (error instanceof Error && error.message) return error.message;
  if (typeof error === 'object' && error && 'message' in error && typeof error.message === 'string') return error.message;
  return '请求失败，请检查网络连接后重试。';
};

const getTableData = async () => {
  const requestId = ++latestRequest;
  tableLoading.value = true;
  tableError.value = null;
  const searchValue = tableSearchValue.value?.trim();
  const searchHighlight = searchSnapshot();
  const sort = tableSortBy.value[0];
  const params: Record<string, unknown> = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    ...tableSearchFilter.value,
  };
  if (searchValue) {
    params.search_value = mapTableSearchValue(searchValue, tableSearchFilter.value.search_keys, { status: SelectUtils.Cards.status, model: SelectUtils.Cards.model });
    params.search_like = 2;
  }
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  const queryKey = JSON.stringify(params);
  if (queryKey !== lastQueryKey) {
    tableItems.value = [];
    tableSelected.value = [];
    tableTotalItems.value = 0;
    resultCount.value = null;
    lastQueryKey = queryKey;
  }

  try {
    const result = await client.cards.listManaged(params as Parameters<typeof client.cards.listManaged>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    applySearchSnapshot(searchHighlight);
    tableCurrentPage.value = result.pagination?.currentPage ?? tableCurrentPage.value;
    tableTotalItems.value = result.pagination?.totalItems ?? result.data.length;
    resultCount.value = tableTotalItems.value;
  } catch (error) {
    if (requestId === latestRequest) tableError.value = errorMessage(error);
  } finally {
    if (requestId === latestRequest) tableLoading.value = false;
  }
};

const syncRouteQuery = () => {
  const next: Record<string, string | null | (string | null)[]> = { ...route.query };
  const sort = tableSortBy.value[0];
  const filterSortKey = typeof tableSearchFilter.value.order_key === 'string' ? tableSearchFilter.value.order_key : undefined;
  const orderKey = sort?.key ?? filterSortKey;
  const orderDesc = sort ? sort.order === 'desc' || sort.order === true : tableSearchFilter.value.order_desc !== false;
  next.page = String(tableCurrentPage.value);
  next.list_rows = String(tableListRows.value);
  if (tableSearchValue.value?.trim()) next.search_value = tableSearchValue.value.trim();
  else delete next.search_value;
  if (orderKey) {
    next.order_key = orderKey;
    next.order_desc = orderDesc ? '1' : '0';
  } else {
    delete next.order_key;
    delete next.order_desc;
  }
  if (Object.keys(tableSearchFilter.value).length) next.filters = JSON.stringify(tableSearchFilter.value);
  else delete next.filters;
  void router.replace({ query: next, hash: route.hash });
};

const refreshFromFirstPage = () => {
  syncRouteQuery();
  if (tableCurrentPage.value === 1) void getTableData();
  else tableCurrentPage.value = 1;
};
const submitSearch = () => {
  const value = searchDraft.value?.trim() || undefined;
  if (value === tableSearchValue.value) return;
  tableSearchValue.value = value;
  refreshFromFirstPage();
};
const clearSearch = () => {
  searchDraft.value = undefined;
  if (!tableSearchValue.value) return;
  tableSearchValue.value = undefined;
  refreshFromFirstPage();
};
const clearFilters = () => {
  tableSearchValue.value = undefined;
  searchDraft.value = undefined;
  tableSearchFilter.value = {};
  refreshFromFirstPage();
};
const resetTableView = () => {
  tableSearchValue.value = undefined;
  searchDraft.value = undefined;
  tableSearchFilter.value = {};
  tableSortBy.value = [];
  tableListRows.value = defaultListRows;
  if (tableCurrentPage.value === 1) { syncRouteQuery(); void getTableData(); }
  else tableCurrentPage.value = 1;
};


// 旧 inspect 深链只单向迁移到只读详情；列表条件留在 history state 中。
watch(() => route.query.inspect, (value) => {
  const id = positiveQueryInt(value, 0);
  if (!id) return;
  const next = { ...route.query };
  delete next.inspect;
  const source = router.resolve({ path: route.path, query: next, hash: route.hash }).fullPath;
  void router.replace({ path: `/apps/content/cards/${id}`, state: { listSource: source } });
}, { immediate: true });

const DeleteCardDialog_state = ref(false);
const DeleteCardDialog_data = ref<{ id: number }>({ id: 0 });
const DeleteCardFun = (id: number) => {
  if (!userStore.hasGrant('cards.delete', 'all')) return Promise.reject(new Error('当前账号无权删除卡片。'));
  return client.cards.deleteManaged(id).then(() => {
    DeleteCardDialog_state.value = false;
    return getTableData();
  });
};
const openDeleteCardDialog = (data: { id: number }) => {
  if (!canMutateRows.value) return;
  DeleteCardDialog_data.value = data;
  DeleteCardDialog_state.value = true;
};

const BatchOperate = ref<CardsBatchMethod | ''>('');
const cardBatchImpact = computed(() => {
  const descriptions: Record<string, string> = {
    top: '已置顶卡片将取消置顶；未置顶卡片将置顶。',
    ban: '已封禁卡片将恢复正常；其余所选卡片将设为封禁。',
    hide: '已隐藏卡片将恢复正常；其余所选卡片将设为隐藏。',
    approve: '仅待审核卡片会通过审核；其他状态保持不变。',
    delete: '所选卡片将被删除并停止展示。',
  };
  return `将处理 ${tableSelected.value.length} 张卡片。${descriptions[BatchOperate.value] || ''}`;
});
const BatchCardDialog_state = ref(false);
const BatchCardDialog_operate = ref('');
const BatchCardFun = () => {
  const method = BatchOperate.value;
  if (!method) return Promise.resolve();
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    return Promise.reject(new Error('当前账号无权执行所选批量操作。'));
  }
  return client.cards.batchManaged({ method, ids: tableSelected.value }).then(() => {
    BatchCardDialog_state.value = false;
    tableSelected.value = [];
    return getTableData();
  });
};
const openBatchCardDialog = () => {
  if (!canMutateRows.value) return;
  const method = BatchOperate.value;
  BatchCardDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchCardDialog_state.value = true;
};

const SearchCardsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(normalizeFilters(data));
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchCardsDialog_state.value = true;
};

const applySavedView = (query: Record<string, string | null | (string | null)[]>) => {
  const value = (key: string) => typeof query[key] === 'string' ? query[key] as string : undefined;
  const rows = positiveQueryInt(value('list_rows'), defaultListRows);
  tableListRows.value = TableListRowsOptions.some((option) => option.value === rows) ? rows : defaultListRows;
  tableSearchValue.value = value('search_value')?.trim() || undefined;
  searchDraft.value = tableSearchValue.value;
  tableSearchFilter.value = queryFilters(value('filters'));
  const orderKey = value('order_key');
  tableSortBy.value = orderKey && AllTableHeaders.some((header) => header.value === orderKey && header.sortable)
    ? [{ key: orderKey, order: ['1', 'true', 'desc'].includes(value('order_desc') || '') ? 'desc' : 'asc' }]
    : [];
  refreshFromFirstPage();
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  if (route.query.inspect) return;
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
</script>
