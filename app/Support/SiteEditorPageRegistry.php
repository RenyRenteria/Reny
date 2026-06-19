<?php

namespace App\Support;

use App\Enums\ContentType;

class SiteEditorPageRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function pages(): array
    {
        return [
            'home' => [
                'label' => 'Home',
                'theme' => 'music',
                'public_path' => '/',
                'summary' => 'Portada principal del sitio publico.',
                'blocks' => [
                    $this->pageSettingsBlock('Hero, header y cover'),
                    $this->block('home-featured-music', 'Featured music hero', [ContentType::Song, ContentType::MusicalAlbum, ContentType::DeluxeAlbum, ContentType::Exclusive], ContentType::Song),
                    $this->block('home-featured-store', 'Featured store / event', [ContentType::Event, ContentType::Product, ContentType::Drop], ContentType::Event),
                    $this->block('home-community-highlight', 'Community highlight', [ContentType::Post, ContentType::Poll], ContentType::Post),
                ],
            ],
            'music' => [
                'label' => 'Music',
                'theme' => 'music',
                'public_path' => '/',
                'summary' => 'Albums, singles, canciones y releases destacados.',
                'blocks' => [
                    $this->pageSettingsBlock('Music page header'),
                    $this->block('music-albums', 'Albums', [ContentType::MusicalAlbum, ContentType::DeluxeAlbum], ContentType::MusicalAlbum),
                    $this->block('music-singles', 'Songs / singles', [ContentType::Song, ContentType::Exclusive], ContentType::Song),
                ],
            ],
            'videos' => [
                'label' => 'Videos',
                'theme' => 'video',
                'public_path' => '/videos',
                'summary' => 'Video premiere, playlists, performances, BTS y vlogs.',
                'blocks' => [
                    $this->pageSettingsBlock('Videos page header'),
                    $this->block('videos-featured', 'Featured video', [ContentType::Video], ContentType::Video),
                    $this->block('videos-library', 'Video library', [ContentType::Video], ContentType::Video),
                ],
            ],
            'photos' => [
                'label' => 'Photos',
                'theme' => 'events',
                'public_path' => '/photos',
                'summary' => 'Fotos, galerias, captions y assets visuales.',
                'blocks' => [
                    $this->pageSettingsBlock('Photos page header'),
                    $this->block('photos-grid', 'Photo grid', [ContentType::Photo], ContentType::Photo),
                    $this->block('photos-galleries', 'Galleries', [ContentType::Gallery], ContentType::Gallery),
                ],
            ],
            'store' => [
                'label' => 'Store',
                'theme' => 'events',
                'public_path' => '/store',
                'summary' => 'Productos, eventos, drops, RSVP y tickets.',
                'blocks' => [
                    $this->pageSettingsBlock('Store page header'),
                    $this->block('store-hero-event', 'Hero event', [ContentType::Event], ContentType::Event),
                    $this->block('store-products', 'Products and digital drops', [ContentType::Product, ContentType::Drop, ContentType::Exclusive], ContentType::Product),
                    $this->block('store-events', 'Events and tickets', [ContentType::Event], ContentType::Event),
                ],
            ],
            'community' => [
                'label' => 'Community',
                'theme' => 'community',
                'public_path' => '/community',
                'summary' => 'Posts, polls y contenido para fans.',
                'blocks' => [
                    $this->pageSettingsBlock('Community page header'),
                    $this->block('community-feed', 'Official feed posts', [ContentType::Post], ContentType::Post),
                    $this->block('community-polls', 'Polls', [ContentType::Poll], ContentType::Poll),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function page(string $key): ?array
    {
        return $this->pages()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->pages());
    }

    /**
     * @param  array<int, ContentType>  $types
     * @return array<string, mixed>
     */
    private function block(string $key, string $label, array $types, ContentType $defaultType): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'types' => array_map(fn (ContentType $type): string => $type->value, $types),
            'default_type' => $defaultType->value,
            'persistable' => true,
            'note' => 'Guarda contenido real en el CMS existente.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageSettingsBlock(string $label): array
    {
        return [
            'key' => str($label)->slug('-')->toString(),
            'label' => $label,
            'types' => [],
            'default_type' => null,
            'persistable' => false,
            'note' => 'Falta un modelo de page settings para editar este header/cover desde CMS.',
        ];
    }
}
