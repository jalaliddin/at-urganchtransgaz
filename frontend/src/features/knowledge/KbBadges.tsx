import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { ArticleStatus, ArticleType } from './types'

const TYPE_COLOR: Record<ArticleType, string> = { faq: 'cobalt', guide: 'teal', solution: 'grape' }
const STATUS_COLOR: Record<ArticleStatus, string> = { draft: 'yellow', published: 'green', archived: 'gray' }

export function ArticleTypeBadge({ type }: { type: ArticleType }) {
  const { t } = useTranslation('knowledge')
  return (
    <Badge color={TYPE_COLOR[type]} variant="light" tt="none">
      {t(`type.${type}`)}
    </Badge>
  )
}

export function ArticleStatusBadge({ status }: { status: ArticleStatus }) {
  const { t } = useTranslation('knowledge')
  return (
    <Badge color={STATUS_COLOR[status]} variant="outline" tt="none">
      {t(`status.${status}`)}
    </Badge>
  )
}
