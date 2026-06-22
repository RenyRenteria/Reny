<?php

namespace App\Enums;

enum ContentType: string
{
    case Song = 'song';
    case MusicalAlbum = 'musical_album';
    case DeluxeAlbum = 'deluxe_album';
    case MusicPlaylist = 'music_playlist';
    case Video = 'video';
    case Photo = 'photo';
    case Gallery = 'gallery';
    case Post = 'post';
    case Poll = 'poll';
    case Product = 'product';
    case Event = 'event';
    case Drop = 'drop';
    case Exclusive = 'exclusive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
