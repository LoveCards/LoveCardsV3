<template>
  <AdminPageHeader
    title="卡片管理"
    description="查看、筛选和治理站内卡片。编辑仅对当前账号拥有对应能力的字段开放。"
    :breadcrumbs="[{ title: '内容与社区' }, { title: '内容管理' }, { title: '卡片' }]"
  >
    <template #actions>
      <v-chip size="small" variant="tonal" color="primary">内容资源</v-chip>
    </template>
  </AdminPageHeader>

  <AdminPageContainer fluid>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :default-columns="defaultColumns"
      :result-count="tableTotalItems"
      preference-key="content-cards"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-row dense align="center">
          <v-col cols="12" lg="7">
            <v-row dense align="center">
              <v-col cols="12" sm="5" md="4">
                <v-select
                  v-model="BatchOperate"
                  :items="TableBatchOptions"
                  :disabled="tableLoading"
                  density="compact"
                  hide-details
                  item-title="title"
                  item-value="value"
                  label="批量操作"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="auto">
                <AdminActionGuard :capability="batchCapability" scope="all">
                  <v-btn
                    color="primary"
                    prepend-icon="mdi-playlist-edit"
                    variant="flat"
                    :disabled="tableSelected.length === 0 || BatchOperate === ''"
                    @click="openBatchCardDialog"
                  >
                    应用操作
                  </v-btn>
                </AdminActionGuard>
              </v-col>
              <v-col v-if="tableSelected.length" cols="auto">
                <v-chip size="small" variant="tonal">已选择 {{ tableSelected.length }} 项</v-chip>
              </v-col>
            </v-row>
          </v-col>

          <v-col cols="12" lg="5">
            <v-row dense align="center" justify="end">
              <v-col cols="12" sm="auto">
                <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="openSearchDialog">
                  高级筛选
                </v-btn>
              </v-col>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="tableSearchValue"
                  append-inner-icon="mdi-magnify"
                  clearable
                  density="compact"
                  hide-details
                  label="搜索卡片内容"
                  single-line
                  variant="outlined"
                  @update:model-value="searchTableData"
                />
              </v-col>
            </v-row>
          </v-col>
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
        class="text-no-wrap"
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
              {{ value }}
            </v-chip>
            <span v-if="renderTags(item.tags).length === 0" class="text-medium-emphasis">—</span>
          </div>
        </template>

        <template #[`item.id`]="{ item }">
          <v-btn
            class="text-none px-2"
            min-height="40"
            variant="text"
            @click="openCardPanel(item.id)"
          >
            #{{ item.id }}
          </v-btn>
        </template>

        <template #[`item.content`]="{ item }">
          <div class="card-content-excerpt text-high-emphasis">{{ cardExcerpt(item.content) }}</div>
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
          <div class="d-flex ga-1">
            <AdminActionGuard capability="cards.read" scope="all">
              <v-tooltip :text="cardPanelActionLabel" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :aria-label="cardPanelActionLabel"
                    :icon="cardPanelActionIcon"
                    size="small"
                    variant="text"
                    @click="openCardPanel(item.id)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
            <AdminActionGuard capability="cards.delete" scope="all">
              <v-tooltip text="删除卡片" location="top">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    aria-label="删除卡片"
                    color="error"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="openDeleteCardDialog(item)"
                  />
                </template>
              </v-tooltip>
            </AdminActionGuard>
          </div>
        </template>

        <template #[`item.is_top`]="{ item }">
          <v-chip size="small" :variant="Number(item.is_top) === 1 ? 'tonal' : 'outlined'" :color="Number(item.is_top) === 1 ? 'primary' : undefined">
            {{ renderTop(item.is_top) }}
          </v-chip>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip size="small" variant="tonal" :color="SelectUtils.getSelect(SelectUtils.Cards.status, item.status).color">
            {{ SelectUtils.getSelect(SelectUtils.Cards.status, item.status).title }}
          </v-chip>
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

  <EditCardDialog
    v-model:thisDialogState="EditCardDialog_state"
    v-model:editCardData="EditCardDialog_data"
    :getTableData="getTableData"
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
  />
  <PublicSearchDialog
    v-model:thisDialogState="SearchCardsDialog_state"
    :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter"
    :getTableData="getTableData"
    KeysMessages="默认[内容]"
  />
</template>

