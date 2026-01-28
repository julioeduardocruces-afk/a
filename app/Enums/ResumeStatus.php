<?php

namespace App\Enums;

enum ResumeStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case PreviewReady = 'preview_ready';
    case Paid = 'paid';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * Allowed transitions from this state.
     *
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft       => [self::Processing],
            self::Processing  => [self::PreviewReady, self::Failed],
            self::PreviewReady => [self::Paid, self::Failed],
            self::Paid        => [self::Delivered],
            self::Delivered   => [],
            self::Failed      => [self::Processing], // allow retry
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
