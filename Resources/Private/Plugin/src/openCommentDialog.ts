import { dialogs, type Node } from '@medienreaktor/neos-studio'
import { invalidateComments } from './api'
import { CommentDialog } from './CommentDialog'
import { reloadOpenComments } from './openComments'

export async function openCommentDialog(node: Node): Promise<void> {
  const saved = await dialogs.open(CommentDialog, { node }).result
  if (saved) {
    reloadOpenComments()
    await invalidateComments()
  }
}
