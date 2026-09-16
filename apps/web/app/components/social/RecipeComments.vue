<script setup lang="ts">
import type { Comment, PaginatedList } from '../../types/api'
import type { PaginationState } from '../../utils/pagination'
import { paginationState } from '../../utils/pagination'
import { buildCommentTree } from '../../utils/social'
import { toFormErrors } from '../../utils/api-errors'
import FormAlert from '../common/FormAlert.vue'
import DestructiveConfirm from '../common/DestructiveConfirm.vue'
import PaginationNav from '../common/PaginationNav.vue'
import CommentTreeItem from './CommentTreeItem.vue'
import UiButton from '../ui/button/Button.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'

const props = withDefaults(defineProps<{
  comments: Comment[]
  loadFailed?: boolean
  loading?: boolean
  nextTo: Record<string, unknown>
  pagination: PaginationState
  previousTo: Record<string, unknown>
  recipeSlug: string
}>(), {
  loadFailed: false,
  loading: false
})

const emit = defineEmits<{
  retry: []
  resynced: [payload: { comment: Comment, page: PaginatedList<Comment> }]
}>()

interface CommentSubmission {
  complete?: (succeeded: boolean) => void
  message: string
  parentId?: number | null
}

const api = useApi()
const auth = useAuth()
const notifications = useNotifications()
const comments = ref<Comment[]>([...props.comments])
const currentPagination = ref(props.pagination)
const message = ref('')
const pending = ref(false)
const pendingActionId = ref<number | null>(null)
const formError = ref<string | null>(null)
const deleteTarget = ref<Comment | null>(null)
const deleteDialogOpen = ref(false)
const deleteError = ref<string | null>(null)

const commentTree = computed(() => buildCommentTree(comments.value))

watch(() => props.comments, (nextComments) => {
  comments.value = [...nextComments]
})

watch(() => props.pagination, (nextPagination) => {
  currentPagination.value = nextPagination
})

async function createComment(payload: CommentSubmission) {
  pending.value = true
  pendingActionId.value = payload.parentId ?? null
  formError.value = null
  let succeeded = false

  try {
    const createdComment = await api.comments.create(props.recipeSlug, {
      message: payload.message,
      parentId: payload.parentId
    })
    succeeded = true
    if (!payload.parentId) message.value = ''
    try {
      const page = await api.comments.list(props.recipeSlug, { around: createdComment.id })
      comments.value = [...page.items]
      currentPagination.value = paginationState({
        currentPage: page.page,
        itemsOnPage: page.items.length,
        pageSize: page.pageSize,
        totalItems: page.totalItems,
        totalPages: page.totalPages
      })
      emit('resynced', { comment: createdComment, page })
    } catch {
      formError.value = 'Your comment was posted, but the comment list could not be refreshed. Reload the page to find it.'
    }
    notifications.success(
      payload.parentId ? `comment-reply:${payload.parentId}` : `comment-create:${props.recipeSlug}`,
      payload.parentId ? 'Reply posted.' : 'Comment posted.'
    )
  } catch (error: unknown) {
    formError.value = socialErrorMessage(error, 'Comment could not be posted.')
  } finally {
    payload.complete?.(succeeded)
    pending.value = false
    pendingActionId.value = null
  }
}

async function updateComment(payload: { comment: Comment, complete?: (succeeded: boolean) => void, message: string }) {
  pendingActionId.value = payload.comment.id
  formError.value = null
  let succeeded = false

  try {
    const updatedComment = await api.comments.update(payload.comment.id, { message: payload.message })
    comments.value = comments.value.map(comment => comment.id === updatedComment.id ? updatedComment : comment)
    succeeded = true
    notifications.success(`comment-update:${payload.comment.id}`, 'Comment updated.')
  } catch (error: unknown) {
    formError.value = socialErrorMessage(error, 'Comment could not be updated.')
  } finally {
    payload.complete?.(succeeded)
    pendingActionId.value = null
  }
}

