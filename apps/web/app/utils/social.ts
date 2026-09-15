import type { ApiId, Comment, ReportReason, User } from '../types/api'

export interface CommentTreeNode extends Comment {
  replies: CommentTreeNode[]
}

export type SocialUser = Readonly<Omit<User, 'roles'> & { roles: readonly string[] }>

export const reportReasonOptions: Array<{ label: string, value: ReportReason }> = [
  { label: 'Spam', value: 'spam' },
  { label: 'Abuse or harassment', value: 'abuse' },
  { label: 'Illegal content', value: 'illegal_content' },
  { label: 'Wrong alcohol classification', value: 'wrong_alcohol_classification' },
  { label: 'Copyright', value: 'copyright' },
  { label: 'Other', value: 'other' }
]

export function buildCommentTree(comments: Comment[]): CommentTreeNode[] {
  const nodes = new Map<ApiId, CommentTreeNode>()
  const roots: CommentTreeNode[] = []

  for (const comment of comments) {
    nodes.set(comment.id, { ...comment, replies: [] })
  }

  for (const comment of comments) {
    const node = nodes.get(comment.id)

    if (!node) {
      continue
    }

    const parent = comment.parentId ? nodes.get(comment.parentId) : null

    if (parent && comment.depth <= 3) {
      parent.replies.push(node)
    } else {
      roots.push(node)
    }
  }

  return sortCommentNodes(roots)
}

export function canManageComment(comment: Comment, user: SocialUser | null): boolean {
  return Boolean(user && (comment.authorUsername === user.username || user.roles.includes('ROLE_ADMIN')))
}

function sortCommentNodes(nodes: CommentTreeNode[]): CommentTreeNode[] {
  return nodes
    .sort(compareComments)
    .map(node => ({
      ...node,
      replies: sortCommentNodes(node.replies)
    }))
}

function compareComments(left: Comment, right: Comment): number {
  return new Date(left.createdAt).getTime() - new Date(right.createdAt).getTime()
}
