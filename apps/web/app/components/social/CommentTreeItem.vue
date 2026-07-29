<script setup lang="ts">
import type { Comment } from '../../types/api'
import type { CommentTreeNode, SocialUser } from '../../utils/social'
import { canManageComment } from '../../utils/social'
import UiButton from '../ui/button/Button.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'
import ReportAction from './ReportAction.vue'

defineOptions({
  name: 'CommentTreeItem'
})

const props = defineProps<{
  currentUser: SocialUser | null
  depth?: number
  node: CommentTreeNode
  pendingActionId: number | null
}>()

const emit = defineEmits<{
  delete: [comment: Comment]
  reply: [payload: { message: string, parentId: number }]
  update: [payload: { comment: Comment, message: string }]
}>()

const editMode = ref(false)
const replyMode = ref(false)
const editMessage = ref(props.node.message ?? '')
const replyMessage = ref('')

const depth = computed(() => props.depth ?? 0)
const canManage = computed(() => canManageComment(props.node, props.currentUser))
const isPending = computed(() => props.pendingActionId === props.node.id)
const isRemoved = computed(() => props.node.deleted || !props.node.message)

watch(() => props.node.message, (message) => {
  editMessage.value = message ?? ''
})

function submitEdit() {
  const message = editMessage.value.trim()

  if (!message) {
    return
  }

  emit('update', { comment: props.node, message })
  editMode.value = false
}

function submitReply() {
  const message = replyMessage.value.trim()

  if (!message) {
    return
  }

  emit('reply', { message, parentId: props.node.id })
  replyMessage.value = ''
  replyMode.value = false
}
</script>

<template>
  <article class="grid gap-3 rounded-lg border border-border bg-background p-4" :class="depth > 0 ? 'border-l-4 border-l-primary/40' : ''">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="m-0 font-black text-foreground">
          {{ node.authorUsername }}
        </p>
        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">
          {{ new Date(node.createdAt).toLocaleDateString('en') }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <UiButton v-if="currentUser && !isRemoved" type="button" size="sm" variant="outline" @click="replyMode = !replyMode">
          Reply
        </UiButton>
        <UiButton v-if="canManage && !isRemoved" type="button" size="sm" variant="outline" @click="editMode = !editMode">
          Edit
        </UiButton>
        <UiButton v-if="canManage && !isRemoved" type="button" size="sm" variant="outline" :disabled="isPending" @click="emit('delete', node)">
          {{ isPending ? 'Deleting...' : 'Delete' }}
        </UiButton>
      </div>
    </header>

    <form v-if="editMode" class="grid gap-3" @submit.prevent="submitEdit">
      <UiTextarea v-model="editMessage" rows="3" />
      <div class="flex flex-wrap gap-2">
        <UiButton type="submit" size="sm" :disabled="isPending || !editMessage.trim()">
          {{ isPending ? 'Saving...' : 'Save edit' }}
        </UiButton>
        <UiButton type="button" size="sm" variant="outline" @click="editMode = false">
          Cancel
        </UiButton>
      </div>
    </form>

    <p v-else class="m-0 text-muted-foreground">
      {{ node.message || 'This comment is no longer visible.' }}
    </p>

    <ReportAction
      v-if="currentUser && !isRemoved"
      compact
      :login-redirect="`/recipes/${node.recipeSlug}`"
      :target-id="node.id"
      target-type="comment"
    />

    <form v-if="replyMode" class="grid gap-3 rounded-lg border border-border bg-card p-3" @submit.prevent="submitReply">
      <UiTextarea v-model="replyMessage" rows="3" placeholder="Write a reply" />
      <div class="flex flex-wrap gap-2">
        <UiButton type="submit" size="sm" :disabled="isPending || !replyMessage.trim()">
          {{ isPending ? 'Posting...' : 'Post reply' }}
        </UiButton>
        <UiButton type="button" size="sm" variant="outline" @click="replyMode = false">
          Cancel
        </UiButton>
      </div>
    </form>

    <div v-if="node.replies.length > 0" class="grid gap-3 pl-3 md:pl-5">
      <CommentTreeItem
        v-for="reply in node.replies"
        :key="reply.id"
        :current-user="currentUser"
        :depth="depth + 1"
        :node="reply"
        :pending-action-id="pendingActionId"
        @delete="emit('delete', $event)"
        @reply="emit('reply', $event)"
        @update="emit('update', $event)"
      />
    </div>
  </article>
</template>
