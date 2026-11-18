import { apiFetch, queryClient } from '@medienreaktor/neos-studio'

export const QUERY_KEY = 'Beromir.BackendComments'

export type CommentStatus = 'OPEN' | 'RESOLVED'

export interface Comment {
  id: string
  nodeAggregateId: string
  documentNodeAggregateId: string | null
  documentLabel: string | null
  nodeType: string
  nodeLabel: string
  workspaceName: string
  dimensionSpacePoint: string
  propertyName: string | null
  status: CommentStatus
  text: string
  createdAt: string
  resolvedAt: string | null
}

interface CommentListing {
  data: Comment[]
  meta: { total: number }
}

export interface CommentFilter {
  documentNodeAggregateId?: string
  status?: CommentStatus
}

const base = (contentRepositoryId: string) =>
  `/cr/${encodeURIComponent(contentRepositoryId)}/comments`

export async function listComments(
  contentRepositoryId: string,
  filter: CommentFilter = {},
): Promise<Comment[]> {
  const query = new URLSearchParams()
  if (filter.documentNodeAggregateId) {
    query.set('filterByDocumentNodeAggregateId', filter.documentNodeAggregateId)
  }
  if (filter.status) query.set('filterByStatus', filter.status)
  const search = query.size > 0 ? `?${query}` : ''
  return (await apiFetch<CommentListing>(`${base(contentRepositoryId)}${search}`)).data
}

export function createComment(
  contentRepositoryId: string,
  body: {
    nodeAggregateId: string
    workspaceName: string
    dimensionSpacePoint: string
    propertyName?: string
    text: string
  },
): Promise<Comment> {
  return apiFetch<Comment>(base(contentRepositoryId), { method: 'POST', body })
}

export function setCommentStatus(
  contentRepositoryId: string,
  commentId: string,
  status: CommentStatus,
): Promise<Comment> {
  const action = status === 'RESOLVED' ? 'resolve' : 'reopen'
  return apiFetch<Comment>(`${base(contentRepositoryId)}/${commentId}/${action}`, {
    method: 'POST',
  })
}

export function deleteComment(contentRepositoryId: string, commentId: string): Promise<void> {
  return apiFetch<void>(`${base(contentRepositoryId)}/${commentId}`, { method: 'DELETE' })
}

interface NodeTypeProperties {
  properties: { name: string; label: string | null }[]
}

export async function nodeTypeProperties(
  contentRepositoryId: string,
  nodeTypeName: string,
): Promise<{ name: string; label: string }[]> {
  const nodeType = await apiFetch<NodeTypeProperties>(
    `/cr/${encodeURIComponent(contentRepositoryId)}/nodetypes/${encodeURIComponent(nodeTypeName)}`,
  )
  return nodeType.properties
    .filter((property) => !property.name.startsWith('_'))
    .map((property) => ({ name: property.name, label: property.label ?? humanize(property.name) }))
}

/** Inline-only properties have no inspector label: "tagName" reads "Tag name". */
function humanize(name: string): string {
  const words = name.replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase()
  return words.charAt(0).toUpperCase() + words.slice(1)
}

export function invalidateComments(): Promise<void> {
  return queryClient.invalidateQueries({ queryKey: [QUERY_KEY] })
}
