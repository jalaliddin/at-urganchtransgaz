<?php

namespace Tests\Feature\Tasks;

use App\Enums\RecurrenceFrequency;
use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskRecurrence;
use App\Models\User;
use App\Notifications\Tasks\TaskNotification;
use App\Services\Tasks\RecurrenceSchedule;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTasks;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use CreatesTasks, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $it;

    private User $staff;

    private TaskBoard $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 07:55', 'Asia/Tashkent'));
        $this->org = Organization::factory()->create();
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org);
        $this->board = $this->boardIn($this->org);
    }

    /** @return array<string, array{string, int, int|null, int|null, string, string, string}> */
    public static function schedules(): array
    {
        // frequency, interval, weekday, month day, start, first occurrence, the one after
        return [
            'every 2 days' => ['daily', 2, null, null, '2026-11-11', '2026-11-11', '2026-11-13'],
            'mondays, every 2 weeks' => ['weekly', 2, 1, null, '2026-11-11', '2026-11-16', '2026-11-30'],
            'wednesday today' => ['weekly', 1, 3, null, '2026-11-11', '2026-11-11', '2026-11-18'],
            'monthly on the 5th' => ['monthly', 1, null, 5, '2026-11-11', '2026-12-05', '2027-01-05'],
            'quarterly on the 20th' => ['monthly', 3, null, 20, '2026-11-11', '2026-11-20', '2027-02-20'],
        ];
    }

    #[DataProvider('schedules')]
    public function test_schedule_maths(string $frequency, int $interval, ?int $weekday, ?int $monthDay, string $start, string $first, string $after): void
    {
        $rule = new TaskRecurrence(['frequency' => $frequency, 'interval' => $interval, 'weekday' => $weekday, 'month_day' => $monthDay]);
        $schedule = new RecurrenceSchedule;
        $firstDate = $schedule->first($rule, CarbonImmutable::parse($start));
        $this->assertSame($first, $firstDate->toDateString());
        $this->assertSame($after, $schedule->advance($rule, $firstDate)->toDateString());
        $this->assertSame(RecurrenceFrequency::from($frequency), $rule->frequency);
    }

    public function test_recurrences_are_validated_and_scoped(): void
    {
        $this->actingAs($this->it);
        $url = "/api/v1/task-boards/{$this->board->id}/recurrences";
        $id = $this->postJson($url, ['title' => 'Zaxira nusxani tekshirish', 'frequency' => 'weekly', 'weekday' => 1, 'assignee_id' => $this->staff->id, 'due_in_days' => 1])
            ->assertCreated()->assertJsonPath('data.next_run_on', '2026-11-16')->assertJsonPath('data.column.name', 'Backlog')->json('data.id');
        $this->postJson($url, ['title' => 'X', 'frequency' => 'weekly'])->assertStatus(422)->assertJsonValidationErrors('weekday');
        $this->postJson($url, ['title' => 'X', 'frequency' => 'monthly', 'month_day' => 31])->assertStatus(422)->assertJsonValidationErrors('month_day');
        $this->postJson($url, ['title' => 'X', 'frequency' => 'daily', 'column_id' => $this->column($this->board, 'Bajarildi')->id])
            ->assertStatus(422)->assertJsonValidationErrors('column_id');
        $this->putJson("/api/v1/task-recurrences/{$id}", ['weekday' => 3])->assertOk()->assertJsonPath('data.next_run_on', '2026-11-11');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');

        $foreign = $this->boardIn(Organization::factory()->create());
        $this->postJson("/api/v1/task-boards/{$foreign->id}/recurrences", ['title' => 'X', 'frequency' => 'daily'])->assertNotFound();
        $this->actingAs($this->staff)->postJson($url, ['title' => 'X', 'frequency' => 'daily'])->assertForbidden();
        $this->actingAs($this->it)->deleteJson("/api/v1/task-recurrences/{$id}")->assertOk();
    }

    public function test_the_daily_run_creates_one_task_per_occurrence_and_moves_on(): void
    {
        Notification::fake();
        $this->actingAs($this->it)->postJson("/api/v1/task-boards/{$this->board->id}/recurrences",
            ['title' => 'Serverlarni tekshirish', 'frequency' => 'daily', 'assignee_id' => $this->staff->id, 'due_in_days' => 2, 'priority' => 'high'])->assertCreated();
        $this->postJson("/api/v1/task-boards/{$this->board->id}/recurrences", ['title' => 'Yillik', 'frequency' => 'monthly', 'month_day' => 20])->assertCreated();

        $this->artisan('tasks:daily')->assertSuccessful();
        $this->artisan('tasks:daily')->assertSuccessful();
        $task = Task::query()->where('title', 'Serverlarni tekshirish')->sole();
        $this->assertSame(['2026-11-11', '2026-11-13', 'high', $this->staff->id], [$task->occurrence_date?->toDateString(), $task->due_date?->toDateString(), $task->priority->value, $task->assignee_id]);
        $this->assertSame('2026-11-12', TaskRecurrence::query()->where('title', 'Serverlarni tekshirish')->value('next_run_on')?->toDateString());
        $this->assertSame(0, Task::query()->where('title', 'Yillik')->count());
        Notification::assertSentTo($this->staff, TaskNotification::class, fn ($n) => $n->event === 'assigned');

        // Three missed days: only the latest occurrence is created.
        $this->travelTo(CarbonImmutable::parse('2026-11-15 07:55', 'Asia/Tashkent'));
        $this->artisan('tasks:daily')->assertSuccessful();
        $this->assertSame(['2026-11-11', '2026-11-15'], Task::query()->where('title', 'Serverlarni tekshirish')->orderBy('id')->get()
            ->map(fn (Task $t) => $t->occurrence_date?->toDateString())->all());

        $this->board->update(['is_archived' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:55', 'Asia/Tashkent'));
        $this->artisan('tasks:daily')->assertSuccessful();
        $this->assertSame(2, Task::query()->where('title', 'Serverlarni tekshirish')->count());
    }

    public function test_due_and_overdue_reminders_are_sent_once_to_the_responsible_person_and_watchers(): void
    {
        Notification::fake();
        $watcher = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org);
        $soon = $this->taskOn($this->board, $this->it, ['title' => 'Ertaga', 'assignee_id' => $this->staff->id, 'due_date' => '2026-11-12', 'watcher_ids' => [$watcher->id]]);
        $late = $this->taskOn($this->board, $this->it, ['title' => 'Kechikkan', 'assignee_id' => $this->staff->id, 'due_date' => '2026-11-09']);
        $done = $this->taskOn($this->board, $this->it, ['title' => 'Tugagan', 'assignee_id' => $this->staff->id, 'due_date' => '2026-11-09',
            'column_id' => $this->column($this->board, 'Bajarildi')->id]);

        $this->artisan('tasks:daily')->assertSuccessful();
        $this->artisan('tasks:daily')->assertSuccessful();

        Notification::assertSentToTimes($this->staff, TaskNotification::class, 5);
        Notification::assertSentTo($this->staff, TaskNotification::class, fn ($n) => $n->event === 'due_soon' && $n->taskId === $soon->id);
        Notification::assertSentTo($this->staff, TaskNotification::class, fn ($n) => $n->event === 'overdue' && $n->taskId === $late->id);
        Notification::assertSentTo($watcher, TaskNotification::class, fn ($n) => $n->event === 'due_soon');
        Notification::assertNotSentTo($this->staff, TaskNotification::class, fn ($n) => $n->taskId === $done->id && $n->event !== 'assigned');

        // A comment reaches the responsible person and watchers, not its author.
        $this->actingAs($this->staff)->postJson("/api/v1/tasks/{$soon->id}/comments", ['body' => 'Bajarilmoqda'])->assertCreated();
        Notification::assertSentTo($watcher, TaskNotification::class, fn ($n) => $n->event === 'commented');
        Notification::assertNotSentTo($this->staff, TaskNotification::class, fn ($n) => $n->event === 'commented');
    }
}
