<script setup lang="ts">
import { Delete02Icon, Edit02Icon, Message02Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import type { Comment } from '../../types/api'
import type { CommentTreeNode, SocialUser } from '../../utils/social'
import { canManageComment } from '../../utils/social'
import UserAvatar from '../common/UserAvatar.vue'
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
  requestDelete: [comment: Comment]
  reply: [payload: { complete: (succeeded: boolean) => void, message: string, parentId: number }]
  update: [payload: { comment: Comment, complete: (succeeded: boolean) => void, message: string }]
}>()

const editMode = ref(false)
const replyMode = ref(false)
const editMessage = ref(props.node.message ?? '')
const replyMessage = ref('')
const depth = computed(() => props.depth ?? 0)
const canManage = computed(() => canManageComment(props.node, props.currentUser))
const isPending = computed(() => props.pendingActionId === props.node.id)
const isRemoved = computed(() => props.node.deleted || !props.node.message)
const authorPath = computed(() => isRemoved.value || !props.node.authorUsername ? null : `/users/${props.node.authorUsername}`)
const authorAvatarPath = computed(() => (
  props.node.authorAvatarPath
    ?? (props.currentUser?.username === props.node.authorUsername ? props.currentUser.avatarPath : null)
))
const authorInitial = computed(() => props.node.authorUsername?.slice(0, 1).toUpperCase() || '?')

watch(() => props.node.message, (message) => {
  editMessage.value = message ?? ''
})

function submitEdit() {
  const message = editMessage.value.trim()

  if (!message) {
    return
  }

  emit('update', {
    comment: props.node,
    message,
    complete(succeeded) {
      if (succeeded) editMode.value = false
    }
  })
}

function submitReply() {
  const message = replyMessage.value.trim()

  if (!message) {
    return
  }

  emit('reply', {
    message,
    parentId: props.node.id,
    complete(succeeded) {
      if (!succeeded) return
      replyMessage.value = ''
      replyMode.value = false
    }
  })
}
</script>

<template>
  <article :id="`comment-${node.id}`" class="grid scroll-mt-6 gap-3 border-l pl-4" :class="depth > 0 ? 'ml-2' : ''">
    <aside v-if="node.parentContext && depth === 0" class="rounded-sm border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
      <p class="font-medium text-foreground">Reply to {{ node.parentContext.authorUsername }}</p>
      <p class="mt-1 line-clamp-2">{{ node.parentContext.message || 'The parent comment is no longer visible.' }}</p>
    </aside>
    <header class="flex items-start gap-3">
      <NuxtLink v-if="authorPath" :to="authorPath" class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full border bg-muted text-xs font-semibold focus-visible:ring-2 focus-visible:ring-ring" :aria-label="`View ${node.authorUsername}'s profile`">
        <UserAvatar class="size-full" :path="authorAvatarPath" sizes="2.25rem" :username="node.authorUsername" />
      </NuxtLink>
      <div v-else class="grid size-9 shrink-0 place-items-center rounded-full border bg-muted text-xs font-semibold" aria-hidden="true">{{ authorInitial }}</div>
      <div class="min-w-0">
        <NuxtLink v-if="authorPath" :to="authorPath" class="break-all font-medium text-foreground underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring">
          {{ node.authorUsername }}
        </NuxtLink>
        <p v-else class="break-all font-medium text-muted-foreground">{{ node.authorUsername || 'Unavailable member' }}</p>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ new Date(node.createdAt).toLocaleDateString('en') }}
        </p>
      </div>
    </header>

    <form v-if="editMode" class="grid gap-3" @submit.prevent="submitEdit">
      <label class="sr-only" :for="`comment-${node.id}-edit`">Edit comment</label>
      <UiTextarea :id="`comment-${node.id}-edit`" v-model="editMessage" rows="3" maxlength="2000" />
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

    <div v-if="!isRemoved" class="flex min-h-10 flex-wrap items-center gap-1" aria-label="Comment actions">
      <UiButton v-if="currentUser && node.canReply" type="button" size="sm" variant="ghost" @click="replyMode = !replyMode">
        <HugeiconsIcon :icon="Message02Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />Reply
      </UiButton>
      <UiButton v-if="canManage" type="button" size="sm" variant="ghost" @click="editMode = !editMode">
        <HugeiconsIcon :icon="Edit02Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />Edit
      </UiButton>
      <UiButton v-if="canManage" type="button" size="sm" variant="ghost" class="text-foreground" :disabled="isPending" @click="emit('requestDelete', node)">
        <HugeiconsIcon :icon="Delete02Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />{{ isPending ? 'Deleting...' : 'Delete' }}
      </UiButton>
      <ReportAction v-if="currentUser" :login-redirect="`/recipes/${node.recipeSlug}`" :target-id="node.id" target-type="comment" />
    </div>

    <form v-if="replyMode" class="grid gap-3 rounded-md border bg-card p-3" @submit.prevent="submitReply">
      <label class="sr-only" :for="`comment-${node.id}-reply`">Reply to {{ node.authorUsername }}</label>
      <UiTextarea :id="`comment-${node.id}-reply`" v-model="replyMessage" rows="3" maxlength="2000" placeholder="Write a reply" />
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
        @request-delete="emit('requestDelete', $event)"
        @reply="emit('reply', $event)"
        @update="emit('update', $event)"
      />
    </div>
  </article>
</template>
