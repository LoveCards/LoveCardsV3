<!-- 用户管理 -->
<template>
  <NuxtLayout name="root">
    <v-container max-width="sm">
      <!-- 标题 -->
      <v-row class="pt-2">
        <v-col cols="6">
          <h1 class="text-primary font-weight-bold">卡片管理</h1>
        </v-col>
      </v-row>

      <!-- 内容 -->
      <v-row>
        <v-col cols="12">
          <v-card class="pa-4">
            <!-- 表格头部 -->
            <v-row>
              <!-- 工具栏 -->
              <v-col cols="4" md="8" class="d-flex align-center">
                <!-- <v-btn color="accent" variant="flat"> 添加用户 </v-btn> -->
              </v-col>
              <!-- 搜索栏 -->
              <v-col cols="8" md="4" class="d-flex justify-end">
                <v-btn elevation="0" icon="mdi-filter" size="small" variant="text"
                  @click="openSearchUserDialog()"></v-btn>
                <v-text-field color="accent" append-inner-icon="mdi-magnify" density="compact" label="搜索"
                  variant="outlined" v-model="tableSearchValue" @input="searchTableData" hide-details
                  single-line></v-text-field>
              </v-col>
            </v-row>

            <!-- 表格身体 -->
            <v-row>
              <v-col cols="12">
                <v-card variant="outlined" color="#E0E0E0">
                  <v-data-table :style="{ 'white-space': 'nowrap' }" :headers="TableHeaders" :items="tableItems"
                    :items-per-page="tableListRows" item-value="id" v-model="tableSelected" show-select>
                    <!-- 头部选择框 -->
                    <template v-slot:header.data-table-select="{
                      allSelected,
                      selectAll,
                      someSelected,
                    }">
                      <v-checkbox-btn :indeterminate="someSelected && !allSelected" :model-value="allSelected"
                        color="accent" @update:model-value="selectAll(!allSelected)"></v-checkbox-btn>
                    </template>

                    <!-- 内容选择框 -->
                    <template v-slot:item.data-table-select="{
                      internalItem,
                      isSelected,
                      toggleSelect,
                    }">
                      <v-checkbox-btn :model-value="isSelected(internalItem)" color="accent"
                        @update:model-value="toggleSelect(internalItem)"></v-checkbox-btn>
                    </template>

                    <!-- 标签 -->
                    <template v-slot:[`item.tags`]="{ item }">
                      <v-chip size="x-small" v-for="(value, index) in renderTags(item.tags)" :key="index">
                        {{ value }}
                      </v-chip>
                    </template>

                    <!-- 内容 -->
                    <template v-slot:[`item.content`]="{ item }">
                      <v-text-field width="250" variant="underlined" :model-value="item.content"
                        readonly></v-text-field>
                    </template>

                    <!-- 封面 -->
                    <template v-slot:[`item.cover`]="{ item }">
                      <v-img class="rounded-lg" :width="100" aspect-ratio="16/9" cover :alt="item.username"
                        :src="item.cover"></v-img>
                    </template>

                    <!-- 操作 -->
                    <template v-slot:[`item.operate`]="{ item }">
                      <v-btn icon="mdi-pencil" elevation="0" size="small" variant="text"
                        @click="openEditCardDialog(item)"></v-btn>
                      <v-btn icon="mdi-delete" elevation="0" size="small" variant="text"
                        @click="openDeleteCardDialog(item)"></v-btn>
                    </template>

                    <!-- 置顶状态 -->
                    <template v-slot:[`item.is_top`]="{ item }">
                      <v-chip size="small">
                        {{ renderTop(item.is_top) }}
                      </v-chip>
                    </template>

                    <!-- 卡片状态 -->
                    <template v-slot:[`item.status`]="{ item }">
                      <v-chip size="small">
                        {{ SelectUtils.getSelect(SelectUtils.Cards.status, item.status).title }}
                      </v-chip>
                    </template>

                    <!-- 无结果时的显示内容 -->
                    <template v-slot:no-data>
                      没有找到相关数据
                    </template>
                    <template v-slot:bottom>
                      <!-- 隐藏默认分页器 -->
                    </template>
                  </v-data-table>
                </v-card>
              </v-col>
            </v-row>

            <!-- 表格脚部 -->
            <v-row>
              <!-- 批量操作 -->
              <v-col cols="12" md="6" class="d-flex justify-start align-center">
                <v-select density="compact" label="请选择操作" max-width="160px" hide-details variant="outlined"
                  color="accent" :items="TableBatchOptions" item-title="title" item-value="value"
                  v-model="BatchOperate"></v-select>
                <v-btn color="accent" variant="flat" class="ml-2" @click="openBatchCardDialog"
                  :disabled="tableSelected.length === 0 || BatchOperate === ''">批量操作</v-btn>
              </v-col>
              <!-- 分页按钮 -->
              <v-col cols="12" md="6" class="d-flex justify-end align-center">
                <v-menu>
                  <template v-slot:activator="{ props }">
                    <v-btn elevation="0" icon="mdi-table-cog" size="small" v-bind="props" variant="text"></v-btn>
                  </template>
                  <v-list>
                    <v-list-item v-for="(item, index) in SelectUtils.Common.Table.ListRowsOptions" :key="index"
                      :value="index">
                      <v-list-item-title @click="tableListRows = item.value">{{
                        item.title
                        }}</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
                <v-divider vertical></v-divider>
                <!-- <v-select density="compact" label="行/页" max-width="80px" hide-details variant="outlined" color="accent"
									:items="TableListRowsOptions" v-model="tableListRows"></v-select> -->
                <v-pagination v-model="tableCurrentPage" :length="tablePaginationLength" :total-visible="5"
                  color="accent" size="small" variant="elevated"></v-pagination>
              </v-col>
            </v-row>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </NuxtLayout>

  <!-- 编辑对话框 -->
  <EditCardDialog v-model:thisDialogState="EditCardDialog_state" v-model:editCardData="EditCardDialog_data"
    :getTableData="getTableData"></EditCardDialog>
  <!-- 删除对话框 -->
  <PublicDeleteDialog v-model:thisDialogState="DeleteCardDialog_state" v-model:deleteData="DeleteCardDialog_data"
    :deleteFun="DeleteCardFun"></PublicDeleteDialog>
  <!-- 批量处理用户对话框 -->
  <PublicBatchDialog v-model:thisDialogState="BatchCardDialog_state" v-model:batchData="tableSelected"
    v-model:batchOptions="BatchCardDialog_operate" :batchFun="BatchCardFun"></PublicBatchDialog>
  <!-- 搜索对话框 -->
  <PublicSearchDialog v-model:thisDialogState="SearchCardsDialog_state" :KEYS="SearchKeys"
    :setFilter="setTableSearchFilter" :getTableData="getTableData" KeysMessages="默认[内容]"></PublicSearchDialog>
