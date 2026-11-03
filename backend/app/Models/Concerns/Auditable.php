<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records created/updated/deleted/restored events in audit_logs. Secrets and
 * bookkeeping columns are never written; a model may add its own exclusions via
 * a `protected array $auditExclude` property.
 */
trait Auditable
{
    /** @var list<string> */
    private static array $globalAuditExclude = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&self $model */
            $model->writeAudit(AuditEvent::Created, [], $model->auditFilter($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $new = $model->auditFilter($model->getChanges());
            if ($new === []) {
                return;
            }
            $old = [];
            foreach (array_keys($new) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }
            $model->writeAudit(AuditEvent::Updated, $old, $new);
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            $model->writeAudit(AuditEvent::Deleted, $model->auditFilter($model->getAttributes()), []);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', function (Model $model): void {
                /** @var Model&self $model */
                $model->writeAudit(AuditEvent::Restored, [], []);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditFilter(array $attributes): array
    {
        $exclude = array_merge(
            self::$globalAuditExclude,
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(AuditEvent $event, array $old, array $new): void
    {
        app(AuditLogger::class)->record($event, $this, $old, $new);
    }
}
