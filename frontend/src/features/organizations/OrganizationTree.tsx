import { ActionIcon, Anchor, Badge, Box, Group, Paper, Text } from '@mantine/core'
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import type { OrganizationTreeNode } from './types'

function Node({
  node,
  level,
  expanded,
  toggle,
}: {
  node: OrganizationTreeNode
  level: number
  expanded: Set<number>
  toggle: (id: number) => void
}) {
  const { t } = useTranslation(['organizations', 'common'])
  const open = expanded.has(node.id)
  const hasChildren = node.children.length > 0

  return (
    <li
      role="treeitem"
      aria-expanded={hasChildren ? open : undefined}
      aria-level={level + 1}
      aria-selected={false}
    >
      <Group
        gap={6}
        wrap="nowrap"
        py={6}
        pl={level * 24}
        pr="sm"
        style={{ borderBottom: '1px solid var(--mantine-color-default-border)' }}
      >
        {hasChildren ? (
          <ActionIcon
            variant="subtle"
            color="gray"
            size="sm"
            onClick={() => toggle(node.id)}
            aria-label={`${node.name}: ${open ? '−' : '+'}`}
          >
            {open ? <IconChevronDown size={16} /> : <IconChevronRight size={16} />}
          </ActionIcon>
        ) : (
          <Box w={22} />
        )}
        <Anchor
          component={Link}
          to={`/organizations/${node.id}`}
          fw={level === 0 ? 600 : 500}
          size="sm"
          truncate
        >
          {node.name}
        </Anchor>
        <Text size="xs" c="dimmed" style={{ whiteSpace: 'nowrap' }}>
          {t(`types.${node.type}`)}
          {hasChildren ? ` · ${node.children.length}` : ''}
        </Text>
        {!node.is_active && (
          <Badge size="xs" color="red" variant="light" tt="none">
            {t('common:labels.inactive')}
          </Badge>
        )}
      </Group>
      {hasChildren && open && (
        <ul role="group" style={{ listStyle: 'none', margin: 0, padding: 0 }}>
          {node.children.map((child) => (
            <Node key={child.id} node={child} level={level + 1} expanded={expanded} toggle={toggle} />
          ))}
        </ul>
      )}
    </li>
  )
}

/** Accessible organization hierarchy; roots start expanded. */
export function OrganizationTree({ nodes }: { nodes: OrganizationTreeNode[] }) {
  const [expanded, setExpanded] = useState(() => new Set(nodes.map((n) => n.id)))
  const toggle = (id: number) =>
    setExpanded((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })

  return (
    <Paper withBorder>
      <ul role="tree" style={{ listStyle: 'none', margin: 0, padding: 0 }}>
        {nodes.map((node) => (
          <Node key={node.id} node={node} level={0} expanded={expanded} toggle={toggle} />
        ))}
      </ul>
    </Paper>
  )
}
