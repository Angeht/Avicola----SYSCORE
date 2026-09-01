<?php

namespace App\Models;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Stringable;

class AuditableModelObserver
{
    /** @var list<string> */
    private const EXCLUDED_FIELDS = [
        'id',
        'created_at',
        'updated_at',
        'password',
        'password_hash',
        'pin_autorizacion_hash',
        'remember_token',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'INSERT', $model->getAttributes(), []);
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), self::EXCLUDED_FIELDS);

        if ($changes === []) {
            return;
        }

        $previous = collect(array_keys($changes))
            ->mapWithKeys(fn (string $field): array => [$field => $model->getRawOriginal($field)])
            ->all();
        $action = filled($changes['anulada_at'] ?? $changes['anulado_at'] ?? null)
            ? 'ANULAR'
            : 'UPDATE';

        $this->record($model, $action, $changes, $previous);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'DELETE', [], $model->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     */
    private function record(Model $model, string $action, array $current, array $previous): void
    {
        $actor = request()->user();

        if (! $actor instanceof Usuario || request()->route() === null) {
            return;
        }

        $fields = array_values(array_diff(
            array_unique([...array_keys($previous), ...array_keys($current)]),
            self::EXCLUDED_FIELDS,
        ));

        $audit = Auditoria::query()->create([
            'usuario_id' => $actor->getKey(),
            'tabla_afectada' => $model->getTable(),
            'registro_id' => is_numeric($model->getKey()) ? (int) $model->getKey() : null,
            'accion' => $action,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        if ($fields === []) {
            return;
        }

        $audit->detalles()->createMany(collect($fields)
            ->map(fn (string $field): array => [
                'campo' => $field,
                'valor_anterior' => $this->stringValue($previous[$field] ?? null),
                'valor_nuevo' => $this->stringValue($current[$field] ?? null),
            ])
            ->all());
    }

    private function stringValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'Sí' : 'No',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            $value instanceof Stringable => (string) $value,
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
        };
    }
}
