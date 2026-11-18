import { useState } from 'react'
import {
  Badge,
  Button,
  ConfirmDialog,
  Icon,
  LoadingState,
  Placeholder,
  Toggle,
  Tooltip,
  TooltipContent,
  TooltipTrigger,
  toast,
  useMutation,
  useQuery,
  useStudio,
} from '@medienreaktor/neos-studio'
import {
  QUERY_KEY,
  deleteComment,
  invalidateComments,
  listComments,
  setCommentStatus,
  type Comment,
  type CommentStatus,
} from './api'
import { useGoToComment } from './goToComment'
import { openCommentDialog } from './openCommentDialog'
import { t } from './i18n'
import { reloadOpenComments } from './openComments'

async function afterChange(): Promise<void> {
  reloadOpenComments()
  await invalidateComments()
}

function showError(error: unknown): void {
  toast.error(error instanceof Error ? error.message : String(error), {
    title: t('plugin.name', 'Comments'),
  })
}

const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

function timeAgo(iso: string): string {
  const seconds = (new Date(iso).getTime() - Date.now()) / 1000
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
  ]
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return relativeTime.format(Math.round(seconds / size), unit)
  }
  return relativeTime.format(0, 'minute')
}

export function CommentsPanel() {
  const studio = useStudio()
  const node = studio.inspectedNode
  const document = studio.selectedDocument
  const contentRepositoryId =
    node?.contentRepositoryId ?? document?.contentRepositoryId ?? studio.site?.contentRepositoryId ?? null

  const [pageOnly, setPageOnly] = useState(true)
  const [showResolved, setShowResolved] = useState(false)
  const goToComment = useGoToComment()

  const documentNodeAggregateId = pageOnly ? document?.nodeAggregateId : undefined
  const comments = useQuery({
    queryKey: [QUERY_KEY, contentRepositoryId, documentNodeAggregateId ?? null, showResolved],
    queryFn: () =>
      listComments(contentRepositoryId!, {
        documentNodeAggregateId,
        status: showResolved ? undefined : 'OPEN',
      }),
    enabled: contentRepositoryId !== null && (!pageOnly || document !== null),
    refetchInterval: 30_000,
  })

  if (contentRepositoryId === null) {
    return (
      <div className="bnc-panel">
        <Placeholder icon="comments" title={t('panel.noSite', 'Select a page to see its comments')} />
      </div>
    )
  }

  return (
    <div className="bnc-panel">
      <div className="bnc-toolbar">
        <FilterToggle
          icon="file-lines"
          label={t('filter.pageOnly', 'Only this page')}
          pressed={pageOnly}
          onPressedChange={setPageOnly}
        />
        <FilterToggle
          icon="circle-check"
          label={t('filter.showResolved', 'Show resolved')}
          pressed={showResolved}
          onPressedChange={setShowResolved}
        />
        <NewComment />
      </div>

      {comments.isPending && comments.fetchStatus !== 'idle' ? (
        <LoadingState />
      ) : comments.isError ? (
        <Placeholder icon="triangle-exclamation" title={t('list.error', 'The comments could not be loaded')} />
      ) : (comments.data ?? []).length === 0 ? (
        <Placeholder
          icon="comments"
          title={
            !showResolved
              ? t('list.emptyOpen', 'No open comments')
              : t('list.empty', 'No comments')
          }
        />
      ) : (
        <ol className="bnc-comments">
          {comments.data!.map((comment) => (
            <CommentItem
              key={comment.id}
              comment={comment}
              contentRepositoryId={contentRepositoryId}
              isInspected={node?.nodeAggregateId === comment.nodeAggregateId}
              onGoToNode={() => goToComment(comment, contentRepositoryId)}
            />
          ))}
        </ol>
      )}
    </div>
  )
}

function FilterToggle({
  icon,
  label,
  pressed,
  onPressedChange,
}: {
  icon: string
  label: string
  pressed: boolean
  onPressedChange: (pressed: boolean) => void
}) {
  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Toggle
            size="sm"
            variant="outline"
            aria-label={label}
            pressed={pressed}
            onPressedChange={(value) => onPressedChange(value)}
          />
        }
      >
        <Icon name={icon} size="s" />
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

function NewComment() {
  const studio = useStudio()
  const node = studio.inspectedNode ?? studio.selectedDocument
  return (
    <Button
      size="sm"
      className="bnc-toolbar__comment"
      disabled={node === null}
      title={node === null ? t('panel.selectNode', 'Select an element to comment on it.') : undefined}
      onClick={() => node !== null && void openCommentDialog(node)}
    >
      <Icon name="comment" size="s" />
      {t('action.comment', 'Comment')}
    </Button>
  )
}

function CommentItem({
  comment,
  contentRepositoryId,
  isInspected,
  onGoToNode,
}: {
  comment: Comment
  contentRepositoryId: string
  isInspected: boolean
  onGoToNode: () => void
}) {
  const changeStatus = useMutation({
    mutationFn: (status: CommentStatus) => setCommentStatus(contentRepositoryId, comment.id, status),
    onSuccess: afterChange,
    onError: showError,
  })
  const remove = useMutation({
    mutationFn: () => deleteComment(contentRepositoryId, comment.id),
    onSuccess: afterChange,
  })
  const [confirmingDelete, setConfirmingDelete] = useState(false)

  const resolved = comment.status === 'RESOLVED'

  return (
    <li
      className={[
        'bnc-comment',
        isInspected ? 'bnc-comment--inspected' : '',
        resolved ? 'bnc-comment--resolved' : '',
      ].join(' ')}
    >
      <div className="bnc-comment__head">
        <button type="button" className="bnc-comment__node" onClick={onGoToNode} title={comment.nodeType}>
          <Icon name="crosshairs" size="s" />
          <span>
            {comment.documentLabel !== null && comment.documentNodeAggregateId !== comment.nodeAggregateId
              ? `${comment.documentLabel} › ${comment.nodeLabel || comment.nodeType}`
              : comment.nodeLabel || comment.nodeType}
          </span>
        </button>
        {comment.propertyName !== null && <Badge variant="outline">{comment.propertyName}</Badge>}
        {resolved && <Badge variant="secondary">{t('status.resolved', 'Resolved')}</Badge>}
      </div>

      <p className="bnc-comment__text">{comment.text}</p>

      <div className="bnc-comment__footer">
        <time dateTime={comment.createdAt} title={new Date(comment.createdAt).toLocaleString()}>
          {timeAgo(comment.createdAt)}
        </time>
        <div className="bnc-comment__actions">
          <Button
            size="xs"
            variant="ghost"
            disabled={changeStatus.isPending}
            onClick={() => changeStatus.mutate(resolved ? 'OPEN' : 'RESOLVED')}
          >
            <Icon name={resolved ? 'rotate-left' : 'check'} size="s" />
            {resolved ? t('action.reopen', 'Reopen') : t('action.resolve', 'Resolve')}
          </Button>
          <Button
            size="xs"
            variant="ghost"
            disabled={remove.isPending}
            onClick={() => setConfirmingDelete(true)}
          >
            <Icon name="trash" size="s" />
            {t('action.delete', 'Delete')}
          </Button>
        </div>
      </div>
      <ConfirmDialog
        open={confirmingDelete}
        title={t('action.delete.confirm', 'Delete this comment?')}
        description={comment.text}
        confirmLabel={t('action.delete', 'Delete')}
        onOpenChange={setConfirmingDelete}
        onConfirm={() => remove.mutateAsync()}
      />
    </li>
  )
}