async function deleteComment(comment: Comment) {
  pendingActionId.value = comment.id
  formError.value = null
  deleteError.value = null

  try {
    const deletedComment = await api.comments.delete(comment.id)
    comments.value = comments.value.map(currentComment => currentComment.id === deletedComment.id ? deletedComment : currentComment)
    notifications.success(`comment-delete:${comment.id}`, 'Comment deleted.')
    deleteDialogOpen.value = false
    deleteTarget.value = null
  } catch (error: unknown) {
    deleteError.value = socialErrorMessage(error, 'Comment could not be deleted.')
  } finally {
    pendingActionId.value = null
  }
}

function requestDelete(comment: Comment) {
  deleteTarget.value = comment
  deleteError.value = null
  deleteDialogOpen.value = true
}

function submitRootComment() {
  const trimmedMessage = message.value.trim()

  if (!trimmedMessage) {
    return
  }

  return createComment({ message: trimmedMessage })
}

function socialErrorMessage(error: unknown, fallback: string): string {
  return toFormErrors(error).message ?? fallback
}
</script>

<template>
  <section>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="section-heading">
          Comments
        </h2>
        <p class="section-description">
          Share a useful note or reply to another member.
        </p>
      </div>
      <p class="text-sm text-muted-foreground">
        {{ currentPagination.totalItems }} comment{{ currentPagination.totalItems === 1 ? '' : 's' }}
      </p>
    </div>

    <div class="mt-5 grid gap-3">
      <FormAlert v-if="formError" :message="formError" tone="error" />
    </div>

    <form v-if="auth.isAuthenticated.value" class="mt-5 grid gap-3 rounded-md border bg-background p-4" @submit.prevent="submitRootComment">
      <label class="field-label" for="new-comment">Add a comment</label>
      <UiTextarea id="new-comment" v-model="message" rows="4" maxlength="2000" placeholder="Write a public comment" />
      <div class="flex justify-end">
        <UiButton type="submit" :disabled="pending || !message.trim()">
          {{ pending ? 'Posting...' : 'Post comment' }}
        </UiButton>
      </div>
    </form>

    <div v-else class="mt-5 rounded-md border bg-background p-4">
      <p class="text-muted-foreground">
        Log in to comment or reply.
      </p>
      <UiButton as-child class="mt-3" variant="outline">
        <NuxtLink :to="`/login?redirect=/recipes/${recipeSlug}`">
          Log in
        </NuxtLink>
      </UiButton>
    </div>

    <div v-if="loadFailed" class="mt-5 rounded-md border border-dashed p-5" role="alert">
      <p class="font-medium">Comments could not be loaded.</p>
      <p class="mt-1 text-sm text-muted-foreground">The conversation may still be available. Try again without leaving the recipe.</p>
      <UiButton class="mt-3" type="button" size="sm" variant="outline" :disabled="loading" @click="emit('retry')">
        {{ loading ? 'Retrying...' : 'Retry comments' }}
      </UiButton>
    </div>

    <div v-else-if="commentTree.length > 0" class="mt-5 grid gap-3">
      <CommentTreeItem
        v-for="comment in commentTree"
        :key="comment.id"
        :current-user="auth.currentUser.value"
        :node="comment"
        :pending-action-id="pendingActionId"
        @request-delete="requestDelete"
        @reply="createComment"
        @update="updateComment"
      />
    </div>

    <p v-else-if="!loading" class="mt-5 text-muted-foreground">
      No public comments yet.
    </p>

    <p v-else class="mt-5 text-sm text-muted-foreground" role="status">Loading comments...</p>

    <PaginationNav
      v-if="!loadFailed && !loading"
      aria-label="Comment pagination"
      :next-to="nextTo"
      :pagination="currentPagination"
      :previous-to="previousTo"
    />

    <DestructiveConfirm
      v-model:open="deleteDialogOpen"
      confirm-label="Delete comment"
      :description="`The comment by ${deleteTarget?.authorUsername ?? 'this member'} will be permanently removed from the conversation.`"
      :error="deleteError"
      :pending="deleteTarget ? pendingActionId === deleteTarget.id : false"
      title="Delete this comment?"
      @confirm="deleteTarget && deleteComment(deleteTarget)"
    />
  </section>
</template>
