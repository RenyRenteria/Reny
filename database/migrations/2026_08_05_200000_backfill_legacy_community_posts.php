<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('editorial_contents') || ! DB::table('users')->exists()) {
            return;
        }

        foreach ($this->legacyPosts() as $post) {
            $exists = DB::table('editorial_contents')
                ->where('type', 'post')
                ->where(function ($query) use ($post): void {
                    $query->where('slug', $post['slug'])->orWhere('title', $post['title']);
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $postId = DB::table('editorial_contents')->insertGetId([
                'type' => 'post',
                'title' => $post['title'],
                'slug' => $post['slug'],
                'summary' => $post['summary'],
                'body' => $post['body'],
                'status' => 'published',
                'visibility' => 'open',
                'needs_approval' => false,
                'published_at' => $post['published_at'],
                'metadata' => json_encode([
                    'published_on' => $post['published_on'],
                    'comments_enabled' => true,
                    'image_url' => $post['image_url'],
                    'media_items' => [],
                    'backfill_source' => 'legacy-community-feed',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $post['published_at'],
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('editorial_audit_logs')) {
                DB::table('editorial_audit_logs')->insert([
                    'editorial_content_id' => $postId,
                    'actor_id' => null,
                    'action' => 'created',
                    'changes' => json_encode(['source' => 'legacy-community-feed']),
                    'snapshot' => json_encode([
                        'id' => $postId,
                        'type' => 'post',
                        'status' => 'published',
                    ]),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep posts because editors may have changed them after the backfill.
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function legacyPosts(): array
    {
        return [
            [
                'title' => 'Studio note from Reny',
                'slug' => 'studio-note-from-reny',
                'summary' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first.',
                'body' => '<p>Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first. I am keeping the first look inside the community because the next chapter should feel close, early, and built with the people who keep showing up.</p>',
                'image_url' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1400&q=80',
                'published_on' => '2026-07-21',
                'published_at' => '2026-07-21 12:00:00',
            ],
            [
                'title' => 'Capri photo drop',
                'slug' => 'capri-photo-drop',
                'summary' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next.',
                'body' => '<p>A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next, especially where fans have been organizing watch parties and meetups.</p>',
                'image_url' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1400&q=80',
                'published_on' => '2026-07-18',
                'published_at' => '2026-07-18 12:00:00',
            ],
        ];
    }
};
