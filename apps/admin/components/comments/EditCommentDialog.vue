<template>
  <v-dialog v-model="thisDialogState" :aria-label="panelTitle" max-width="840" :persistent="saving">
    <v-card prepend-icon="mdi-comment-edit-outline" :title="panelTitle">
      <v-card-text class="pt-4">
        <v-progress-linear v-if="loading" class="mb-4" indeterminate />
        <v-alert v-if="loadError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ loadError }}
          <template #append><v-btn variant="outlined" @click="loadComment">重试</v-btn></template>
        </v-alert>
        <v-alert v-else-if="panelMode === 'view'" class="mb-4" type="info" variant="tonal">
          当前账号只有查看权限，评论内容和治理状态保持只读。
        </v-alert>
        <v-alert v-else class="mb-4" type="info" variant="tonal">
          普通用户发布后不能编辑评论正文；获得授权的后台修改会自动记录操作者和变更前后状态。
        </v-alert>
        <v-alert v-if="formError && !loadError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>

        <v-list bg-color="surface-variant" class="mb-4" density="compact" rounded="lg">
          <v-row class="ma-0" dense>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-account-outline" subtitle="用户 ID" :title="displayValue(EditCommentData.edit.user_id)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-clock-outline" subtitle="发布时间" :title="displayValue(EditCommentData.edit.created_at)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-ip-network-outline" subtitle="发布 IP" :title="displayValue(EditCommentData.edit.post_ip)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-heart-outline" subtitle="获赞数" :title="displayValue(EditCommentData.edit.goods)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-card-text-outline" subtitle="所属卡片">
                <template #title><AdminResourceLink resource-type="card" :resource-id="EditCommentData.edit.card_id" /></template>
              </v-list-item>
            </v-col>
          </v-row>
        </v-list>

        <v-textarea v-if="!loading && !loadError" v-model="EditCommentData.edit.content" auto-grow class="mb-4" :disabled="saving || !canUpdateContent" label="评论正文" maxlength="140" rows="4" variant="outlined" />

        <v-expansion-panels v-if="!loading && !loadError && EditCommentData.edit.data" class="mb-4" variant="accordion">
          <v-expansion-panel title="查看自定义字段">
            <v-expansion-panel-text>
              <pre class="ma-0 text-body-2 text-wrap">{{ displayJson(EditCommentData.edit.data) }}</pre>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

        <v-row v-if="!loading && !loadError" dense>
          <v-col cols="12">
            <v-select
              v-model="EditCommentData.edit.status"
              :disabled="saving || !canModerate"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="SelectUtils.Common.Status"
              label="审核状态"
              variant="outlined"
            />
          </v-col>
        </v-row>

      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">{{ canEditComment ? '取消' : '关闭' }}</v-btn>
        <v-btn v-if="canEditComment" color="primary" :disabled="loading || Boolean(loadError)" :loading="saving" variant="flat" @click="submit">保存修改</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { Comment } from '@lovecards/sdk'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import SelectUtils from '~/utils/select'

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const canUpdateContent = computed(() => userStore.hasGrant('comments.update', 'all'))
const canModerate = computed(() => userStore.hasGrant('comments.moderate', 'all'))
const canEditComment = computed(() => canUpdateContent.value || canModerate.value)
const panelMode = computed<'view' | 'review' | 'edit'>(() => canUpdateContent.value ? 'edit' : canModerate.value ? 'review' : 'view')
const panelTitle = computed(() => panelMode.value === 'edit' ? '编辑评论' : panelMode.value === 'review' ? '审核评论' : '查看评论')
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const CommentData = defineModel<Comment | null>('CommentData')
const emptyComment = {} as Comment
const EditCommentData = ref<{ edit: Comment; origin: Comment }>({
  edit: emptyComment,
  origin: emptyComment,
})
const saving = ref(false)
const loading = ref(false)
const loadError = ref('')
const formError = ref('')
type CommentUpdate = { content?: string; status?: number }

const displayValue = (value: unknown) => {
  if (value === undefined || value === null || value === '') return '—'
  return String(value)
}
const displayJson = (value: unknown) => {
  if (typeof value === 'string') return value
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return displayValue(value)
  }
}
const errorMessage = (cause: unknown, fallback: string) => {
  return isApiError(cause) ? cause.message : fallback
}

const loadComment = async () => {
  const id = Number(CommentData.value?.id)
  if (!Number.isInteger(id) || id < 1) {
    loadError.value = '评论 ID 无效。'
    return
  }
  loading.value = true
  loadError.value = ''
  formError.value = ''
  try {
    const comment = await client.comments.getManaged(id)
    EditCommentData.value = {
      origin: CommonUtils.deepClone(comment),
      edit: CommonUtils.deepClone(comment),
    }
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载评论详情，请稍后重试。')
  } finally {
    loading.value = false
  }
}

const patchComment = async () => {
  const current = EditCommentData.value
  if (!current.edit.id) throw new Error('评论数据尚未加载')
  const editData = CommonUtils.deepClone(current.edit)
  const originData = CommonUtils.deepClone(current.origin)
  const editInput: CommentUpdate = {}
  const originInput: CommentUpdate = {}
  if (canUpdateContent.value) {
    editInput.content = editData.content
    originInput.content = originData.content
  }
  if (canModerate.value) {
    editInput.status = editData.status
    originInput.status = originData.status
  }
  const params = CommonUtils.removeCommonProperties(editInput, originInput)

  if (Object.keys(params).length === 0) {
    notifier.warning('请修改后再提交')
    return false
  }
  await client.comments.moderateManaged(editData.id, params)
  return true
}

const submit = async () => {
  if (saving.value) return
  saving.value = true
  formError.value = ''
  try {
    const changed = await patchComment()
    if (!changed) return
    thisDialogState.value = false
    await props.getTableData?.()
  } catch (cause) {
    formError.value = errorMessage(cause, '审核结果保存失败，请稍后重试。')
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) void loadComment()
})
</script>

<style scoped>
.text-wrap {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
</style>
