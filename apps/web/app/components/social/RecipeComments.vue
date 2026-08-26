<script setup lang="ts">
import { ApiRequestError } from '../../services/api-client'
import type { Comment } from '../../types/api'
import { buildCommentTree } from '../../utils/social'
import FormAlert from '../common/FormAlert.vue'
import CommentTreeItem from './CommentTreeItem.vue'
import UiButton from '../ui/button/Button.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'

const props = defineProps<{
  comments: Comment[]
  recipeSlug: string
}>()

const api = useApi()
const auth = useAuth()
const comments = ref<Comment[]>([...props.comments])
const message = ref('')
const pending = ref(false)
const pendingActionId = ref<number | null>(null)
const formError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const commentTree = computed(() => buildCommentTree(comments.value))

watch(() => props.comments, (nextComments) => {
  comments.value = [...nextComments]
})

async function createComment(payload: { message: string, parentId?: number | null }) {
  pending.value = true
  pendingActionId.value = payload.parentId ?? null
  formError.value = null
  successMessage.value = null

  try {
    const createdComment = await api.comments.create(props.recipeSlug, payload)
    comments.value = [...comments.value, createdComment]
    message.value = ''
    successMessage.value = payload.parentId ? 'Reply posted.' : 'Comment posted.'
  } catch (error: unknown) {
    formError.value = socialErrorMessage(error, 'Comment could not be posted.')
  } finally {
    pending.value = false
    pendingActionId.value = null
  }
}

async function updateComment(payload: { comment: Comment, message: string }) {
  pendingActionId.value = payload.comment.id
  formError.value = null
  successMessage.value = null

  try {
    const updatedComment = await api.comments.update(payload.comment.id, { message: payload.message })
    comments.value = comments.value.map(comment => comment.id === updatedComment.id ? updatedComment : comment)
    successMessage.value = 'Comment updated.'
  } catch (error: unknown) {
    formError.value = socialErrorMessage(error, 'Comment could not be updated.')
  } finally {
    pendingActionId.value = null
  }
}

async function deleteComment(comment: Comment) {
  pendingActionId.value = comment.id
  formError.value = null
  successMessage.value = null

  try {
    const deletedComment = await api.comments.delete(comment.id)
    comments.value = comments.value.map(currentComment => currentComment.id === deletedComment.id ? deletedComment : currentComment)
    successMessage.value = 'Comment deleted.'
  } catch (error: unknown) {
    formError.value = socialErrorMessage(error, 'Comment could not be deleted.')
  } finally {
    pendingActionId.value = null
  }
}

function submitRootComment() {
  const trimmedMessage = message.value.trim()

  if (!trimmedMessage) {
    return
  }

  return createComment({ message: trimmedMessage })
}

function socialErrorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiRequestError ? error.message : fallback
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
        {{ comments.length }} comment{{ comments.length === 1 ? '' : 's' }}
      </p>
    </div>

    <div class="mt-5 grid gap-3">
      <FormAlert v-if="formError" :message="formError" tone="error" />
      <FormAlert v-if="successMessage" :message="successMessage" tone="success" />
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

    <div v-if="commentTree.length > 0" class="mt-5 grid gap-3">
      <CommentTreeItem
        v-for="comment in commentTree"
        :key="comment.id"
        :current-user="auth.currentUser.value"
        :node="comment"
        :pending-action-id="pendingActionId"
        @delete="deleteComment"
        @reply="createComment"
        @update="updateComment"
      />
    </div>

    <p v-else class="mt-5 text-muted-foreground">
      No public comments yet.
    </p>
  </section>
</template>
