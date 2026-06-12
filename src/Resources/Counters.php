<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Resources;

use TexHub\VirtualPosAndoz\Enums\CounterValueType;
use TexHub\VirtualPosAndoz\Enums\FormCode;
use TexHub\VirtualPosAndoz\Responses\Response;

/**
 * Universal counters (универсальные счётчики). Up to 32 counters.
 */
final class Counters extends Resource
{
    /**
     * Create a counter.
     *
     * @param int              $code      Free-form integer code (e.g. 101).
     * @param string           $title     Counter title (max 50 chars).
     * @param CounterValueType $valueType LONG or FLOAT.
     * @param int|float        $start     Initial value.
     * @param bool             $autoReset Reset automatically on shift close.
     */
    public function create(
        int $code,
        string $title,
        CounterValueType $valueType = CounterValueType::Long,
        int|float $start = 0,
        bool $autoReset = true,
    ): Response {
        return $this->api->call(FormCode::CreateCounter->command(), FormCode::CreateCounter->value, [
            'counterTitle' => $title,
            'counterCode' => $code,
            'valueType' => $valueType->value,
            'start' => $start,
            'autoReset' => $autoReset,
        ]);
    }

    /**
     * List current counters.
     */
    public function list(): Response
    {
        return $this->api->call(FormCode::ListCounters->command(), FormCode::ListCounters->value);
    }

    /**
     * Increase a counter (negative value subtracts).
     */
    public function increase(int $code, int|float $value): Response
    {
        return $this->api->call(FormCode::IncreaseCounter->command(), FormCode::IncreaseCounter->value, [
            'counterCode' => $code,
            'value' => $value,
        ]);
    }

    /**
     * Decrease a counter (negative value adds).
     */
    public function decrease(int $code, int|float $value): Response
    {
        return $this->api->call(FormCode::DecreaseCounter->command(), FormCode::DecreaseCounter->value, [
            'counterCode' => $code,
            'value' => $value,
        ]);
    }

    /**
     * Reset a counter to zero.
     */
    public function reset(int $code): Response
    {
        return $this->api->call(FormCode::ResetCounter->command(), FormCode::ResetCounter->value, [
            'counterCode' => $code,
        ]);
    }

    /**
     * Delete a counter.
     */
    public function delete(int $code): Response
    {
        return $this->api->call(FormCode::DeleteCounter->command(), FormCode::DeleteCounter->value, [
            'counterCode' => $code,
        ]);
    }
}
