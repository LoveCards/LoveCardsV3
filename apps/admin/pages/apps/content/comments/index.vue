<template>
  <AdminPageHeader
    title="评论"
    description="查看评论内容与状态，按需审核和管理。"
    :breadcrumbs="[{ title: '内容' }]"
  />

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      preference-key="content-comments"
      @reset-view="resetTableView"
    >
      <template #search>
        <v-text-field v-model="tableSearchValue" clearable label="搜索评论正文" prepend-inner-icon="mdi-magnify" single-line @update:model-value="searchTableData" />
      </template>
      <template #actions>
        <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="openSearchDialog">搜索与排序</v-btn>
      </template>
      <template v-if="tableSelected.length" #selection>
        <v-row dense align="center" aria-live="polite">
          <v-col cols="12" sm="auto"><span class="text-body-2">已选择 {{ tableSelected.length }} 项</span></v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select v-model="BatchOperate" :items="TableBatchOptions" :disabled="tableLoading" item-title="title" item-value="value" label="批量操作" />
          </v-col>
          <v-col cols="auto">
            <AdminActionGuard :capability="batchCapability" scope="all">
              <v-btn variant="outlined" :disabled="BatchOperate === ''" @click="openBatchCommentDialog">应用操作</v-btn>
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
          <v-btn class="text-none px-2" min-height="40" variant="text" @click="openCommentPanel(item.id)">
            #{{ item.id }}
          </v-btn>
        </template>

        <template #[`item.content`]="{ item }">
          <AdminContentSummary :content="item.content" :author="item.username" :user-id="item.user_id" />
        </template>

        <template #[`item.operate`]="{ item }">
          <div class="d-flex align-center ga-1">
            <AdminActionGuard capability="comments.read" scope="all">
              <v-btn variant="text" @click="openCommentPanel(item.id)">{{ commentPanelActionLabel }}</v-btn>
            </AdminActionGuard>
            <v-menu v-if="canDeleteComment || (canPin && Number(item.status) === 0 && !item.parent_id)" location="bottom end">
              <template #activator="{ props }">
                <v-btn v-bind="props" aria-label="更多评论操作" icon="mdi-dots-horizontal" variant="text" />
              </template>
              <v-list density="compact">
                <v-list-item v-if="canPin && Number(item.status) === 0 && !item.parent_id" :prepend-icon="item.is_pinned ? 'mdi-pin-off-outline' : 'mdi-pin-outline'" :title="item.is_pinned ? '取消置顶评论' : '置顶评论'" @click="openPinCommentDialog(item)" />
                <v-list-item v-if="canDeleteComment" prepend-icon="mdi-delete-outline" title="删除评论" @click="openDeleteCommentDialog(item)" />
              </v-list>
            </v-menu>
          </div>
        </template>

        <template #[`item.is_pinned`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ item.is_pinned ? '置顶' : '—' }}</span>
        </template>

        <template #[`item.created_at`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ item.created_at }}</span>
        </template>

        <template #[`item.status`]="{ item }">
          <AdminContentStatus :status="item.status" :pinned="item.is_pinned && !visibleColumns.includes('is_pinned')" />
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

  <EditCommentDialog
    v-model:thisDialogState="EditCommentDialog_state"
    v-model:CommentData="EditCommentDialog_data"
    :getTableData="getTableData"
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
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import EditCommentDialog from '@/components/comments/EditCommentDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useApi } from '~/lib/api';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';
import { tableSearchValue as mapTableSearchValue } from '~/utils/tableSearchValue';

type TableSortItem = { key: string; order?: boolean | 'asc' | 'desc' };

const client = useApi();
const route = useRoute();
const router = useRouter();

const positiveQueryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
};
const queryFilters = (value: unknown): Record<string, unknown> => {
  if (typeof value !== 'string' || !value) return {};
  try {
    const parsed = JSON.parse(value);
    return typeof parsed === 'object' && parsed && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
};

const AllTableHeaders = [
  { title: '内容', value: 'content', sortable: false, minWidth: 320, width: '55%' },
  { title: 'ID', value: 'id', sortable: true },
  { title: '用户ID', value: 'user_id', sortable: true },
  { title: '应用ID', value: 'aid', sortable: true },
  { title: '自定义字段', value: 'data', sortable: false },
  { title: '点赞数', value: 'goods', sortable: true },
  { title: '发布时间', value: 'created_at', sortable: true, width: 180 },
  { title: 'IP', value: 'post_ip', sortable: true },
  { title: '卡片置顶', value: 'is_pinned', sortable: false },
  { title: '状态', value: 'status', sortable: true, width: 120 },
  { title: '操作', value: 'operate', sortable: false, width: 160 },
];
const defaultColumns = ['content', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['content', 'operate'].includes(header.value) }));
const SearchKeys = AllTableHeaders.filter((header) => !['is_pinned', 'operate'].includes(header.value));
const BaseTableBatchOptions = [
  { title: '切换封禁状态', value: 'ban' },
  { title: '切换隐藏状态', value: 'hide' },
  { title: '通过审核', value: 'approve' },
  { title: '删除', value: 'delete' },
] satisfies Array<{ title: string; value: CommentsBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
const canDeleteComment = computed(() => userStore.hasGrant('comments.delete', 'all'));
const canPin = computed(() => userStore.hasGrant('comments.pin', 'all'));
const commentPanelActionLabel = computed(() => userStore.hasGrant('comments.update', 'all')
  ? '编辑评论'
  : userStore.hasGrant('comments.moderate', 'all') ? '审核评论' : '查看评论');
const batchCapabilityFor = (method: CommentsBatchMethod | '') => method === 'delete' ? 'comments.delete' : 'comments.moderate';
const TableBatchOptions = computed(() => BaseTableBatchOptions.filter((option) => userStore.hasGrant(batchCapabilityFor(option.value), 'all')));
const batchCapability = computed(() => batchCapabilityFor(BatchOperate.value));

const tableItems = ref<Comment[]>([]);
const tableSelected = ref<number[]>([]);
const tableTotalItems = ref(0);
const defaultListRows = Number(TableListRowsOptions[0]?.value ?? 10);
const requestedListRows = positiveQueryInt(route.query.list_rows, defaultListRows);
const tableCurrentPage = ref(positiveQueryInt(route.query.page, 1));
const tableListRows = ref(TableListRowsOptions.some((option) => option.value === requestedListRows) ? requestedListRows : defaultListRows);
const tableSortBy = ref<TableSortItem[]>(typeof route.query.order_key === 'string'
  ? [{ key: route.query.order_key, order: ['1', 'true', 'desc'].includes(String(route.query.order_desc)) ? 'desc' : 'asc' }]
  : []);
const tableSearchValue = ref<string | undefined>(typeof route.query.search_value === 'string' ? route.query.search_value : undefined);
const tableSearchFilter = ref<Record<string, unknown>>(queryFilters(route.query.filters));
const { searchDialogFilter, applySearchDialog } = useAdminTableSearch(tableSortBy, tableSearchFilter);
const tableLoading = ref(false);
const tableError = ref<string | null>(null);
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

  try {
    const result = await client.comments.listManaged(params as Parameters<typeof client.comments.listManaged>[0]);
    if (requestId !== latestRequest) return;
    tableItems.value = result.data;
    tableCurrentPage.value = result.pagination?.currentPage ?? tableCurrentPage.value;
    tableTotalItems.value = result.pagination?.totalItems ?? result.data.length;
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
const searchTableData = CommonUtils.lodash.debounce(refreshFromFirstPage, 500);
const clearFilters = () => {
  tableSearchValue.value = undefined;
  tableSearchFilter.value = {};
  refreshFromFirstPage();
};
const resetTableView = () => {
  tableSearchValue.value = undefined;
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
  DeleteCommentDialog_data.value = data;
  DeleteCommentDialog_state.value = true;
};

const EditCommentDialog_state = ref(false);
const EditCommentDialog_data = ref<Comment | null>(null);
const openCommentPanel = (id: number) => {
  EditCommentDialog_data.value = { id } as Comment;
  EditCommentDialog_state.value = true;
  void router.replace({ query: { ...route.query, inspect: String(id) }, hash: route.hash });
};
watch(() => route.query.inspect, (value) => {
  const id = positiveQueryInt(value, 0);
  if (id > 0 && (!EditCommentDialog_state.value || EditCommentDialog_data.value?.id !== id)) {
    EditCommentDialog_data.value = { id } as Comment;
    EditCommentDialog_state.value = true;
  }
  if (id === 0 && EditCommentDialog_state.value) EditCommentDialog_state.value = false;
}, { immediate: true });
watch(EditCommentDialog_state, (open) => {
  if (open || !route.query.inspect) return;
  const next = { ...route.query };
  delete next.inspect;
  void router.replace({ query: next, hash: route.hash });
});

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
  const method = BatchOperate.value;
  BatchCommentDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchCommentDialog_state.value = true;
};

const SearchCommentsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  applySearchDialog(data);
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchCommentsDialog_state.value = true;
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
</script>