</template>

<script setup lang="ts">
import CardsApi from "@/api/app/cards";
import CommonUtils from "@/api/utils/common";
import PublicDeleteDialog from "@/components/apps/public/Table/DeleteDialog.vue";
import EditCardDialog from "@/components/apps/cards/EditCardDialog.vue";
import PublicBatchDialog from "@/components/apps/public/Table/BatchDialog.vue";
import PublicSearchDialog from "@/components/apps/public/Table/SearchDialog.vue";
import SelectUtils from "~/api/utils/select";
import { useTagsStore } from "@/stores/tagsStore";
const notifier = useNotifier();

//渲染数据预处理
const renderTop = (data: any) => {
  if (data !== '' && data !== undefined) {
    let result = "";
    const top = SelectUtils.Cards.top.find((item: any) => item.value == data);
    if (top) {
      result = top.title;
    }
    return result;
  } else {
    return "";
  }
};

//表格头部
const TableHeaders = [
  { title: "ID", value: "id" },
  { title: "用户ID", value: "user_id" },
  { title: "封面", value: "cover" },
  { title: "内容", value: "content" },
  { title: "自定义字段", value: "data" },
  { title: "点赞数", value: "goods" },
  { title: "评论数", value: "comments" },
  { title: "浏览量", value: "views" },
  { title: "标签", value: "tags" },
  { title: "发布时间", value: "created_at" },
  { title: "IP", value: "post_ip" },
  { title: "置顶", value: "is_top" },
  { title: "状态", value: "status" },
  { title: "操作", value: "operate" },
];
//搜索字段
const SearchKeys = [...TableHeaders];
SearchKeys.pop();
//批量操作选项
const TableBatchOptions = [
  { title: '置顶/取消', value: 'top' },
  { title: '封禁/取消', value: 'ban' },
  { title: "隐藏/取消", value: "hide" },
  { title: "审核/取消", value: "approve" },
  { title: "删除", value: "delete" },
];

