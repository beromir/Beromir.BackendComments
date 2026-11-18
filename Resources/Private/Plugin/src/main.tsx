import { nodeActions, nodeDecorators, panels, version } from '@medienreaktor/neos-studio'
import { CommentsPanel } from './CommentsPanel'
import { t, tp } from './i18n'
import { openCommentDialog } from './openCommentDialog'
import { openCommentCount } from './openComments'
import './plugin.css'

const SUPPORTED_API_VERSION = 2

if (version === SUPPORTED_API_VERSION) {
  register()
} else {
  console.warn(
    `Beromir.BackendComments supports the Studio plugin API version ${SUPPORTED_API_VERSION}, the shell has ${String(version)} - not registering.`,
  )
}

function register() {
  panels.register({
    id: 'Beromir.BackendComments:comments',
    title: () => t('panel.title', 'Comments'),
    component: CommentsPanel,
    defaultPlacement: { kind: 'dock', region: 'secondary' },
  })

  nodeActions.register({
    id: 'Beromir.BackendComments:comment',
    label: () => t('action.comment', 'Comment'),
    icon: 'comment',
    handle: true,
    run: (node, { studio }) => {
      studio.inspectNode(node)
      return openCommentDialog(node)
    },
  })

  nodeDecorators.register({
    id: 'Beromir.BackendComments:openComments',
    decorate: (node) => {
      const count = openCommentCount(node.contentRepositoryId, node.nodeAggregateId)
      if (count === 0) return null
      return {
        overlay: {
          icon: 'comment',
          color: 'var(--color-primary-text)',
          label: tp('decorator.openComments', count, ['{0} open comment', '{0} open comments']),
          position: 'top-right',
        },
      }
    },
  })
}
