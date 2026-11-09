import { Paper } from '@mantine/core'
import { useNavigate, useParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useTask } from './queries'
import { TaskDetail } from './TaskDetail'

/** A task on its own page (links from notifications, lists and tickets). */
export function TaskPage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const task = useTask(id)
  if (task.error instanceof ApiError && task.error.status === 404) return <NotFoundPage />
  if (task.error) return <ErrorState error={task.error} onRetry={() => void task.refetch()} />
  if (!task.data) return null
  return (
    <Paper withBorder p="lg" maw={860}>
      <TaskDetail task={task.data} onDeleted={() => navigate(`/tasks/boards/${task.data?.board.id ?? ''}`)} />
    </Paper>
  )
}
