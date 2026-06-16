<?php

namespace App\Enums;

enum MediaAssetType: string
{
    case Image = 'image';
    case Audio = 'audio';
    case ShortVideo = 'short_video';
    case Document = 'document';
    case ProductAsset = 'product_asset';
    case Thumbnail = 'thumbnail';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresAltTextWhenPublic(): bool
    {
        return in_array($this, [self::Image, self::Thumbnail], true);
    }
}
