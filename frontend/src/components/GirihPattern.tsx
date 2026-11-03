import { useId } from 'react'

/**
 * Khiva-style girih: eight-point khatam stars on a cobalt ground, joined by
 * diagonal strapwork. Purely decorative (aria-hidden).
 */
export function GirihPattern({ className }: { className?: string }) {
  const id = `girih-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`
  return (
    <svg className={className} aria-hidden="true" focusable="false" width="100%" height="100%">
      <defs>
        <pattern id={id} width="96" height="96" patternUnits="userSpaceOnUse">
          <rect width="96" height="96" fill="#1D4E89" />
          <g fill="none" strokeLinejoin="round">
            <g stroke="#4CBCC7" strokeOpacity="0.55" strokeWidth="1.5">
              <rect x="33" y="33" width="30" height="30" />
              <rect x="33" y="33" width="30" height="30" transform="rotate(45 48 48)" />
            </g>
            <g stroke="#A5DDE3" strokeOpacity="0.22" strokeWidth="1.25">
              <path d="M0 0 L27 27 M96 0 L69 27 M0 96 L27 69 M96 96 L69 69" />
              <path d="M48 0 L48 12 M48 96 L48 84 M0 48 L12 48 M96 48 L84 48" />
            </g>
            <circle cx="48" cy="48" r="5" fill="#0E8C99" fillOpacity="0.5" />
          </g>
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill={`url(#${id})`} />
    </svg>
  )
}
