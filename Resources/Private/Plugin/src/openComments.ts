import { nodeDecorators } from '@medienreaktor/neos-studio'
import { listComments } from './api'

/**
 * Open comment counts by node, for the tree badges: decorators run
 * synchronously for every visible row, so they read this cache and a
 * repository is loaded on first sight. A document counts the comments on
 * its content as well.
 */
const counts = new Map<string, Map<string, number>>()
const loading = new Set<string>()

const RELOAD_INTERVAL = 60_000

export function openCommentCount(contentRepositoryId: string, nodeAggregateId: string): number {
  const repositoryCounts = counts.get(contentRepositoryId)
  if (repositoryCounts === undefined) {
    void load(contentRepositoryId)
    return 0
  }
  return repositoryCounts.get(nodeAggregateId) ?? 0
}

export function reloadOpenComments(): void {
  for (const contentRepositoryId of counts.keys()) {
    void load(contentRepositoryId)
  }
}

async function load(contentRepositoryId: string): Promise<void> {
  if (loading.has(contentRepositoryId)) return
  loading.add(contentRepositoryId)
  try {
    const comments = await listComments(contentRepositoryId, { status: 'OPEN' })
    const repositoryCounts = new Map<string, number>()
    const add = (id: string) => repositoryCounts.set(id, (repositoryCounts.get(id) ?? 0) + 1)
    for (const comment of comments) {
      add(comment.nodeAggregateId)
      if (
        comment.documentNodeAggregateId !== null &&
        comment.documentNodeAggregateId !== comment.nodeAggregateId
      ) {
        add(comment.documentNodeAggregateId)
      }
    }
    counts.set(contentRepositoryId, repositoryCounts)
    nodeDecorators.refresh()
  } catch (error) {
    // Keep the old counts; without the comments.read privilege there are none to show.
    if (!counts.has(contentRepositoryId)) counts.set(contentRepositoryId, new Map())
    console.warn('Beromir.BackendComments: could not load the open comments', error)
  } finally {
    loading.delete(contentRepositoryId)
  }
}

window.setInterval(reloadOpenComments, RELOAD_INTERVAL)
