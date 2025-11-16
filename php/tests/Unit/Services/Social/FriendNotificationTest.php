<?php

namespace Tests\Unit\Services\Social;

use PHPUnit\Framework\TestCase;
use Happy\Services\Social\FriendNotification;
use Happy\Services\Social\RelationshipStatus;

class FriendNotificationTest extends TestCase
{
    public function testReturnTrueWhenLastNotifiedAtIsNull(): void
    {
        $result = FriendNotification::shouldSendNotification(null, RelationshipStatus::PENDING);
        $this->assertTrue($result);
    }

    public function testReturnFalseForRejectedRelationships(): void
    {
        $result = FriendNotification::shouldSendNotification(null, RelationshipStatus::REJECTED);
        $this->assertFalse($result);
    }

    public function testReturnFalseForRejectedRelationshipsEvenIf24HoursPassed(): void
    {
        $twentyFiveHoursAgo = new \DateTime();
        $twentyFiveHoursAgo->modify('-25 hours');

        $result = FriendNotification::shouldSendNotification($twentyFiveHoursAgo, RelationshipStatus::REJECTED);
        $this->assertFalse($result);
    }

    public function testReturnTrueWhen24HoursHavePassedSinceLastNotification(): void
    {
        $twentyFiveHoursAgo = new \DateTime();
        $twentyFiveHoursAgo->modify('-25 hours');

        $result = FriendNotification::shouldSendNotification($twentyFiveHoursAgo, RelationshipStatus::PENDING);
        $this->assertTrue($result);
    }

    public function testReturnFalseWhenLessThan24HoursHavePassed(): void
    {
        $tenHoursAgo = new \DateTime();
        $tenHoursAgo->modify('-10 hours');

        $result = FriendNotification::shouldSendNotification($tenHoursAgo, RelationshipStatus::PENDING);
        $this->assertFalse($result);
    }

    public function testWorkForFriendStatus(): void
    {
        $twentyFiveHoursAgo = new \DateTime();
        $twentyFiveHoursAgo->modify('-25 hours');

        $result = FriendNotification::shouldSendNotification($twentyFiveHoursAgo, RelationshipStatus::FRIEND);
        $this->assertTrue($result);
    }

    public function testWorkForRequestedStatus(): void
    {
        $result = FriendNotification::shouldSendNotification(null, RelationshipStatus::REQUESTED);
        $this->assertTrue($result);
    }
}
