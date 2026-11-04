/** Mirrors the server's SafeUpload allowlist so users learn about problems before uploading. */
export const ALLOWED_EXTENSIONS = [
  'jpg',
  'jpeg',
  'png',
  'gif',
  'webp',
  'pdf',
  'doc',
  'docx',
  'xls',
  'xlsx',
  'ppt',
  'pptx',
  'odt',
  'ods',
  'txt',
  'csv',
  'zip',
  'rar',
  '7z',
]

export const MAX_FILES = 5

export interface FileProblem {
  key: 'tooLarge' | 'badType' | 'tooMany'
  name: string
}

/** Splits candidate files into accepted ones and problems (size, type, count). */
export function checkFiles(
  current: File[],
  incoming: File[],
  maxMb: number,
): { files: File[]; problems: FileProblem[] } {
  const files = [...current]
  const problems: FileProblem[] = []
  for (const file of incoming) {
    const extension = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : ''
    if (!ALLOWED_EXTENSIONS.includes(extension)) problems.push({ key: 'badType', name: file.name })
    else if (file.size > maxMb * 1024 * 1024) problems.push({ key: 'tooLarge', name: file.name })
    else if (files.length >= MAX_FILES) problems.push({ key: 'tooMany', name: file.name })
    else files.push(file)
  }
  return { files, problems }
}

export function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