//标签
const tagsStore = useTagsStore();
const Tags = ref([] as any);
Tags.value = tagsStore.tags;
//标签数据预处理
const renderTags = (tags: any) => {
  if (tags !== undefined && tags !== null) {
    tags = JSON.parse(tags);
    let TagsValue: string[] = [];
    tags.forEach((item: any) => {
      const tag = Tags.value.find((tagItem: any) => tagItem.id === item);
      if (tag) {
        TagsValue.push('#' + tag.id + ' ' + tag.name);
      }
    });
    return TagsValue;
  } else {
    return [];
  }
};

const tableItems = ref([{} as any]);//表格数据
const tableSelected = ref([]);//批量选择数据
const tablePaginationLength = ref(0);//页面数量
const tableCurrentPage = ref(1);//当前页面
const tableSearchValue = ref(undefined);//搜索值
const tableSearchFilter = ref({});//搜索过滤器

//每页项目数量
const tableListRows = ref(SelectUtils.Common.Table.ListRowsOptions[0].value);

//EditCardDialog组件
const EditCardDialog_state = ref(false);
const EditCardDialog_data = ref({} as any);
const openEditCardDialog = (data: any) => {
  EditCardDialog_data.value = data;
  EditCardDialog_state.value = true;
};

//DeleteDialog组件
const DeleteCardFun = (id: any) => {
  CardsApi.deleteCard(id).then(() => {
    DeleteCardDialog_state.value = false;
    getTableData();
  });
}
const DeleteCardDialog_state = ref(false);
const DeleteCardDialog_data = ref({} as any);
const openDeleteCardDialog = (data: {}) => {
  DeleteCardDialog_data.value = data;
  DeleteCardDialog_state.value = true;
};

// 批量操作相关
const BatchOperate = ref('');
//BatchCardDialog组件
const BatchCardFun = () => {
  const data = {
    ids: tableSelected.value,
    method: BatchOperate.value,
  }
  CardsApi.batchOperate(data).then(() => {
    BatchCardDialog_state.value = false;
    getTableData();
  })
}
const BatchCardDialog_state = ref(false);
//tableSelected
const BatchCardDialog_operate = ref('');
const openBatchCardDialog = () => {
  const headerMap = new Map(TableBatchOptions.map(header => [header.value, header.title]));
  BatchCardDialog_operate.value = headerMap.get(BatchOperate.value) || '';
  if (tableSelected.value.length != 0) {
    BatchCardDialog_state.value = true;
  }
};

//SearchDialog组件
const SearchCardsDialog_state = ref(false);
//子父传参
const setTableSearchFilter = (data: any) => {
  tableSearchFilter.value = data;
}
const openSearchUserDialog = () => {
  SearchCardsDialog_state.value = true;
};

//获取表格数据
const getTableData = () => {
  //合并参数
  const params = {
    page: tableCurrentPage.value,
    list_rows: tableListRows.value,
    search_value: tableSearchValue.value,
    ...tableSearchFilter.value
  };
  //获取数据
  CardsApi.getCardIndex(params)
    .then((response) => {
      const data = response.data;
      tableCurrentPage.value = data.current_page;
      tablePaginationLength.value = data.last_page;
      tableItems.value = data.data;
    })
};

//搜索防抖
const searchTableData = useDebounce(() => {
  getTableData();
}, 500);

//数据获取监控
watch([tableCurrentPage, tableListRows], (newValue, oldValue) => {
  getTableData();
});

//页面加载完成后获取数据
onMounted(() => {
  getTableData();
});
</script>
