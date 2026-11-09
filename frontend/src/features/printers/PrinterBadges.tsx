import { Badge, ColorSwatch, Group, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { CartridgeColor } from './types'

const SWATCH: Record<CartridgeColor, string> = {
  black: '#1f2328',
  cyan: '#00a3d9',
  magenta: '#d4148a',
  yellow: '#f2c200',
  tricolor: 'linear-gradient(90deg, #00a3d9, #d4148a, #f2c200)',
  other: '#8a8f98',
}

/** Cartridge colour with its name (never colour alone). */
export function CartridgeColorLabel({ color }: { color: CartridgeColor }) {
  const { t } = useTranslation('printers')
  return (
    <Group gap={6} wrap="nowrap">
      <ColorSwatch color={SWATCH[color]} size={12} withShadow={false} style={{ background: SWATCH[color] }} />
      <Text size="sm">{t(`color.${color}`)}</Text>
    </Group>
  )
}

export function CancelledBadge() {
  const { t } = useTranslation('printers')
  return (
    <Badge color="gray" variant="outline" tt="none">
      {t('replacements.cancelled')}
    </Badge>
  )
}
