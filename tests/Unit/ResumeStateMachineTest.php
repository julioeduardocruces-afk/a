<?php

namespace Tests\Unit;

use App\Enums\ResumeStatus;
use PHPUnit\Framework\TestCase;

class ResumeStateMachineTest extends TestCase
{
    public function test_draft_can_transition_to_processing(): void
    {
        $status = ResumeStatus::Draft;
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Processing));
    }

    public function test_draft_cannot_transition_to_paid(): void
    {
        $status = ResumeStatus::Draft;
        $this->assertFalse($status->canTransitionTo(ResumeStatus::Paid));
    }

    public function test_processing_can_transition_to_preview_ready_or_failed(): void
    {
        $status = ResumeStatus::Processing;
        $this->assertTrue($status->canTransitionTo(ResumeStatus::PreviewReady));
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Failed));
        $this->assertFalse($status->canTransitionTo(ResumeStatus::Paid));
    }

    public function test_preview_ready_can_transition_to_paid_or_failed(): void
    {
        $status = ResumeStatus::PreviewReady;
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Paid));
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Failed));
        $this->assertFalse($status->canTransitionTo(ResumeStatus::Delivered));
    }

    public function test_paid_can_transition_to_delivered_or_failed(): void
    {
        $status = ResumeStatus::Paid;
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Delivered));
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Failed));
        $this->assertFalse($status->canTransitionTo(ResumeStatus::Processing));
    }

    public function test_delivered_cannot_transition_to_anything(): void
    {
        $status = ResumeStatus::Delivered;
        $this->assertEmpty($status->allowedTransitions());
    }

    public function test_failed_can_retry_to_processing_or_recover_to_delivered(): void
    {
        $status = ResumeStatus::Failed;
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Processing));
        $this->assertTrue($status->canTransitionTo(ResumeStatus::Delivered));
        $this->assertFalse($status->canTransitionTo(ResumeStatus::Paid));
    }
}
