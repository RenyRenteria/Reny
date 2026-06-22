<?php

namespace App\Support;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
use Illuminate\Validation\Rule;

class EditorialContentForms
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            ContentType::Song->value => [
                'label' => 'Song',
                'description' => 'Required audio, artwork, and member/open release dates.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'release_date_member_view', 'label' => 'Member release date', 'type' => 'datetime-local', 'required' => true],
                    ['name' => 'release_date_open_view', 'label' => 'Open release date', 'type' => 'datetime-local', 'required' => true],
                ],
            ],
            ContentType::MusicalAlbum->value => [
                'label' => 'Musical album',
                'description' => 'Required album artwork, member/open release dates, and tracks.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'release_date_member_view', 'label' => 'Member release date', 'type' => 'datetime-local', 'required' => true],
                    ['name' => 'release_date_open_view', 'label' => 'Open release date', 'type' => 'datetime-local', 'required' => true],
                ],
            ],
            ContentType::DeluxeAlbum->value => [
                'label' => 'Album / deluxe',
                'description' => 'Special package page with premium access and bundled assets.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'package_title', 'label' => 'Package title', 'type' => 'text', 'required' => true],
                    ['name' => 'package_notes', 'label' => 'Package notes', 'type' => 'textarea', 'required' => true],
                    ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'step' => '0.01'],
                ],
            ],
            ContentType::MusicPlaylist->value => [
                'label' => 'Music playlist',
                'description' => 'A public music playlist built from existing songs and album tracks.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'tracks', 'label' => 'Tracks', 'type' => 'list', 'required' => true],
                ],
            ],
            ContentType::Video->value => [
                'label' => 'Video',
                'description' => 'YouTube or Mux video metadata, thumbnail, category, and playlist.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'video_url', 'label' => 'Video URL', 'type' => 'url', 'required' => true],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => true],
                    ['name' => 'playlist', 'label' => 'Playlist', 'type' => 'text'],
                    ['name' => 'access_label', 'label' => 'Free / premium label', 'type' => 'text'],
                ],
            ],
            ContentType::Photo->value => [
                'label' => 'Photo',
                'description' => 'Image, caption, location, tags, and access.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'caption', 'label' => 'Caption', 'type' => 'textarea', 'required' => true],
                    ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
                ],
            ],
            ContentType::Gallery->value => [
                'label' => 'Visual album / gallery',
                'description' => 'Grouped images with theme, captioning, and access rules.',
                'media_required' => true,
                'fields' => [
                    ['name' => 'gallery_theme', 'label' => 'Gallery theme', 'type' => 'text', 'required' => true],
                    ['name' => 'caption', 'label' => 'Gallery caption', 'type' => 'textarea'],
                    ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
                ],
            ],
            ContentType::Post->value => [
                'label' => 'Post',
                'description' => 'Editorial text, media, links, pinning, and visibility.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'link_url', 'label' => 'Link URL', 'type' => 'url'],
                    ['name' => 'pinned_until', 'label' => 'Pinned until', 'type' => 'datetime-local'],
                ],
            ],
            ContentType::Poll->value => [
                'label' => 'Poll',
                'description' => 'Question, options, eligibility, dates, and result visibility.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true],
                    ['name' => 'options', 'label' => 'Options', 'type' => 'list', 'required' => true],
                    ['name' => 'eligibility', 'label' => 'Eligibility', 'type' => 'text', 'required' => true],
                    ['name' => 'results_visibility', 'label' => 'Results visibility', 'type' => 'text'],
                ],
            ],
            ContentType::Product->value => [
                'label' => 'Product',
                'description' => 'Digital, physical, subscription, drop, or bundle.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'product_type', 'label' => 'Product type', 'type' => 'text', 'required' => true],
                    ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'step' => '0.01', 'required' => true],
                    ['name' => 'inventory', 'label' => 'Inventory', 'type' => 'number'],
                    ['name' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ],
            ],
            ContentType::Event->value => [
                'label' => 'Event',
                'description' => 'Physical, digital, or listening session with inventory and RSVP.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'event_type', 'label' => 'Event type', 'type' => 'text', 'required' => true],
                    ['name' => 'event_starts_at', 'label' => 'Event starts at', 'type' => 'datetime-local', 'required' => true],
                    ['name' => 'venue', 'label' => 'Venue / URL', 'type' => 'text', 'required' => true],
                    ['name' => 'inventory', 'label' => 'Inventory', 'type' => 'number', 'required' => true],
                    ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'step' => '0.01'],
                ],
            ],
            ContentType::Drop->value => [
                'label' => 'Drop',
                'description' => 'Timed release, inventory, product/media bundle, and access.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'drop_window', 'label' => 'Drop window', 'type' => 'datetime-local', 'required' => true],
                    ['name' => 'inventory', 'label' => 'Inventory', 'type' => 'number', 'required' => true],
                    ['name' => 'bundle_notes', 'label' => 'Bundle notes', 'type' => 'textarea', 'required' => true],
                ],
            ],
            ContentType::Exclusive->value => [
                'label' => 'Exclusive content',
                'description' => 'Member, Royal, or purchased-only content.',
                'media_required' => false,
                'fields' => [
                    ['name' => 'access_note', 'label' => 'Access note', 'type' => 'textarea', 'required' => true],
                    ['name' => 'unlocked_by', 'label' => 'Unlocked by', 'type' => 'text', 'required' => true],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(ContentType $type): array
    {
        return $this->definitions()[$type->value];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(ContentType $type): array
    {
        $rules = [
            'type' => ['required', Rule::in(ContentType::values())],
            'title' => ['required', 'string', 'max:160'],
        ];

        if ($this->definition($type)['media_required']) {
            $rules['media_asset_ids'] = ['required', 'array', 'min:1'];
        }

        return [
            ...$rules,
            ...match ($type) {
                ContentType::Song => [
                    'metadata.release_date_member_view' => ['required', 'date'],
                    'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                ],
                ContentType::MusicalAlbum => [
                    'metadata.release_date_member_view' => ['required', 'date'],
                    'metadata.release_date_open_view' => ['required', 'date', 'after_or_equal:metadata.release_date_member_view'],
                    'metadata.tracks' => ['required', 'array', 'min:1'],
                    'metadata.tracks.*.track_name' => ['required', 'string', 'max:160'],
                    'metadata.tracks.*.release_date_member_view' => ['nullable', 'date'],
                ],
                ContentType::DeluxeAlbum => [
                    'purchase_key' => ['required', 'string', 'max:120'],
                    'metadata.package_title' => ['required', 'string', 'max:160'],
                    'metadata.package_notes' => ['required', 'string'],
                    'metadata.price' => ['nullable', 'numeric', 'min:0'],
                ],
                ContentType::MusicPlaylist => [
                    'metadata.tracks' => ['required', 'array', 'min:1'],
                    'metadata.tracks.*' => ['required', 'string', 'max:80'],
                ],
                ContentType::Video => [
                    'metadata.video_url' => ['required', 'url', 'max:500'],
                    'metadata.category' => ['required', 'string', 'max:120'],
                    'metadata.playlist' => ['nullable', 'string', 'max:160'],
                    'metadata.access_label' => ['nullable', 'string', 'max:80'],
                ],
                ContentType::Photo => [
                    'metadata.caption' => ['required', 'string'],
                    'metadata.location' => ['nullable', 'string', 'max:160'],
                ],
                ContentType::Gallery => [
                    'metadata.gallery_theme' => ['required', 'string', 'max:160'],
                    'metadata.caption' => ['nullable', 'string'],
                    'metadata.location' => ['nullable', 'string', 'max:160'],
                ],
                ContentType::Post => [
                    'body' => ['required', 'string'],
                    'metadata.link_url' => ['nullable', 'url', 'max:500'],
                    'metadata.pinned_until' => ['nullable', 'date'],
                ],
                ContentType::Poll => [
                    'metadata.question' => ['required', 'string', 'max:220'],
                    'metadata.options' => ['required', 'array', 'min:2'],
                    'metadata.options.*' => ['nullable', 'string', 'max:160'],
                    'metadata.eligibility' => ['required', 'string', 'max:160'],
                    'metadata.results_visibility' => ['nullable', 'string', 'max:120'],
                ],
                ContentType::Product => [
                    'purchase_key' => ['required', 'string', 'max:120'],
                    'metadata.product_type' => ['required', 'string', 'max:80'],
                    'metadata.price' => ['required', 'numeric', 'min:0'],
                    'metadata.inventory' => ['nullable', 'integer', 'min:0'],
                    'metadata.sku' => ['nullable', 'string', 'max:120'],
                ],
                ContentType::Event => [
                    'metadata.event_type' => ['required', 'string', 'max:80'],
                    'metadata.event_starts_at' => ['required', 'date'],
                    'metadata.venue' => ['required', 'string', 'max:220'],
                    'metadata.inventory' => ['required', 'integer', 'min:0'],
                    'metadata.price' => ['nullable', 'numeric', 'min:0'],
                ],
                ContentType::Drop => [
                    'metadata.drop_window' => ['required', 'date'],
                    'metadata.inventory' => ['required', 'integer', 'min:0'],
                    'metadata.bundle_notes' => ['required', 'string'],
                ],
                ContentType::Exclusive => [
                    'visibility' => ['required', Rule::in([
                        VisibilityAudience::Member->value,
                        VisibilityAudience::Royal->value,
                        VisibilityAudience::Purchased->value,
                    ])],
                    'metadata.access_note' => ['required', 'string'],
                    'metadata.unlocked_by' => ['required', 'string', 'max:160'],
                ],
            },
        ];
    }
}
