import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '@/test/utils'
import { MarkdownText } from './MarkdownText'

describe('MarkdownText', () => {
  it('renders headings, lists, emphasis, code and safe links only', () => {
    const source = [
      '## Qadamlar',
      "1. Printerni **o'chiring**",
      "2. `ipconfig /all` buyrug'ini bajaring",
      '',
      "- [Yo'riqnoma](https://example.uz/doc) va [ichki](/kb/2)",
      '- [xavfli](javascript:alert(1))',
      '',
      '<script>alert("x")</script> oddiy matn',
      '```',
      'ping 10.0.0.1',
      '```',
    ].join('\n')
    const { container } = renderWithProviders(<MarkdownText source={source} />)

    expect(screen.getByRole('heading', { name: 'Qadamlar' })).toBeInTheDocument()
    expect(screen.getAllByRole('listitem')).toHaveLength(4)
    expect(screen.getByText("o'chiring").tagName).toBe('SPAN')
    expect(screen.getByText('ipconfig /all').tagName).toBe('CODE')
    expect(screen.getByRole('link', { name: "Yo'riqnoma" })).toHaveAttribute('rel', 'noopener noreferrer')
    expect(screen.getByRole('link', { name: 'ichki' })).toHaveAttribute('href', '/kb/2')
    expect(screen.queryByRole('link', { name: 'xavfli' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('listitem')[3]).toHaveTextContent('xavfli)')
    expect(container.querySelector('script')).toBeNull()
    expect(screen.getByText(/<script>alert\("x"\)<\/script> oddiy matn/)).toBeInTheDocument()
    expect(screen.getByText('ping 10.0.0.1')).toBeInTheDocument()
  })
})