<script setup lang="ts">
import type { Card, CardsBatchMethod } from '@lovecards/sdk';
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue';
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue';
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue';
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue';
import EditCardDialog from '@/components/cards/EditCardDialog.vue';
import PublicBatchDialog from '@/components/shared/Table/BatchDialog.vue';
import PublicDeleteDialog from '@/components/shared/Table/DeleteDialog.vue';
import PublicSearchDialog from '@/components/shared/Table/SearchDialog.vue';
import { useApi } from '~/lib/api';
import { useTagsStore } from '@/stores/tagsStore';
import { useUserStore } from '~/stores/userStore';
import CommonUtils from '@/utils/common';
import SelectUtils from '~/utils/select';

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
  { title: 'ID', value: 'id', sortable: true },
  { title: '用户ID', value: 'user_id', sortable: true },
  { title: '封面', value: 'cover', sortable: false },
  { title: '内容', value: 'content', sortable: false },
  { title: '自定义字段', value: 'data', sortable: false },
  { title: '点赞数', value: 'goods', sortable: true },
  { title: '评论数', value: 'comments', sortable: true },
  { title: '浏览量', value: 'views', sortable: true },
  { title: '标签', value: 'tags', sortable: false },
  { title: '发布时间', value: 'created_at', sortable: true },
  { title: 'IP', value: 'post_ip', sortable: true },
  { title: '置顶', value: 'is_top', sortable: true },
  { title: '状态', value: 'status', sortable: true },
  { title: '操作', value: 'operate', sortable: false },
];
const defaultColumns = ['id', 'cover', 'content', 'goods', 'comments', 'created_at', 'status', 'operate'];
const visibleColumns = ref([...defaultColumns]);
const TableHeaders = computed(() => AllTableHeaders.filter((header) => visibleColumns.value.includes(header.value)));
const columnOptions = AllTableHeaders.map((header) => ({ title: header.title, key: header.value, required: ['content', 'operate'].includes(header.value) }));
const SearchKeys = AllTableHeaders.slice(0, -1);
const BaseTableBatchOptions = [
  { title: '置顶/取消', value: 'top' },
  { title: '封禁/取消', value: 'ban' },
  { title: '隐藏/取消', value: 'hide' },
  { title: '审核/取消', value: 'approve' },
  { title: '删除', value: 'delete' },
] satisfies Array<{ title: string; value: CardsBatchMethod }>;
const TableListRowsOptions = SelectUtils.Common.Table.ListRowsOptions;
const userStore = useUserStore();
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
const renderTags = (tags: unknown) => normalizeTagIds(tags).flatMap((item) => {
  const tag = Tags.value.find((tagItem) => Number(tagItem.id) === Number(item));
  return tag ? [`#${tag.id} ${tag.name}`] : [];
});
const renderTop = (data: unknown) => SelectUtils.Cards.top.find((item) => item.value === Number(data))?.title ?? '—';
const cardExcerpt = (content: unknown) => {
  const normalized = String(content ?? '').replace(/\s+/gu, ' ').trim();
  if (!normalized) return '—';
  const characters = Array.from(normalized);
  return characters.length > 72 ? `${characters.slice(0, 72).join('')}…` : normalized;
};
const canUpdateCard = computed(() => userStore.hasGrant('cards.update', 'all'));
const canApproveCard = computed(() => userStore.hasGrant('cards.approve', 'all'));
const cardPanelActionLabel = computed(() => canUpdateCard.value ? '编辑卡片' : canApproveCard.value ? '审核卡片' : '查看卡片');
const cardPanelActionIcon = computed(() => canUpdateCard.value ? 'mdi-pencil' : canApproveCard.value ? 'mdi-gavel' : 'mdi-eye-outline');

const tableItems = ref<Card[]>([]);
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
  if (searchValue) {
    params.search_value = searchValue;
    params.search_like = 2;
  }
  if (sort) {
    params.order_key = sort.key;
    params.order_desc = sort.order === 'desc';
  }

  try {
    const result = await client.cards.listManaged(params as Parameters<typeof client.cards.listManaged>[0]);
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

const EditCardDialog_state = ref(false);
const EditCardDialog_data = ref<Card | null>(null);
const openCardPanel = (id: number) => {
  EditCardDialog_data.value = { id } as Card;
  EditCardDialog_state.value = true;
  const next = { ...route.query, inspect: String(id) };
  void router.replace({ query: next, hash: route.hash });
};
watch(() => route.query.inspect, (value) => {
  const id = positiveQueryInt(value, 0);
  if (id > 0 && (!EditCardDialog_state.value || EditCardDialog_data.value?.id !== id)) {
    EditCardDialog_data.value = { id } as Card;
    EditCardDialog_state.value = true;
  }
  if (id === 0 && EditCardDialog_state.value) EditCardDialog_state.value = false;
}, { immediate: true });
watch(EditCardDialog_state, (open) => {
  if (open || !route.query.inspect) return;
  const next = { ...route.query };
  delete next.inspect;
  void router.replace({ query: next, hash: route.hash });
});

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
  DeleteCardDialog_data.value = data;
  DeleteCardDialog_state.value = true;
};

const BatchOperate = ref<CardsBatchMethod | ''>('');
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
  const method = BatchOperate.value;
  BatchCardDialog_operate.value = TableBatchOptions.value.find((option) => option.value === method)?.title || '';
  if (tableSelected.value.length) BatchCardDialog_state.value = true;
};

const SearchCardsDialog_state = ref(false);
const setTableSearchFilter = (data: Record<string, unknown>) => {
  tableSearchFilter.value = data;
  tableCurrentPage.value = 1;
  syncRouteQuery();
};
const openSearchDialog = () => {
  SearchCardsDialog_state.value = true;
};

watch([tableCurrentPage, tableListRows, tableSortBy], () => {
  syncRouteQuery();
  void getTableData();
}, { deep: true, immediate: true });
</script>

<style scoped>
.card-content-excerpt {
  display: -webkit-box;
  max-width: 360px;
  overflow: hidden;
  overflow-wrap: anywhere;
  line-height: 1.4;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
