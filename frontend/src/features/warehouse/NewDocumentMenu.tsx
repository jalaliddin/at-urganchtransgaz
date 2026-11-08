import { Button, Menu } from '@mantine/core'
import { IconChevronDown, IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useMe } from '@/features/auth/queries'
import { hasPermission } from '@/hooks/usePermissions'
import { StockDocumentDrawer } from './StockDocumentDrawer'
import { DOCUMENT_PERMISSIONS, DOCUMENT_TYPES, type StockDocumentType } from './types'

/** "New document" menu with the types the user may post; opens the document drawer. */
export function NewDocumentMenu() {
  const { t } = useTranslation('warehouse')
  const navigate = useNavigate()
  const { data: me } = useMe()
  const [type, setType] = useState<StockDocumentType | null>(null)
  const allowed = DOCUMENT_TYPES.filter((d) => me && hasPermission(me, DOCUMENT_PERMISSIONS[d]))
  if (allowed.length === 0) return null

  return (
    <>
      <Menu position="bottom-end" withinPortal>
        <Menu.Target>
          <Button leftSection={<IconPlus size={16} />} rightSection={<IconChevronDown size={14} />}>
            {t('actions.newDocument')}
          </Button>
        </Menu.Target>
        <Menu.Dropdown>
          {allowed.map((d) => (
            <Menu.Item key={d} onClick={() => setType(d)}>
              {t(`new.${d}`)}
            </Menu.Item>
          ))}
        </Menu.Dropdown>
      </Menu>
      {type && (
        <StockDocumentDrawer
          type={type}
          onClose={(doc) => {
            setType(null)
            if (doc) navigate(`/warehouse/documents/${doc.id}`)
          }}
        />
      )}
    </>
  )
}
