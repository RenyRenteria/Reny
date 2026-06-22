<?php

namespace App\Support;

use App\Enums\ContentType;

class AdminCmsSections
{
    /**
     * @return array<string, array{label: string, caption: string, accent: string, types: array<int, string>}>
     */
    public static function tabs(): array
    {
        return [
            'music' => [
                'label' => 'Musica',
                'caption' => 'Songs, albums and Royal exclusives',
                'accent' => '#d22547',
                'types' => [
                    ContentType::Song->value,
                    ContentType::MusicalAlbum->value,
                    ContentType::DeluxeAlbum->value,
                    ContentType::MusicPlaylist->value,
                    ContentType::Exclusive->value,
                ],
            ],
            'video' => [
                'label' => 'Video',
                'caption' => 'Clips, premieres and visual assets',
                'accent' => '#2764d8',
                'types' => [
                    ContentType::Video->value,
                    ContentType::Photo->value,
                    ContentType::Gallery->value,
                ],
            ],
            'events' => [
                'label' => 'Events',
                'caption' => 'Tickets, drops and store moments',
                'accent' => '#a96519',
                'types' => [
                    ContentType::Event->value,
                    ContentType::Drop->value,
                    ContentType::Product->value,
                ],
            ],
            'community' => [
                'label' => 'Community',
                'caption' => 'Posts, polls and member updates',
                'accent' => '#1f8a70',
                'types' => [
                    ContentType::Post->value,
                    ContentType::Poll->value,
                ],
            ],
        ];
    }

    public static function normalize(?string $section): ?string
    {
        return array_key_exists((string) $section, self::tabs()) ? (string) $section : null;
    }

    /**
     * @return array<int, string>
     */
    public static function typesFor(?string $section): array
    {
        $section = self::normalize($section);

        return $section === null ? [] : self::tabs()[$section]['types'];
    }

    public static function sectionForType(ContentType|string|null $type): ?string
    {
        $value = $type instanceof ContentType ? $type->value : $type;

        foreach (self::tabs() as $section => $tab) {
            if (in_array($value, $tab['types'], true)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, caption: string, accent: string, types: array<int, string>}|null
     */
    public static function tabForType(ContentType|string|null $type): ?array
    {
        $section = self::sectionForType($type);

        return $section === null ? null : self::tabs()[$section];
    }
}
