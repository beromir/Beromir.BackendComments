import { useEffect, useRef } from 'react'
import { useStudio, type NodeAddress } from '@medienreaktor/neos-studio'
import type { Comment } from './api'

/**
 * Shows the commented node: its page first, then the node in it. Studio's
 * navigateToNode selects the given node as the page, so it only takes the
 * document; inspecting right after it would cancel the navigation, so the
 * node is inspected once the page is selected.
 */
export function useGoToComment(): (comment: Comment, contentRepositoryId: string) => void {
  const studio = useStudio()
  const pending = useRef<{ documentNodeAggregateId: string; address: NodeAddress } | null>(null)
  const selectedDocumentId = studio.selectedDocument?.nodeAggregateId ?? null

  useEffect(() => {
    const target = pending.current
    if (target !== null && target.documentNodeAggregateId === selectedDocumentId) {
      pending.current = null
      studio.inspectAddress(target.address)
    }
  }, [selectedDocumentId, studio])

  return (comment, contentRepositoryId) => {
    const address = (nodeAggregateId: string): NodeAddress => ({
      contentRepositoryId,
      workspaceName: studio.workspaceName ?? comment.workspaceName,
      dimensionSpacePoint: comment.dimensionSpacePoint,
      nodeAggregateId,
    })
    const documentId = comment.documentNodeAggregateId
    if (documentId === comment.nodeAggregateId) {
      studio.navigateToNode(address(documentId))
    } else if (documentId === null || documentId === selectedDocumentId) {
      studio.inspectAddress(address(comment.nodeAggregateId))
    } else {
      pending.current = { documentNodeAggregateId: documentId, address: address(comment.nodeAggregateId) }
      studio.navigateToNode(address(documentId))
    }
  }
}
