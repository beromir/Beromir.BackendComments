// The 2.4/2.5 plugin types aren't on npm yet; drop this once they are.
import type { ComponentProps, ComponentType } from 'react'
import '@medienreaktor/neos-studio'

declare module '@medienreaktor/neos-studio' {
  interface NodeActionDefinition {
    handle?: boolean
  }

  interface DialogControls<Result = unknown> {
    close: (result?: Result) => void
  }

  interface DialogHandle<Result = unknown> extends DialogControls<Result> {
    result: Promise<Result | undefined>
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  type DialogComponent = ComponentType<any>

  interface DialogApi {
    open<C extends DialogComponent>(
      component: C,
      props: Omit<ComponentProps<C>, 'close'>,
    ): DialogHandle<ComponentProps<C> extends { close: (result?: infer R) => void } ? R : unknown>
  }

  const dialogs: DialogApi
}
