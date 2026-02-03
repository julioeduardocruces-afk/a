<?php

namespace App\Jobs;

use App\Services\MetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches a Meta Conversions API event asynchronously.
 *
 * This prevents CAPI HTTP calls (up to 5s timeout) from blocking the
 * user-facing request. Failed attempts are retried with backoff.
 */
class SendMetaCapiEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public string $eventName,
        public string $eventId,
        public array $userData,
        public array $customData = [],
        public ?string $sourceUrl = null,
    ) {}

    public function handle(): void
    {
        MetaConversionsService::sendEvent(
            $this->eventName,
            $this->eventId,
            $this->userData,
            $this->customData,
            $this->sourceUrl,
        );
    }
}
