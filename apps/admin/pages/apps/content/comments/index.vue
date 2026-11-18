<template>
  <AdminPageHeader
    title="评论"
    description="查看评论内容与状态，按需审核和管理。"
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
      :action-labels="commentActionLabels"
      :selection-width="48"
      preference-key="content-comments"
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
              <v-btn variant="outlined" :disabled="!canMutateRows || BatchOperate === ''" @click="openBatchCommentDialog">应用操作</v-btn>
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
        title="刷新评论列表失败"
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

        <template #[`item.id`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">#<AdminTableSearchHighlight :text="item.id" :query="highlightQuery('id')" /></span>
        </template>

        <template #[`item.user_id`]="{ item }">
          <AdminTableSearchHighlight :text="`${item.username?.trim() || '用户'} #${item.user_id}`" :query="highlightQuery('user_id')" />
        </template>

        <template #[`item.content`]="{ item }">
          <AdminContentSummary :content="item.content" :author="item.username" :user-id="item.user_id" :highlight-query="highlightQuery('content')" />
        </template>

        <template #[`item.operate`]="{ item }">
          <AdminTableRowActions :actions="commentRowActions(item)" :capacity="actionCapacity" more-label="更多评论操作" />
        </template>

        <template #[`item.is_pinned`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ item.is_pinned ? '置顶' : '—' }}</span>
        </template>

        <template #[`item.created_at`]="{ item }">
          <span class="text-body-2 text-medium-emphasis"><AdminTableTime :value="item.created_at" :query="highlightQuery('created_at')" /></span>
        </template>

        <template #[`item.status`]="{ item }">
          <AdminContentStatus :status="item.status" :pinned="item.is_pinned && !visibleColumns.includes('is_pinned')" :highlight-query="highlightQuery('status')" />
        </template>

        <template v-for="field in ['aid', 'goods', 'post_ip']" :key="field" #[`item.${field}`]="{ item }">
          <AdminTableSearchHighlight :text="tableSearchCell(item, field)" :query="highlightQuery(field)" />
        </template>

        <template #no-data>
          <v-empty-state
            :icon="tableError ? 'mdi-cloud-alert-outline' : 'mdi-comment-text-outline'"
            :title="tableError ? '无法加载评论' : '暂无评论'"
            :text="tableError || '当前筛选条件下没有可显示的评论。可以调整关键词或高级筛选条件。'"
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

  <CommentInspectDialog v-model="CommentInspectDialog_state" :comment-id="CommentInspectDialog_id" :initial-mode="CommentInspectDialog_mode" :source-path="route.fullPath" @saved="getTableData" />
  <AdminDangerActionDialog
    :model-value="Boolean(pendingCommentCommand)"
    :title="pendingCommentCommand?.action.label ?? '确认评论操作'"
    :action-label="pendingCommentCommand?.action.label ?? '确认执行'"
    :impact="pendingCommentCommand?.action.impact ?? ''"
    :resource-label="pendingCommentCommand ? `评论 #${pendingCommentCommand.id}` : ''"
    :loading="commentCommandBusy"
    :error="commentCommandError"
    @update:model-value="closeCommentCommand"
    @confirm="runCommentCommand"
  />
  <AdminDangerActionDialog
    v-model="PinCommentDialog_state"
    :action-label="PinCommentDialog_data?.is_pinned ? '取消置顶' : '确认置顶'"
    :color="PinCommentDialog_data?.is_pinned ? 'warning' : 'primary'"
    :error="PinCommentDialog_error"
    :impact="pinDialogImpact"
    :loading="PinCommentDialog_loading"
    :resource-label="pinDialogResource"
    :title="PinCommentDialog_data?.is_pinned ? '取消评论置顶' : '置顶评论'"
    @confirm="submitPinComment"
  />
  <PublicDeleteDialog
    v-model:thisDialogState="DeleteCommentDialog_state"
    v-model:deleteData="DeleteCommentDialog_data"
    :deleteFun="DeleteCommentFun"
  />
  <PublicBatchDialog
    v-model:thisDialogState="BatchCommentDialog_state"
    v-model:batchData="tableSelected"
    v-model:batchOptions="BatchCommentDialog_operate"
    :batchFun="BatchCommentFun"
    :impact-description="commentBatchImpact"
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchCommentsDialog_state"
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
import type { Comment, CommentsBatchMethod } from '@lovecards/sdk';
import { commentGovernanceActions, type CommentGovernanceAction } from '~/components/comments/commentGovernanceActions';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue';
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue';
import CommentInspectDialog from '~/components/comments/CommentInspectDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useAdminNotifier } from '~/composables/useAdminNotifier';
import { useApi } from '~/lib/api';
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
  { title: '应用ID', value: 'aid', sortable: true },
  { title: '自定义字段', value: 'data', sortable: false },
  { title: '点赞数', value: 'goods', sortable: true },
  { title: '发布时间', value: 'created_at', sortable: true, minWidth: 176 },
  { title: 'IP', value: 'post_ip', sortable: true },
  { title: '卡片置顶', value: 'is_pinned', sortable: false },
  { title: '状态', value: 'status', sortable: true, minWidth: 112 },
  { title: '操作', value: 'operate', sortable: false, minWidth: 144 },
];
const defaultColumns = ['content', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['content', 'operate'].includes(header.value) }));
const SearchKeys = AllTableHeaders.filter((header) => !['is_pinned', 'operate'].includes(header.value))
  .map((header) => header.value === 'user_id' ? { ...header, title: '用户 ID' } : header);
