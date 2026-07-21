<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\CommunityCountryClub;
use App\Models\CommunityCountryClubMembership;
use App\Models\CommunityCountryClubMessage;
use App\Models\CommunityPollVote;
use App\Models\CommunityPostReaction;
use App\Models\CommunityPostReply;
use App\Models\CommunityUserBlock;
use App\Models\EditorialContent;
use App\Models\User;
use App\Support\CommunityPostContent;
use App\Support\EntitlementMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityInteractionService
{
    private const LIVE_CHAT_KEY = 'official-live-chat';

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
            'live_chat' => $this->liveChat($user),
            'can_use_actions' => EntitlementMatrix::canUseRoyalFeature($user),
            'can_use_post_actions' => $user !== null,
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
     * @return array<string, mixed>
     */
    public function liveChat(?User $user): array
    {
        return [
            'messages' => $this->liveChatMessages($user),
            'pinned_message' => $this->stringValue(config('reny_catalog.community.live_chat.pinned_message'))
                ?? 'Bienvenidos al chat oficial. Sé amable y mantén la conversación segura.',
            'messages_endpoint' => route('community.live-chat.messages.index'),
            'message_endpoint' => route('community.live-chat.messages.store'),
            'current_user_id' => $user?->id,
            'can_moderate' => $this->canModerate($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function liveChatMessages(?User $viewer): array
    {
        if (! Schema::hasTable('community_country_club_messages')) {
            return [];
        }

        $blockedUserIds = $viewer && Schema::hasTable('community_user_blocks')
            ? CommunityUserBlock::query()
                ->where('blocker_id', $viewer->id)
                ->pluck('blocked_id')
                ->all()
            : [];

        return CommunityCountryClubMessage::query()
            ->where('status', 'visible')
            ->whereHas('club', fn ($query) => $query->where('key', self::LIVE_CHAT_KEY))
            ->when($blockedUserIds !== [], fn ($query) => $query->whereNotIn('user_id', $blockedUserIds))
            ->with(['club:id,key', 'user:id,name,username,role'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (CommunityCountryClubMessage $message): array => $this->liveChatMessagePayload($message, $viewer))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createLiveChatMessage(User $user, string $body): array
    {
        $club = CommunityCountryClub::query()->firstOrCreate([
            'key' => self::LIVE_CHAT_KEY,
        ], [
            'name' => 'Live Chat',
            'flag_label' => 'LIVE',
            'activity' => 'Official community live chat',
            'status' => 'active',
            'metadata' => ['source' => 'official_live_chat'],
        ]);

        $message = CommunityCountryClubMessage::create([
            'community_country_club_id' => $club->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => 'visible',
        ])->load(['club:id,key', 'user:id,name,username,role']);

        return [
            'chat_message' => $this->liveChatMessagePayload($message, $user),
            'message' => 'Mensaje enviado.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blockLiveChatUser(User $blocker, User $blocked): array
    {
        if ($blocker->is($blocked)) {
            throw ValidationException::withMessages([
                'user' => 'No puedes bloquear tu propia cuenta.',
            ]);
        }

        CommunityUserBlock::query()->firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
        ]);

        return [
            'blocked_user_id' => $blocked->id,
            'message' => 'Usuario bloqueado.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moderateLiveChatMessage(CommunityCountryClubMessage $message): array
    {
        $message->loadMissing('club:id,key');
        abort_unless($message->club?->key === self::LIVE_CHAT_KEY, 404);

        $message->update(['status' => 'removed']);

        return [
            'removed_message_id' => $message->id,
            'message' => 'Mensaje ocultado.',
        ];
    }

    public function canModerate(?User $user): bool
    {
        return $user && in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ARTIST_ADMIN,
            User::ROLE_MODERATOR,
        ], true);
    }

    /**
     * @return array{liked: bool, count: int}
     */
    public function toggleLike(User $user, string $postKey): array
    {
        $postKey = $this->normalizeKey($postKey);
        $this->assertPostExists($user, $postKey);

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
        $postKey = $this->normalizeKey($postKey);
        $post = $this->assertPostExists($user, $postKey);

        if (! (bool) ($post['comments_enabled'] ?? true)) {
            throw ValidationException::withMessages([
                'body' => 'Los comentarios están desactivados para este post.',
            ]);
        }

        $reply = CommunityPostReply::create([
            'user_id' => $user->id,
            'post_key' => $postKey,
            'body' => $body,
            'status' => 'visible',
            'metadata' => ['source' => 'community'],
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return [
            'id' => $reply->id,
            'body' => $reply->body,
            'author' => $user->name,
            'time' => 'ahora',
            'reply_count' => $this->persistedReplyCount($reply->post_key),
            'message' => 'Comentario publicado.',
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
        $sourcePosts = collect($cmsPosts ?: $this->fallbackPosts())
            ->filter(fn (mixed $post): bool => is_array($post))
            ->values()
            ->map(function (array $post, int $index): array {
                $title = $this->stringValue($post['title'] ?? null) ?? 'Community post '.($index + 1);
                $body = $this->stringValue($post['body'] ?? null) ?? '';
                $key = $this->stringValue($post['key'] ?? null) ?? $this->normalizeKey($title);
                $bodyHtml = $this->stringValue($post['body_html'] ?? null)
                    ?? $this->stringValue($post['full_body'] ?? null)
                    ?? $body;

                return [
                    'key' => $key,
                    'title' => $title,
                    'time' => $this->stringValue($post['time'] ?? null) ?? 'Published',
                    'body_html' => CommunityPostContent::sanitize($bodyHtml),
                    'image_url' => $this->stringValue($post['image_url'] ?? null),
                    'image_alt' => $this->stringValue($post['image_alt'] ?? null) ?? $title,
                    'media_items' => CommunityPostContent::normalizeMediaUrls(
                        is_array($post['media_items'] ?? null) ? $post['media_items'] : []
                    ),
                    'comments_enabled' => (bool) ($post['comments_enabled'] ?? true),
                    'base_likes' => is_numeric($post['base_likes'] ?? null) ? (int) $post['base_likes'] : 0,
                    'base_replies' => is_numeric($post['base_replies'] ?? null) ? (int) $post['base_replies'] : 0,
                ];
            });

        $keys = $sourcePosts->pluck('key')->all();
        $likeCounts = $this->reactionCounts($keys);
        $replyCounts = $this->replyCounts($keys);
        $replies = $this->visibleReplies($keys);
        $likedKeys = $this->likedKeys($user, $keys);

        return $sourcePosts
            ->map(fn (array $post): array => [
                ...$post,
                'like_count' => $post['base_likes'] + (int) ($likeCounts[$post['key']] ?? 0),
                'reply_count' => $post['base_replies'] + (int) ($replyCounts[$post['key']] ?? 0),
                'replies' => $replies[$post['key']] ?? [],
                'liked' => in_array($post['key'], $likedKeys, true),
                'like_endpoint' => route('community.posts.like', $post['key']),
                'reply_endpoint' => route('community.posts.replies.store', $post['key']),
                'share_url' => url('/community').'#'.$post['key'],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function assertPostExists(User $user, string $postKey): array
    {
        if (preg_match('/^cms-post-(\d+)$/', $postKey, $matches)) {
            $content = EditorialContent::query()
                ->whereKey((int) $matches[1])
                ->where('type', ContentType::Post->value)
                ->visibleFor($user)
                ->first();

            abort_unless($content, 404);

            return [
                'comments_enabled' => (bool) data_get($content->metadata ?? [], 'comments_enabled', true),
            ];
        }

        $fallback = collect($this->fallbackPosts())
            ->filter(fn (mixed $post): bool => is_array($post))
            ->first(fn (array $post): bool => $this->normalizeKey((string) ($post['key'] ?? $post['title'] ?? '')) === $postKey);

        abort_unless(is_array($fallback), 404);

        return $fallback;
    }

    /**
     * @param  array<string, mixed>|null  $cmsPoll
     * @return array<string, mixed>|null
     */
    private function poll(?User $user, ?array $cmsPoll): ?array
    {
        $source = $cmsPoll ?: $this->fallbackPoll();

        if ($source === []) {
            return null;
        }

        $question = $this->stringValue($source['question'] ?? null) ?? 'Fan vote';
        $pollKey = $this->normalizeKey($this->stringValue($source['key'] ?? null) ?? $question);
        $voteCounts = $this->pollVoteCounts($pollKey);
        $userVote = $user && Schema::hasTable('community_poll_votes')
            ? CommunityPollVote::query()
                ->where('user_id', $user->id)
                ->where('poll_key', $pollKey)
                ->first()
            : null;

        $options = collect($source['options'] ?? [])
            ->filter(fn (mixed $option): bool => is_array($option))
            ->values()
            ->map(function (array $option, int $index) use ($pollKey, $userVote, $voteCounts): array {
                $label = $this->stringValue($option['label'] ?? null) ?? 'Option '.($index + 1);
                $optionKey = $this->normalizeKey($this->stringValue($option['key'] ?? null) ?? $label);
                $votes = $option['votes'] ?? $option['count'] ?? $option['percent'] ?? null;
                $baseVotes = is_numeric($votes) ? (int) $votes : 0;

                return [
                    'key' => $optionKey,
                    'label' => $label,
                    'votes' => $baseVotes + (int) ($voteCounts[$optionKey] ?? 0),
                    'selected' => $userVote?->option_key === $optionKey,
                    'vote_endpoint' => route('community.polls.vote', $pollKey),
                ];
            });

        if ($options->isEmpty()) {
            return null;
        }

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
                ->where('key', '!=', self::LIVE_CHAT_KEY)
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

        $defaultClubs = $this->defaultClubs();
        $defaultKeys = collect($defaultClubs)->pluck('key')->all();

        return collect($defaultClubs)
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
        $key = $this->normalizeKey((string) ($source['key'] ?? $source['name']));
        $baseMessages = collect($source['messages'] ?? [])
            ->filter(fn (mixed $message): bool => is_array($message))
            ->map(fn (array $message): array => [
                'author' => $this->stringValue($message['author'] ?? null) ?? 'Member',
                'text' => $this->stringValue($message['text'] ?? null) ?? '',
            ])
            ->filter(fn (array $message): bool => $message['text'] !== '');
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
            'name' => $this->stringValue($club?->name) ?? $this->stringValue($source['name'] ?? null) ?? 'Country club',
            'flag_label' => $this->stringValue($club?->flag_label) ?? $this->stringValue($source['flag_label'] ?? null) ?? strtoupper(substr($key, 0, 2)),
            'activity' => $this->stringValue($club?->activity) ?? $this->stringValue($source['activity'] ?? null) ?? 'New country club',
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
        $default = collect($this->defaultClubs())->firstWhere('key', $key);

        if (! $default && ! CommunityCountryClub::query()->where('key', $key)->exists()) {
            abort(404);
        }

        return CommunityCountryClub::query()->firstOrCreate([
            'key' => $key,
        ], [
            'name' => $this->stringValue($default['name'] ?? null) ?? str($key)->replace('-', ' ')->headline()->toString(),
            'flag_label' => $this->stringValue($default['flag_label'] ?? null) ?? strtoupper(substr($key, 0, 2)),
            'activity' => $this->stringValue($default['activity'] ?? null) ?? 'New country club',
            'status' => 'active',
            'metadata' => ['source' => $default ? 'community_default' : 'community'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function liveChatMessagePayload(CommunityCountryClubMessage $message, ?User $viewer): array
    {
        $author = $message->user?->name ?? $message->user?->username ?? 'Miembro';
        $canBlock = $viewer
            && $message->user_id !== $viewer->id
            && EntitlementMatrix::canUseRoyalFeature($viewer);

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author' => $author,
            'initials' => $this->initials($author),
            'text' => $message->body,
            'time' => $message->created_at?->diffForHumans() ?? 'ahora',
            'is_self' => $viewer?->id === $message->user_id,
            'is_host' => $message->user?->isStaff() ?? false,
            'block_endpoint' => $canBlock ? route('community.live-chat.users.block', $message->user_id) : null,
            'moderation_endpoint' => $this->canModerate($viewer)
                ? route('community.live-chat.messages.moderate', $message)
                : null,
        ];
    }

    private function initials(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('') ?: 'R';
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function visibleReplies(array $postKeys): array
    {
        if ($postKeys === [] || ! Schema::hasTable('community_post_replies')) {
            return [];
        }

        return CommunityPostReply::query()
            ->whereIn('post_key', $postKeys)
            ->where('status', 'visible')
            ->with('user:id,name,username')
            ->oldest()
            ->limit(250)
            ->get()
            ->groupBy('post_key')
            ->map(fn ($replies): array => $replies
                ->take(20)
                ->map(fn (CommunityPostReply $reply): array => [
                    'id' => $reply->id,
                    'author' => $reply->user?->name ?? $reply->user?->username ?? 'Miembro',
                    'body' => $reply->body,
                    'time' => $reply->created_at?->diffForHumans() ?? 'ahora',
                ])
                ->values()
                ->all())
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackPosts(): array
    {
        $posts = config('reny_catalog.community.posts', []);

        return is_array($posts) ? $posts : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackPoll(): array
    {
        $poll = config('reny_catalog.community.poll', []);

        return is_array($poll) ? $poll : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultClubs(): array
    {
        $clubs = config('reny_catalog.community.clubs', []);

        if (! is_array($clubs)) {
            return [];
        }

        return collect($clubs)
            ->filter(fn (mixed $club): bool => is_array($club))
            ->map(function (array $club): array {
                $key = $this->stringValue($club['key'] ?? null)
                    ?? $this->stringValue($club['name'] ?? null);

                return [
                    ...$club,
                    'key' => $this->normalizeKey($key ?? ''),
                ];
            })
            ->filter(fn (array $club): bool => $club['key'] !== 'community-item')
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
