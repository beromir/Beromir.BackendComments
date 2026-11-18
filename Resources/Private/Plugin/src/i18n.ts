import { translate, translatePlural, type TranslateArgs } from '@medienreaktor/neos-studio'

const SOURCE = 'Beromir.BackendComments:Main'

export function t(id: string, fallback: string, args?: TranslateArgs): string {
  return translate(`${SOURCE}:${id}`, fallback, args)
}

export function tp(id: string, count: number, fallback: [singular: string, plural: string]): string {
  return translatePlural(`${SOURCE}:${id}`, count, fallback)
}
