<?php

namespace Happy\Services\Social;

/**
 * Relationship status enum matching the database schema.
 */
enum RelationshipStatus: string
{
    case PENDING = 'pending';
    case FRIEND = 'friend';
    case REQUESTED = 'requested';
    case REJECTED = 'rejected';
}

class FriendNotification
{
    /**
     * Check if a notification should be sent based on the last notification time and relationship status.
     *
     * Returns true if:
     * - No previous notification was sent (lastNotifiedAt is null)
     * - OR 24 hours have passed since the last notification
     * - AND the relationship is not rejected
     *
     * @param \DateTime|null $lastNotifiedAt
     * @param RelationshipStatus $status
     * @return bool
     */
    public static function shouldSendNotification(
        ?\DateTime $lastNotifiedAt,
        RelationshipStatus $status
    ): bool {
        // Don't send notifications for rejected relationships
        if ($status === RelationshipStatus::REJECTED) {
            return false;
        }

        // If never notified, send notification
        if ($lastNotifiedAt === null) {
            return true;
        }

        // Check if 24 hours have passed since last notification
        $twentyFourHoursAgo = new \DateTime();
        $twentyFourHoursAgo->modify('-24 hours');

        return $lastNotifiedAt < $twentyFourHoursAgo;
    }
}
