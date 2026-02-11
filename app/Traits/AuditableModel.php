<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait AuditableModel
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getLogName())
            ->setDescriptionForEvent(fn(string $eventName) => $this->getActivityDescription($eventName));
    }

    protected function getLogName(): string
    {
        return $this->logName ?? 'default';
    }

    protected function getActivityDescription(string $eventName): string
    {
        $modelName = class_basename($this);
        $descriptions = [
            'created' => "{$modelName} creado",
            'updated' => "{$modelName} actualizado",
            'deleted' => "{$modelName} eliminado",
        ];

        return $descriptions[$eventName] ?? "{$modelName} {$eventName}";
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $request = request();

        $activity->ip_address = $request->ip();
        $activity->user_agent = $request->userAgent();
        $activity->modulo = $this->getModulo();
    }

    protected function getModulo(): string
    {
        return $this->modulo ?? 'general';
    }
}
