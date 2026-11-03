<?php

namespace Tests\Feature\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccessChanged;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $org = Organization::factory()->create();
        $this->me = $this->makeUser(Roles::EMPLOYEE, $org);
        $this->other = $this->makeUser(Roles::EMPLOYEE, $org);
        $this->me->notify(new AccessChanged('Admin'));
        $this->me->notify(new AccessChanged('Admin'));
        $this->other->notify(new AccessChanged('Admin'));
        $this->actingAs($this->me);
    }

    public function test_lists_only_own_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'access_changed')
            ->assertJsonPath('data.0.read_at', null);
    }

    public function test_unread_filter_count_and_mark_read(): void
    {
        $id = $this->me->notifications()->first()->id;

        $this->getJson('/api/v1/notifications/unread-count')->assertExactJson(['data' => ['count' => 2]]);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertExactJson(['data' => ['count' => 1]]);
        $this->getJson('/api/v1/notifications?filter[unread]=1')->assertJsonCount(1, 'data');
    }

    public function test_read_all(): void
    {
        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')->assertExactJson(['data' => ['count' => 0]]);
        $this->assertSame(1, $this->other->unreadNotifications()->count());
    }

    public function test_other_users_notification_is_not_found(): void
    {
        $foreign = $this->other->notifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$foreign}/read")->assertNotFound();
    }
}
