<?php

namespace App\Enums;

enum EditorialAuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case ApprovalRequested = 'approval_requested';
    case Approved = 'approved';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
    case Retired = 'retired';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
