import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as docs from './api'

export function useDocuments(params: Record<string, string | number>) {
  return useQuery({
    queryKey: ['documents', 'list', params],
    queryFn: () => docs.listDocuments(params),
    placeholderData: keepPreviousData,
  })
}

export function useDocument(id: number) {
  return useQuery({ queryKey: ['documents', 'detail', id], queryFn: () => docs.getDocument(id) })
}

function useInvalidate() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: ['documents'] }),
      queryClient.invalidateQueries({ queryKey: ['helpdesk', 'ticket'] }),
    ])
}

export function useDocumentMutations(id?: number) {
  const invalidate = useInvalidate()
  return {
    create: useMutation({
      mutationFn: ({ input, file }: { input: docs.DocumentInput; file: File | null }) =>
        docs.createDocument(input, file),
      onSuccess: invalidate,
    }),
    update: useMutation({
      mutationFn: (input: Partial<docs.DocumentInput>) => docs.updateDocument(id ?? 0, input),
      onSuccess: invalidate,
    }),
    remove: useMutation({ mutationFn: () => docs.deleteDocument(id ?? 0), onSuccess: invalidate }),
    addVersion: useMutation({
      mutationFn: ({ file, note }: { file: File; note: string }) => docs.addVersion(id ?? 0, file, note),
      onSuccess: invalidate,
    }),
    link: useMutation({
      mutationFn: (ticketId: number) => docs.linkDocument(id ?? 0, 'ticket', ticketId),
      onSuccess: invalidate,
    }),
    unlink: useMutation({
      mutationFn: (linkId: number) => docs.unlinkDocument(id ?? 0, linkId),
      onSuccess: invalidate,
    }),
  }
}
