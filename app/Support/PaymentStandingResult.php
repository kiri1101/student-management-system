<?php

namespace App\Support;

use App\Enums\PaymentStanding;

/**
 * Immutable result of a payment-standing computation (#8). Carries the verdict
 * plus the figures behind it so the UI can explain "why blocked / what to pay".
 */
final readonly class PaymentStandingResult
{
    public function __construct(
        public PaymentStanding $standing,
        public bool $hasSchedule,
        public int $totalXaf,
        public int $requiredSoFar,
        public int $validatedPaid,
        public int $shortfall,
        public ?string $activeDeferralDeadline = null,
    ) {}

    /**
     * @return array{
     *     standing: string,
     *     has_schedule: bool,
     *     total_xaf: int,
     *     required_so_far: int,
     *     validated_paid: int,
     *     shortfall: int,
     *     active_deferral_deadline: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'standing' => $this->standing->value,
            'has_schedule' => $this->hasSchedule,
            'total_xaf' => $this->totalXaf,
            'required_so_far' => $this->requiredSoFar,
            'validated_paid' => $this->validatedPaid,
            'shortfall' => $this->shortfall,
            'active_deferral_deadline' => $this->activeDeferralDeadline,
        ];
    }
}
