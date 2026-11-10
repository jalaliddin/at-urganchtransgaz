import { Anchor, Code, List, Stack, Text, Title } from '@mantine/core'
import type { ReactNode } from 'react'

/**
 * A small, safe Markdown renderer for knowledge base articles: headings, lists, paragraphs, bold, italic, inline and
 * block code, links. It builds React elements only (never raw HTML), and links work only for http(s), mailto and
 * paths inside the app.
 */
const SAFE_LINK = /^(https?:\/\/|mailto:|\/(?!\/))/i
const INLINE = /(\*\*[^*\n]+\*\*|`[^`\n]+`|\[[^\]\n]+\]\([^)\s]+\)|\*[^*\n]+\*|_[^_\n]+_)/g

function inline(text: string, key: string): ReactNode[] {
  return text.split(INLINE).map((part, i) => {
    const k = `${key}-${i}`
    if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
      return (
        <Text key={k} span fw={700} inherit>
          {part.slice(2, -2)}
        </Text>
      )
    }
    if (part.startsWith('`') && part.endsWith('`') && part.length > 2)
      return <Code key={k}>{part.slice(1, -1)}</Code>
    const link = /^\[([^\]]+)\]\(([^)\s]+)\)$/.exec(part)
    if (link) {
      const [, label = '', href = ''] = link
      if (!SAFE_LINK.test(href)) return label
      const external = /^https?:\/\//i.test(href)
      return (
        <Anchor
          key={k}
          href={href}
          {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          inherit
        >
          {label}
        </Anchor>
      )
    }
    if ((part.startsWith('*') && part.endsWith('*')) || (part.startsWith('_') && part.endsWith('_'))) {
      if (part.length > 2) {
        return (
          <Text key={k} span fs="italic" inherit>
            {part.slice(1, -1)}
          </Text>
        )
      }
    }
    return part
  })
}

function withBreaks(lines: string[], key: string): ReactNode[] {
  return lines.flatMap((line, i) =>
    i === 0 ? inline(line, `${key}-${i}`) : [<br key={`${key}-br-${i}`} />, ...inline(line, `${key}-${i}`)],
  )
}

export function MarkdownText({ source }: { source: string }) {
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const blocks: ReactNode[] = []
  let i = 0
  while (i < lines.length) {
    const line = lines[i] ?? ''
    const key = `b${i}`
    if (line.trim() === '') {
      i++
      continue
    }
    if (line.trim().startsWith('```')) {
      const code: string[] = []
      i++
      while (i < lines.length && !(lines[i] ?? '').trim().startsWith('```')) code.push(lines[i++] ?? '')
      i++
      blocks.push(
        <Code key={key} block>
          {code.join('\n')}
        </Code>,
      )
      continue
    }
    const heading = /^(#{1,4})\s+(.*)$/.exec(line)
    if (heading) {
      const level = Math.min(6, (heading[1]?.length ?? 1) + 2) as 3 | 4 | 5 | 6
      blocks.push(
        <Title key={key} order={level}>
          {inline(heading[2] ?? '', key)}
        </Title>,
      )
      i++
      continue
    }
    const ordered = /^\s*\d+[.)]\s+/
    const bullet = /^\s*[-*•]\s+/
    if (ordered.test(line) || bullet.test(line)) {
      const isOrdered = ordered.test(line)
      const pattern = isOrdered ? ordered : bullet
      const items: string[] = []
      while (i < lines.length && pattern.test(lines[i] ?? ''))
        items.push((lines[i++] ?? '').replace(pattern, ''))
      blocks.push(
        <List key={key} type={isOrdered ? 'ordered' : 'unordered'} spacing={4}>
          {items.map((item, n) => (
            <List.Item key={n}>{inline(item, `${key}-${n}`)}</List.Item>
          ))}
        </List>,
      )
      continue
    }
    const paragraph: string[] = []
    while (
      i < lines.length &&
      (lines[i] ?? '').trim() !== '' &&
      !/^(#{1,4})\s/.test(lines[i] ?? '') &&
      !(lines[i] ?? '').trim().startsWith('```') &&
      !ordered.test(lines[i] ?? '') &&
      !bullet.test(lines[i] ?? '')
    ) {
      paragraph.push(lines[i++] ?? '')
    }
    blocks.push(<Text key={key}>{withBreaks(paragraph, key)}</Text>)
  }
  return <Stack gap="sm">{blocks}</Stack>
}
