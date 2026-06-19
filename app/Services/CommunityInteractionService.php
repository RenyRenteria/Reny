<?php

namespace App\Services;

use App\Models\CommunityCountryClub;
use App\Models\CommunityCountryClubMembership;
use App\Models\CommunityCountryClubMessage;
use App\Models\CommunityPollVote;
use App\Models\CommunityPostReaction;
use App\Models\CommunityPostReply;
use App\Models\User;
use App\Support\EntitlementMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityInteractionService
{
    private const FALLBACK_POSTS = [
        [
            'key' => 'studio-note-from-reny',
            'title' => 'Studio note from Reny',
            'time' => 'Today',
            'body' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first.',
            'full_body' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first. I am keeping the first look inside the community because the next chapter should feel close, early, and built with the people who keep showing up.',
            'image_url' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1400&q=80',
            'image_alt' => 'Warm recording studio with microphone and instruments',
            'base_likes' => 284,
            'base_replies' => 38,
        ],
        [
            'key' => 'capri-photo-drop',
            'title' => 'Capri photo drop',
            'time' => 'This week',
            'body' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next.',
            'full_body' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next, especially where fans have been organizing watch parties and meetups.',
            'image_url' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1400&q=80',
            'image_alt' => 'Capri coastline and turquoise sea',
            'base_likes' => 319,
            'base_replies' => 51,
        ],
    ];

    private const FALLBACK_POLL = [
        'key' => 'which-drop-should-go-first',
        'question' => 'Which drop should go first?',
        'options' => [
            ['key' => 'studio-photos', 'label' => 'Studio photos', 'votes' => 524],
            ['key' => 'performance-stills', 'label' => 'Performance stills', 'votes' => 424],
            ['key' => 'travel-archive', 'label' => 'Travel archive', 'votes' => 300],
        ],
    ];

    private const DEFAULT_CLUBS = [
        [
            'key' => 'dominican-republic',
            'name' => 'Dominican Republic',
            'flag_label' => 'DO',
            'base_members' => 8400,
            'activity' => 'Planning Santo Domingo party',
            'messages' => [
                ['author' => 'Mia', 'text' => 'Who is going to the first meetup?'],
                ['author' => 'Luis', 'text' => 'We should pin a date after the next Reny post.'],
            ],
        ],
        [
            'key' => 'panama',
            'name' => 'Panama',
            'flag_label' => 'PA',
            'base_members' => 6900,
            'activity' => 'Sharing radio clips',
            'messages' => [
                ['author' => 'Ana', 'text' => 'Radio clips thread is ready for the next drop.'],
                ['author' => 'Marco', 'text' => 'Panama City listening party list is almost full.'],
            ],
        ],
        [
            'key' => 'colombia',
            'name' => 'Colombia',
            'flag_label' => 'CO',
            'base_members' => 4200,
            'activity' => 'Building the Bogota map',
            'messages' => [
                ['author' => 'Sofia', 'text' => 'Bogota map is open for venue ideas.'],
                ['author' => 'Leo', 'text' => 'Medellin fans should get a second pin too.'],
            ],
        ],
    ];

    /**
     * @param  array<string, mixed>  $publicCms
     * @return array<string, mixed>
     */
    public function viewModel(?User $user, array $publicCms): array
    {
        $clubs = $this->clubs($user);

        return [
            'posts' => $this->posts($user, $publicCms['posts'] ?? []),
            'poll' => $this->poll($user, $publicCms['poll'] ?? null),
            'clubs' => $clubs,
            'active_club' => $clubs[0] ?? null,
            'can_use_actions' => EntitlementMatrix::canUseRoyalFeature($user),
            'login_url' => route('login'),
            'store_url' => route('store'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function club(string $key, ?User $user): array
    {
        $club = collect($this->clubs($user))
            ->firstWhere('key', $this->normalizeKey($key));

        abort_unless($club, 404);

        return $club;
    }

    /**
     * @return array{liked: bool, count: int}
     */
    public function toggleLike(User $user, string $postKey): array
    {
        $postKey = $this->normalizeKey($postKey);

        return DB::transaction(function () use ($postKey, $user): array {
            $existing = CommunityPostReaction::query()
                ->where('user_id', $user->id)
                ->where('post_key', $postKey)
                ->where('reaction', 'like')
                ->first();

            if ($existing) {
                $existing->delete();
                $liked = false;
            } else {
                CommunityPostReaction::create([
                    'user_id' => $user->id,
                    'post_key' => $postKey,
                    'reaction' => 'like',
                ]);
                $liked = true;
            }

            PublicCmsContentService::forgetCachedUserPayloads($user);

            return [
                'liked' => $liked,
                'count' => $this->persistedLikeCount($postKey),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function createReply(User $user, string $postKey, string $body): array
    {
        $reply = CommunityPostReply::create([
            'user_id' => $user->id,
            'post_key' => $this->normalizeKey($postKey),
            'body' => $body,
            'status' => 'visible',
            'metadata' => ['source' => 'community'],
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return [
            'id' => $reply->id,
            'body' => $reply->body,
            'author' => $user->name,
            'reply_count' => $this->persistedReplyCount($reply->post_key),
            'message' => 'Reply posted.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVote(User $user, string $pollKey, string $optionKey, ?string $optionLabel = null): array
    {
        $pollKey = $this->normalizeKey($pollKey);
        $optionKey = $this->normalizeKey($optionKey);

        $existing = CommunityPollVote::query()
            ->where('user_id', $user->id)
            ->where('poll_key', $pollKey)
            ->first();

        if ($existing) {
            return [
                'accepted' => false,
                'message' => 'You already voted in this poll.',
                'poll_key' => $pollKey,
                'option_key' => $existing->option_key,
            ];
        }

        $vote = CommunityPollVote::create([
            'user_id' => $user->id,
            'poll_key' => $pollKey,
            'option_key' => $optionKey,
            'option_label' => $optionLabel,
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return [
            'accepted' => true,
            'message' => 'Vote saved.',
            'poll_key' => $vote->poll_key,
            'option_key' => $vote->option_key,
            'option_label' => $vote->option_label,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function joinClub(User $user, string $clubKey): array
    {
        $club = $this->resolveClub($clubKey);

        CommunityCountryClubMembership::query()->updateOrCreate([
            'community_country_club_id' => $club->id,
            'user_id' => $user->id,
        ], [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return [
            'joined' => true,
            'message' => 'Club joined.',
            'club' => $this->club($club->key, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createClub(User $user, string $name, string $activity): array
    {
        $key = $this->normalizeKey($name);
        $club = CommunityCountryClub::query()->firstOrCreate([
            'key' => $key,
        ], [
            'name' => $name,
            'flag_label' => strtoupper(substr($key, 0, 2)),
            'activity' => $activity,
            'status' => 'active',
            'created_by_id' => $user->id,
            'metadata' => ['source' => 'community_create'],
        ]);

        $this->joinClub($user, $club->key);

        return [
            'message' => 'Country club created.',
            'club' => $this->club($club->key, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createClubMessage(User $user, string $clubKey, string $body): array
    {
        $club = $this->resolveClub($clubKey);
        $isMember = CommunityCountryClubMembership::query()
            ->where('community_country_club_id', $club->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'club' => 'Join this country club before posting.',
            ]);
        }

        $message = CommunityCountryClubMessage::create([
            'community_country_club_id' => $club->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => 'visible',
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return [
            'id' => $message->id,
            'author' => $user->name,
            'text' => $message->body,
            'message' => 'Message posted.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cmsPosts
     * @return array<int, array<string, mixed>>
     */
    private function posts(?User $user, array $cmsPosts): array
    {
        $sourcePosts = collect($cmsPosts ?: self::FALLBACK_POSTS)
            ->take(2)
            ->values()
            ->map(function (array $post, int $index): array {
                $title = (string) ($post['title'] ?? 'Community post '.($index + 1));
                $key = $post['key'] ?? $this->normalizeKey($title);

                return [
                    'key' => $key,
                    'title' => $title,
                    'time' => $post['time'] ?? 'Published',
                    'body' => $post['body'] ?? '',
                    'full_body' => $post['full_body'] ?? $post['body'] ?? '',
                    'image_url' => $post['image_url'] ?? null,
                    'image_alt' => $post['image_alt'] ?? $title,
                    'cta' => $post['cta'] ?? 'View Reny note',
                    'url' => $post['url'] ?? null,
                    'base_likes' => (int) ($post['base_likes'] ?? 0),
                    'base_replies' => (int) ($post['base_replies'] ?? 0),
                ];
            });

        $keys = $sourcePosts->pluck('key')->all();
        $likeCounts = $this->reactionCounts($keys);
        $replyCounts = $this->replyCounts($keys);
        $likedKeys = $this->likedKeys($user, $keys);

        return $sourcePosts
            ->map(fn (array $post): array => [
                ...$post,
                'like_count' => $post['base_likes'] + (int) ($likeCounts[$post['key']] ?? 0),
                'reply_count' => $post['base_replies'] + (int) ($replyCounts[$post['key']] ?? 0),
                'liked' => in_array($post['key'], $likedKeys, true),
                'like_endpoint' => route('community.posts.like', $post['key']),
                'reply_endpoint' => route('community.posts.replies.store', $post['key']),
                'share_url' => $post['url'] ?? url('/community').'#'.$post['key'],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $cmsPoll
     * @return array<string, mixed>
     */
    private function poll(?User $user, ?array $cmsPoll): array
    {
        $source = $cmsPoll ?: self::FALLBACK_POLL;
        $question = (string) ($source['question'] ?? 'Fan vote');
        $pollKey = $this->normalizeKey($source['key'] ?? $question);
        $voteCounts = $this->pollVoteCounts($pollKey);
        $userVote = $user && Schema::hasTable('community_poll_votes')
            ? CommunityPollVote::query()
                ->where('user_id', $user->id)
                ->where('poll_key', $pollKey)
                ->first()
            : null;

        $options = collect($source['options'] ?? [])
            ->values()
            ->map(function (array $option, int $index) use ($pollKey, $userVote, $voteCounts): array {
                $label = (string) ($option['label'] ?? 'Option '.($index + 1));
                $optionKey = $this->normalizeKey($option['key'] ?? $label);
                $baseVotes = (int) ($option['votes'] ?? $option['count'] ?? $option['percent'] ?? 0);

                return [
                    'key' => $optionKey,
                    'label' => $label,
                    'votes' => $baseVotes + (int) ($voteCounts[$optionKey] ?? 0),
                    'selected' => $userVote?->option_key === $optionKey,
                    'vote_endpoint' => route('community.polls.vote', $pollKey),
                ];
            });

        $percentages = $this->percentages($options->pluck('votes')->all());
        $totalVotes = (int) $options->sum('votes');

        return [
            'key' => $pollKey,
            'question' => $question,
            'options' => $options
                ->values()
                ->map(fn (array $option, int $index): array => [
                    ...$option,
                    'percent' => $percentages[$index] ?? 0,
                ])
                ->all(),
            'user_vote' => $userVote?->option_key,
            'total_votes' => $totalVotes,
            'total_votes_label' => number_format($totalVotes).' total votes',
            'vote_endpoint' => route('community.polls.vote', $pollKey),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clubs(?User $user): array
    {
        $persisted = Schema::hasTable('community_country_clubs')
            ? CommunityCountryClub::query()
                ->where('status', 'active')
                ->withCount([
                    'memberships as active_memberships_count' => fn ($query) => $query->where('status', 'active'),
                ])
                ->with([
                    'messages' => fn ($query) => $query->where('status', 'visible')->oldest()->limit(20),
                    'messages.user:id,name,username',
                ])
                ->oldest()
                ->get()
                ->keyBy('key')
            : collect();

        $joinedKeys = $user && Schema::hasTable('community_country_club_memberships')
            ? CommunityCountryClubMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('club', fn ($query) => $query->where('status', 'active'))
                ->with('club:id,key')
                ->get()
                ->map(fn (CommunityCountryClubMembership $membership): ?string => $membership->club?->key)
                ->filter()
                ->values()
                ->all()
            : [];

        $defaultKeys = collect(self::DEFAULT_CLUBS)->pluck('key')->all();

        return collect(self::DEFAULT_CLUBS)
            ->map(fn (array $source): array => $this->clubPayload($source, $persisted->get($source['key']), $joinedKeys))
            ->merge(
                $persisted
                    ->reject(fn (CommunityCountryClub $club): bool => in_array($club->key, $defaultKeys, true))
                    ->map(fn (CommunityCountryClub $club): array => $this->clubPayload([
                        'key' => $club->key,
                        'name' => $club->name,
                        'flag_label' => $club->flag_label,
                        'base_members' => 0,
                        'activity' => $club->activity,
                        'messages' => [],
                    ], $club, $joinedKeys))
                    ->values()
            )
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $joinedKeys
     * @return array<string, mixed>
     */
    private function clubPayload(array $source, ?CommunityCountryClub $club, array $joinedKeys): array
    {
        $key = $this->normalizeKey($source['key'] ?? $source['name']);
        $baseMessages = collect($source['messages'] ?? []);
        $persistedMessages = $club
            ? $club->messages->map(fn (CommunityCountryClubMessage $message): array => [
                'author' => $message->user?->name ?? $message->user?->username ?? 'Member',
                'text' => $message->body,
            ])
            : collect();

        $messages = $baseMessages
            ->merge($persistedMessages)
            ->values()
            ->all();

        if ($messages === []) {
            $messages[] = [
                'author' => 'System',
                'text' => 'Welcome thread ready. Join to post the first message.',
            ];
        }

        $memberCount = (int) ($source['base_members'] ?? 0)
            + (int) ($club?->active_memberships_count ?? 0);

        return [
            'key' => $key,
            'name' => (string) ($source['name'] ?? $club?->name ?? 'Country club'),
            'flag_label' => (string) ($source['flag_label'] ?? $club?->flag_label ?? strtoupper(substr($key, 0, 2))),
            'activity' => (string) ($source['activity'] ?? $club?->activity ?? 'New country club'),
            'members_count' => $memberCount,
            'members_label' => $this->compactCount($memberCount).' members',
            'joined' => in_array($key, $joinedKeys, true),
            'messages' => $messages,
            'detail_url' => route('community.clubs.show', $key),
            'join_endpoint' => route('community.clubs.join', $key),
            'message_endpoint' => route('community.clubs.messages.store', $key),
        ];
    }

    private function resolveClub(string $clubKey): CommunityCountryClub
    {
        $key = $this->normalizeKey($clubKey);
        $default = collect(self::DEFAULT_CLUBS)->firstWhere('key', $key);

        if (! $default && ! CommunityCountryClub::query()->where('key', $key)->exists()) {
            abort(404);
        }

        return CommunityCountryClub::query()->firstOrCreate([
            'key' => $key,
        ], [
            'name' => (string) ($default['name'] ?? str($key)->replace('-', ' ')->headline()),
            'flag_label' => (string) ($default['flag_label'] ?? strtoupper(substr($key, 0, 2))),
            'activity' => (string) ($default['activity'] ?? 'New country club'),
            'status' => 'active',
            'metadata' => ['source' => $default ? 'community_default' : 'community'],
        ]);
    }

    /**
     * @param  array<int, string>  $postKeys
     * @return array<string, int>
     */
    private function reactionCounts(array $postKeys): array
    {
        if (! Schema::hasTable('community_post_reactions')) {
            return [];
        }

        return CommunityPostReaction::query()
            ->whereIn('post_key', $postKeys)
            ->where('reaction', 'like')
            ->select('post_key')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('post_key')
            ->pluck('aggregate', 'post_key')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, string>  $postKeys
     * @return array<string, int>
     */
    private function replyCounts(array $postKeys): array
    {
        if (! Schema::hasTable('community_post_replies')) {
            return [];
        }

        return CommunityPostReply::query()
            ->whereIn('post_key', $postKeys)
            ->where('status', 'visible')
            ->select('post_key')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('post_key')
            ->pluck('aggregate', 'post_key')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, string>  $postKeys
     * @return array<int, string>
     */
    private function likedKeys(?User $user, array $postKeys): array
    {
        if (! $user || ! Schema::hasTable('community_post_reactions')) {
            return [];
        }

        return CommunityPostReaction::query()
            ->where('user_id', $user->id)
            ->whereIn('post_key', $postKeys)
            ->where('reaction', 'like')
            ->pluck('post_key')
            ->all();
    }

    private function persistedLikeCount(string $postKey): int
    {
        return CommunityPostReaction::query()
            ->where('post_key', $postKey)
            ->where('reaction', 'like')
            ->count();
    }

    private function persistedReplyCount(string $postKey): int
    {
        return CommunityPostReply::query()
            ->where('post_key', $postKey)
            ->where('status', 'visible')
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function pollVoteCounts(string $pollKey): array
    {
        if (! Schema::hasTable('community_poll_votes')) {
            return [];
        }

        return CommunityPollVote::query()
            ->where('poll_key', $pollKey)
            ->select('option_key')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('option_key')
            ->pluck('aggregate', 'option_key')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, int>  $values
     * @return array<int, int>
     */
    private function percentages(array $values): array
    {
        $total = array_sum($values);

        if ($total <= 0) {
            return array_fill(0, count($values), 0);
        }

        $exact = array_map(fn (int $value): float => ($value / $total) * 100, $values);
        $rounded = array_map('floor', $exact);
        $remainder = 100 - array_sum($rounded);

        collect($exact)
            ->map(fn (float $value, int $index): array => [
                'index' => $index,
                'fraction' => $value - floor($value),
            ])
            ->sortByDesc('fraction')
            ->each(function (array $value) use (&$rounded, &$remainder): void {
                if ($remainder <= 0) {
                    return;
                }

                $rounded[$value['index']] += 1;
                $remainder -= 1;
            });

        return array_map(fn ($value): int => (int) $value, $rounded);
    }

    private function normalizeKey(string $value): string
    {
        return Str::of($value)->lower()->slug('-')->limit(160, '')->toString() ?: 'community-item';
    }

    private function compactCount(int $count): string
    {
        if ($count >= 1000) {
            return rtrim(rtrim(number_format($count / 1000, 1), '0'), '.').'K';
        }

        return (string) $count;
    }
}
