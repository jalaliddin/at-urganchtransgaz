<?php

namespace Tests\Feature\Itsm;

use App\Enums\AssetEventAction;
use App\Models\Role;
use App\Services\Settings\SettingsService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItsmFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, list<string>}> */
    public static function defaults(): array
    {
        $all = ['kb' => ['view', 'internal', 'write', 'manage'], 'problems' => ['view', 'manage', 'export'],
            'changes' => ['view', 'create', 'approve', 'manage', 'export'], 'maintenance' => ['view', 'manage', 'perform', 'export']];
        $rows = [];
        foreach ([
            'central-it-head' => $all,
            'central-it-staff' => ['kb' => ['view', 'internal', 'write'], 'problems' => ['view', 'manage', 'export'],
                'changes' => ['view', 'create', 'manage', 'export'], 'maintenance' => ['view', 'manage', 'perform', 'export']],
            'org-it-responsible' => ['kb' => ['view', 'internal', 'write'], 'problems' => ['view', 'manage', 'export'],
                'changes' => ['view', 'create', 'export'], 'maintenance' => ['view', 'manage', 'perform', 'export']],
            'org-head' => ['kb' => ['view'], 'problems' => ['view'], 'changes' => ['view'], 'maintenance' => ['view']],
            'employee' => ['kb' => ['view'], 'problems' => [], 'changes' => [], 'maintenance' => []],
            'accountant' => ['kb' => ['view'], 'problems' => [], 'changes' => [], 'maintenance' => []],
            'auditor' => ['kb' => ['view', 'internal'], 'problems' => ['view', 'export'], 'changes' => ['view', 'export'],
                'maintenance' => ['view', 'export']],
        ] as $role => $modules) {
            foreach ($modules as $module => $actions) {
                $rows["{$role} {$module}"] = [$role, $module, $actions];
            }
        }

        return $rows;
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_module_permissions(string $role, string $module, array $expected): void
    {
        $this->seed(RbacSeeder::class);
        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, $module.'.'))->map(fn (string $p) => substr($p, strlen($module) + 1))
            ->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_recurrence_threshold_defaults_to_three_and_asset_events_exist(): void
    {
        $this->assertSame(3, app(SettingsService::class)->get('problems.recurrence_threshold'));
        $this->assertSame('change_implemented', AssetEventAction::ChangeImplemented->value);
        $this->assertSame('preventive_done', AssetEventAction::PreventiveDone->value);
    }
}
