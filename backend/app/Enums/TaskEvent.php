<?php

namespace App\Enums;

enum TaskEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Moved = 'moved';
    case Assigned = 'assigned';
    case Completed = 'completed';
    case Reopened = 'reopened';
    case ChecklistChanged = 'checklist_changed';
    case Commented = 'commented';
    case FileAdded = 'file_added';
    case WatchersChanged = 'watchers_changed';

    public function label(): string
    {
        return __('enums.task_event.'.$this->value);
    }
}