const BaseTableBatchOptions = [
  { title: '切换封禁状态', value: 'ban' },
  { title: '切换隐藏状态', value: 'hide' },
  { title: '通过审核', value: 'approve' },
  { title: '删除', value: 'delete' },
] satisfies Array<{ title: string; value: CommentsBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const canDeleteComment = computed(() => userStore.hasGrant('comments.delete', 'all'));
const canViewComment = computed(() => userStore.hasGrant('comments.read', 'all'));
const canPin = computed(() => userStore.hasGrant('comments.pin', 'all'));
const canEditComment = computed(() => userStore.hasGrant('comments.update', 'all'));
const canModerateComment = computed(() => userStore.hasGrant('comments.moderate', 'all'));
const commentActionLabels = computed(() => ['查看评论', ...(canEditComment.value ? ['编辑评论'] : []), ...(canModerateComment.value ? ['通过审核', '封禁评论', '解除封禁', '隐藏评论', '取消隐藏'] : []), ...(canPin.value ? ['置顶评论', '取消置顶评论'] : []), ...(canDeleteComment.value ? ['删除评论'] : [])]);
const actionCapacity = ref(1);
const CommentInspectDialog_state = ref(false);
const CommentInspectDialog_id = ref<number | null>(null);
const CommentInspectDialog_mode = ref<'view' | 'edit'>('view');
const openCommentInspect = (id: number, mode: 'view' | 'edit' = 'view') => {
  CommentInspectDialog_id.value = id;
  CommentInspectDialog_mode.value = mode;
  CommentInspectDialog_state.value = true;
};
const commentRowActions = (item: Comment) => [
  ...(canViewComment.value ? [{ key: 'view', label: '查看评论', onClick: () => openCommentInspect(item.id) }] : []),
  ...(canEditComment.value
    ? [{ key: 'edit', label: '编辑评论', disabled: !canMutateRows.value, onClick: () => openCommentInspect(item.id, 'edit') }]
    : []),
  ...commentGovernanceActions(item, { moderate: canModerateComment.value, pin: false })
    .map(action => ({ key: action.method, label: action.label, disabled: !canMutateRows.value, onClick: () => void openCommentCommand(item.id, action) })),
  ...(canPin.value && Number(item.status) === 0 && !item.parent_id
    ? [{ key: 'pin', label: item.is_pinned ? '取消置顶评论' : '置顶评论', disabled: !canMutateRows.value, onClick: () => openPinCommentDialog(item) }]
    : []),
  ...(canDeleteComment.value ? [{ key: 'delete', label: '删除评论', disabled: !canMutateRows.value, onClick: () => openDeleteCommentDialog(item) }] : []),
];
const pendingCommentCommand = ref<{ id: number; action: CommentGovernanceAction } | null>(null);
const commentCommandBusy = ref(false);
const commentCommandError = ref('');
const matchingCommentCommand = async (id: number, expected: CommentGovernanceAction) => {
  const current = await client.comments.getManaged(id);
  return commentGovernanceActions(current, { moderate: canModerateComment.value, pin: false }).find(action => action.method === expected.method && action.label === expected.label);
};
const openCommentCommand = async (id: number, action: CommentGovernanceAction) => {
  if (!canMutateRows.value) return;
  commentCommandBusy.value = true;
  commentCommandError.value = '';
  try {
    const current = await matchingCommentCommand(id, action);
    if (!canMutateRows.value) return;
    if (!current) throw new Error('评论状态已变化，请刷新后重新选择操作。');
    pendingCommentCommand.value = { id, action: current };
  } catch (cause) {
    commentCommandError.value = cause instanceof Error ? cause.message : '无法核对评论当前状态。';
    notifier.error(commentCommandError.value);
    void getTableData();
  } finally { commentCommandBusy.value = false; }
};
const closeCommentCommand = (open: boolean) => { if (!open && !commentCommandBusy.value) pendingCommentCommand.value = null; };
const runCommentCommand = async () => {
  const current = pendingCommentCommand.value;
  if (!current || commentCommandBusy.value || !canMutateRows.value) return;
  commentCommandBusy.value = true;
  commentCommandError.value = '';
  try {
    if (!await matchingCommentCommand(current.id, current.action)) throw new Error('评论状态已变化，请刷新后重新选择操作。');
    await client.comments.batchManaged({ method: current.action.method, ids: [current.id] });
    pendingCommentCommand.value = null;
    await getTableData();
  } catch (cause) { commentCommandError.value = cause instanceof Error ? cause.message : '评论操作失败，请稍后重试。'; }
  finally { commentCommandBusy.value = false; }
};
const batchCapabilityFor = (method: CommentsBatchMethod | '') => method === 'delete' ? 'comments.delete' : 'comments.moderate';
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

const tableItems = ref<Comment[]>([]);
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
const { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery } = useAdminTableSearchFeedback(tableSearchValue, tableSearchFilter, SearchKeys, 'content', '搜索评论正文');
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
  if (searchValue) params.search_value = mapTableSearchValue(searchValue, tableSearchFilter.value.search_keys, { status: SelectUtils.Common.Status });
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
    const result = await client.comments.listManaged(params as Parameters<typeof client.comments.listManaged>[0]);
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

const DeleteCommentDialog_state = ref(false);
const DeleteCommentDialog_data = ref<{ id: number }>({ id: 0 });
const DeleteCommentFun = (id: number) => {
  if (!userStore.hasGrant('comments.delete', 'all')) return Promise.reject(new Error('当前账号无权删除评论。'));
  return client.comments.deleteManaged(id).then(() => {
    DeleteCommentDialog_state.value = false;
    return getTableData();
  });
};
const openDeleteCommentDialog = (data: { id: number }) => {
  if (!canMutateRows.value) return;
  DeleteCommentDialog_data.value = data;
  DeleteCommentDialog_state.value = true;
};


// 旧 inspect 深链只单向迁移到只读详情；列表条件留在 history state 中。
watch(() => route.query.inspect, (value) => {
  const id = positiveQueryInt(value, 0);
  if (!id) return;
  const next = { ...route.query };
  delete next.inspect;
  const source = router.resolve({ path: route.path, query: next, hash: route.hash }).fullPath;
  void router.replace({ path: `/apps/content/comments/${id}`, state: { listSource: source } });
}, { immediate: true });

const PinCommentDialog_state = ref(false);
const PinCommentDialog_data = ref<Comment | null>(null);
const PinCommentDialog_loading = ref(false);
const PinCommentDialog_error = ref('');
const pinDialogResource = computed(() => {
  const comment = PinCommentDialog_data.value;
  return comment ? `卡片 #${comment.card_id} · 评论 #${comment.id}` : '';
});
const pinDialogImpact = computed(() => PinCommentDialog_data.value?.is_pinned
  ? '该评论将回到所属卡片的普通评论顺序，恢复后不会自动重新置顶。'
  : '该评论会成为所属卡片唯一的置顶评论；如果已有置顶评论，将被原子替换。');
const openPinCommentDialog = (data: Comment) => {
  if (!canMutateRows.value) return;
  PinCommentDialog_data.value = data;
  PinCommentDialog_error.value = '';
  PinCommentDialog_state.value = true;
};
const submitPinComment = async () => {
  const comment = PinCommentDialog_data.value;
  if (!comment || PinCommentDialog_loading.value) return;
  PinCommentDialog_loading.value = true;
  PinCommentDialog_error.value = '';
  try {
    if (comment.is_pinned) await client.comments.unpinManaged(comment.card_id);
    else await client.comments.pinManaged(comment.card_id, { comment_id: comment.id });
    PinCommentDialog_state.value = false;
    await getTableData();
  } catch (error) {
    PinCommentDialog_error.value = errorMessage(error);
  } finally {
    PinCommentDialog_loading.value = false;
  }
};

const BatchOperate = ref<CommentsBatchMethod | ''>('');
const commentBatchImpact = computed(() => {
  const descriptions: Record<string, string> = {
    ban: '已封禁评论将恢复正常；其余所选评论将设为封禁。',
    hide: '已隐藏评论将恢复正常；其余所选评论将设为隐藏。',
    approve: '仅待审核评论会通过审核；已正常、封禁或隐藏的评论不会被这项操作改为正常。',
    delete: '所选评论将被删除并停止展示。',
  };
  return `将处理 ${tableSelected.value.length} 条评论。${descriptions[BatchOperate.value] || ''}`;
});
const BatchCommentDialog_state = ref(false);
const BatchCommentDialog_operate = ref('');
const BatchCommentFun = () => {
  const method = BatchOperate.value;
  if (!method) return Promise.resolve();
  if (!userStore.hasGrant(batchCapabilityFor(method), 'all')) {
    return Promise.reject(new Error('当前账号无权执行所选批量操作。'));
  }
  return client.comments.batchManaged({ method, ids: tableSelected.value }).then(() => {
    BatchCommentDialog_state.value = false;
    tableSelected.value = [];
    return getTableData();
  });
};
const openBatchCommentDialog = () => {
  if (!canMutateRows.value) return;
  const method = BatchOperate.value;
  BatchCommentDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchCommentDialog_state.value = true;
};

const SearchCommentsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(normalizeFilters(data));
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchCommentsDialog_state.value = true;
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
