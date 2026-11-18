import { useState } from 'react'
import {
  Button,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Textarea,
  useMutation,
  useQuery,
  type DialogControls,
  type Node,
} from '@medienreaktor/neos-studio'
import { QUERY_KEY, createComment, nodeTypeProperties } from './api'
import { t } from './i18n'

const WHOLE_ELEMENT = ''

/** Opened with dialogs.open(); closes with true once the comment is saved. */
export function CommentDialog({ node, close }: { node: Node } & DialogControls<boolean>) {
  const [text, setText] = useState('')
  const [propertyName, setPropertyName] = useState(WHOLE_ELEMENT)
  const properties = useQuery({
    queryKey: [QUERY_KEY, 'nodeTypeProperties', node.contentRepositoryId, node.nodeType],
    queryFn: () => nodeTypeProperties(node.contentRepositoryId, node.nodeType),
    staleTime: Infinity,
  })
  const propertyItems = [
    { value: WHOLE_ELEMENT, label: t('dialog.wholeElement', 'The whole element') },
    ...(properties.data ?? []).map((property) => ({ value: property.name, label: property.label })),
  ]
  const create = useMutation({
    mutationFn: () =>
      createComment(node.contentRepositoryId, {
        nodeAggregateId: node.nodeAggregateId,
        workspaceName: node.workspaceName,
        dimensionSpacePoint: node.dimensionSpacePoint,
        propertyName: propertyName === WHOLE_ELEMENT ? undefined : propertyName,
        text,
      }),
    onSuccess: () => close(true),
  })
  const submit = () => {
    if (text.trim() !== '' && !create.isPending) create.mutate()
  }

  return (
    <DialogContent size="sm">
      <form
        className="bnc-dialog"
        onSubmit={(event) => {
          event.preventDefault()
          submit()
        }}
      >
        <DialogHeader>
          <DialogTitle>{t('dialog.title', 'Comment')}</DialogTitle>
          <DialogDescription>{t('dialog.on', 'On {0}', [node.label || node.nodeType])}</DialogDescription>
        </DialogHeader>
        <label className="bnc-dialog__field">
          <span>{t('dialog.about', 'About')}</span>
          <Select
            value={propertyName}
            onValueChange={(value) => setPropertyName(String(value ?? WHOLE_ELEMENT))}
            items={propertyItems}
          >
            <SelectTrigger className="bnc-dialog__select">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {propertyItems.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </label>
        <Textarea
          autoFocus
          rows={4}
          value={text}
          aria-label={t('dialog.title', 'Comment')}
          placeholder={t('dialog.placeholder', 'Add a comment…')}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={(event) => {
            // Shift+Enter keeps a line break; Enter while an IME composes confirms the composition.
            if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
              event.preventDefault()
              submit()
            }
          }}
        />
        {create.isError && (
          <p className="bnc-dialog__error" role="alert">
            {create.error instanceof Error ? create.error.message : String(create.error)}
          </p>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => close()}>
            {t('dialog.cancel', 'Cancel')}
          </Button>
          <Button type="submit" disabled={text.trim() === '' || create.isPending}>
            {t('action.comment', 'Comment')}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  )
}
