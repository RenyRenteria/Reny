<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
use App\Models\CommunityCountryClub;
use App\Models\CommunityCountryClubMembership;
use App\Models\CommunityCountryClubMessage;
use App\Models\CommunityPollVote;
use App\Models\CommunityPostReaction;
use App\Models\CommunityPostReply;
use App\Models\CommunityUserBlock;
use App\Models\CommunityVideoView;
use App\Models\EditorialContent;
use App\Models\User;
use App\Support\CommunityPostContent;
use App\Support\EntitlementMatrix;
use Illuminate\Support\Carbon;
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
            'poll' => $this->poll(
                $user,
                $publicCms['poll'] ?? null,
                ! in_array($publicCms['_cms_source'] ?? 'static', ['cms', 'cache', 'preview'], true),
            ),
            'clubs' => $clubs,
            'active_club' => $clubs[0] ?? null,
            'live_chat' => $this->liveChat($user),
            'can_use_actions' => EntitlementMatrix::canUseRoyalFeature($user),
            'can_use_post_actions' => EntitlementMatrix::canUseRoyalFeature($user),
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
            ->with(['club:id,key', 'user:id,name,username,role,avatar_path'])
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
        ])->load(['club:id,key', 'user:id,name,username,role,avatar_path']);

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
     * @param  array<int, array<string, mixed>>  $cmsPosts
     * @return array{counted: bool, view_count: int}
     */
    public function recordVideoView(
        ?User $user,
        string $sessionId,
        string $postKey,
        string $videoKey,
        array $cmsPosts,
    ): array {
        $post = collect($this->posts($user, $cmsPosts))->firstWhere('key', $postKey);
        $video = collect($post['media_items'] ?? [])->first(
            fn (mixed $media): bool => is_array($media)
                && ($media['type'] ?? null) === 'video'
                && ($media['view_key'] ?? null) === $videoKey
        );

        abort_unless(is_array($post) && is_array($video), 404);

        if (! Schema::hasTable('community_video_views')) {
            return ['counted' => false, 'view_count' => 0];
        }

        abort_if($user === null && $sessionId === '', 419, 'Session expired.');

        $viewerIdentity = $user
            ? 'user:'.$user->getKey()
            : 'session:'.$sessionId;
        $view = CommunityVideoView::query()->firstOrCreate([
            'video_key' => $videoKey,
            'viewer_key' => hash_hmac('sha256', $viewerIdentity, (string) config('app.key')),
        ], [
            'post_key' => $postKey,
            'user_id' => $user?->getKey(),
        ]);

        return [
            'counted' => $view->wasRecentlyCreated,
            'view_count' => CommunityVideoView::query()
                ->where('video_key', $videoKey)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVote(User $user, string $pollKey, string $optionKey, ?string $optionLabel = null): array
    {
        $pollKey = $this->normalizeKey($pollKey);
        $optionKey = $this->normalizeKey($optionKey);
        $cmsPoll = $this->cmsPollForVote($user, $pollKey);

        if ($cmsPoll instanceof EditorialContent) {
            $this->assertCmsPollEligibility($cmsPoll, $user);
            $optionLabel = $this->canonicalCmsPollOption($cmsPoll, $optionKey);
        } else {
            $optionLabel = $this->canonicalFallbackPollOption($user, $pollKey, $optionKey);
        }

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

    private function cmsPollForVote(User $user, string $pollKey): ?EditorialContent
    {
        if (! str_starts_with($pollKey, 'cms-poll-')) {
            return null;
        }

        $contentId = (int) Str::after($pollKey, 'cms-poll-');
        $poll = EditorialContent::query()
            ->visibleFor($user)
            ->whereKey($contentId)
            ->where('type', ContentType::Poll->value)
            ->first();

        abort_unless($poll, 404);

        return $poll;
    }

    private function assertCmsPollEligibility(EditorialContent $poll, User $user): void
    {
        $eligibility = VisibilityAudience::tryFrom((string) data_get($poll->metadata, 'eligibility'))
            ?? VisibilityAudience::Royal;
        $closesAt = data_get($poll->metadata, 'closes_at');

        if (filled($closesAt) && Carbon::parse((string) $closesAt)->lte(now())) {
            throw ValidationException::withMessages(['poll' => 'This poll is closed.']);
        }

        if (! $poll->audienceAllows($eligibility, $user)) {
            throw ValidationException::withMessages(['poll' => 'Your account is not eligible to vote in this poll.']);
        }
    }

    private function canonicalCmsPollOption(EditorialContent $poll, string $optionKey): string
    {
        $option = collect(data_get($poll->metadata, 'options', []))
            ->values()
            ->map(fn (mixed $label, int $index): array => [
                'key' => 'option-'.($index + 1),
                'label' => trim((string) $label),
            ])
            ->firstWhere('key', $optionKey);

        if (! is_array($option) || $option['label'] === '') {
            throw ValidationException::withMessages(['option_key' => 'Choose a valid poll option.']);
        }

        return $option['label'];
    }

    private function canonicalFallbackPollOption(User $user, string $pollKey, string $optionKey): string
    {
        if (! EntitlementMatrix::canUseRoyalFeature($user)) {
            throw ValidationException::withMessages(['poll' => 'Royal Pass is required to vote in this poll.']);
        }

        $poll = $this->fallbackPoll();
        $configuredKey = $this->normalizeKey((string) ($poll['key'] ?? $poll['question'] ?? ''));
        abort_unless($poll !== [] && $configuredKey === $pollKey, 404);

        $option = collect($poll['options'] ?? [])->first(function (mixed $option) use ($optionKey): bool {
            return is_array($option)
                && $this->normalizeKey((string) ($option['key'] ?? $option['label'] ?? '')) === $optionKey;
        });

        if (! is_array($option)) {
            throw ValidationException::withMessages(['option_key' => 'Choose a valid poll option.']);
        }

        return (string) ($option['label'] ?? $optionKey);
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
        $hostAvatarUrl = $this->communityHostAvatarUrl();
        $sourcePosts = collect($cmsPosts ?: $this->fallbackPosts())
            ->filter(fn (mixed $post): bool => is_array($post))
            ->values()
            ->map(function (array $post, int $index) use ($hostAvatarUrl): array {
                $title = $this->stringValue($post['title'] ?? null) ?? 'Community post '.($index + 1);
                $body = $this->stringValue($post['body'] ?? null) ?? '';
                $key = $this->stringValue($post['key'] ?? null) ?? $this->normalizeKey($title);
                $imageUrl = $this->stringValue($post['image_url'] ?? null);
                $bodyHtml = $this->stringValue($post['body_html'] ?? null)
                    ?? $this->stringValue($post['full_body'] ?? null)
                    ?? $body;
                $mediaItems = collect(CommunityPostContent::normalizeMediaUrls(
                    is_array($post['media_items'] ?? null) ? $post['media_items'] : []
                ))
                    ->map(fn (array $media): array => $media['type'] === 'video' && $imageUrl && empty($media['poster_url'])
                        ? [...$media, 'poster_url' => $imageUrl]
                        : $media)
                    ->map(fn (array $media): array => $media['type'] === 'video'
                        ? [...$media, 'view_key' => $this->videoKey($key, $media['url'])]
                        : $media)
                    ->all();

                return [
                    'key' => $key,
                    'title' => $title,
                    'time' => $this->stringValue($post['time'] ?? null) ?? 'Published',
                    'avatar_url' => $hostAvatarUrl,
                    'body_html' => CommunityPostContent::sanitize($bodyHtml),
                    'image_url' => $imageUrl,
                    'image_alt' => $this->stringValue($post['image_alt'] ?? null) ?? $title,
                    'media_items' => $mediaItems,
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
        $videoViewCounts = $this->videoViewCounts(
            $sourcePosts
                ->flatMap(fn (array $post): array => collect($post['media_items'])
                    ->where('type', 'video')
                    ->pluck('view_key')
                    ->all())
                ->all()
        );

        return $sourcePosts
            ->map(function (array $post) use ($likeCounts, $replyCounts, $replies, $likedKeys, $videoViewCounts): array {
                $mediaItems = collect($post['media_items'])
                    ->map(fn (array $media): array => $media['type'] === 'video'
                        ? [
                            ...$media,
                            'view_count' => (int) ($videoViewCounts[$media['view_key']] ?? 0),
                            'view_endpoint' => route('community.posts.videos.views.store', [
                                'post' => $post['key'],
                                'video' => $media['view_key'],
                            ]),
                        ]
                        : $media)
                    ->all();

                return [
                    ...$post,
                    'media_items' => $mediaItems,
                    'like_count' => $post['base_likes'] + (int) ($likeCounts[$post['key']] ?? 0),
                    'reply_count' => $post['base_replies'] + (int) ($replyCounts[$post['key']] ?? 0),
                    'replies' => $replies[$post['key']] ?? [],
                    'liked' => in_array($post['key'], $likedKeys, true),
                    'like_endpoint' => route('community.posts.like', $post['key']),
                    'reply_endpoint' => route('community.posts.replies.store', $post['key']),
                    'share_url' => route('royals').'#'.$post['key'],
                ];
            })
            ->all();
    }

    private function communityHostAvatarUrl(): ?string
    {
        $hostEmail = $this->stringValue(config('admin.community_editor_email'));

        if ($hostEmail === null || ! Schema::hasTable('users')) {
            return null;
        }

        $avatarPath = User::query()
            ->where('email', $hostEmail)
            ->value('avatar_path');

        return filled($avatarPath) ? asset((string) $avatarPath) : null;
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
    private function poll(?User $user, ?array $cmsPoll, bool $allowFallback = true): ?array
    {
        $source = $cmsPoll ?: ($allowFallback ? $this->fallbackPoll() : []);

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
            'can_vote' => $this->canVoteInPoll($user, $source),
        ];
    }

    /** @param array<string, mixed> $source */
    private function canVoteInPoll(?User $user, array $source): bool
    {
        if (! $user) {
            return false;
        }

        if (filled($source['closes_at'] ?? null) && Carbon::parse((string) $source['closes_at'])->lte(now())) {
            return false;
        }

        $eligibility = VisibilityAudience::tryFrom((string) ($source['eligibility'] ?? 'royal'))
            ?? VisibilityAudience::Royal;

        return match ($eligibility) {
            VisibilityAudience::Open, VisibilityAudience::Member => true,
            VisibilityAudience::Royal => EntitlementMatrix::canUseRoyalFeature($user),
            VisibilityAudience::Purchased => isset($source['content_id'])
                && (EditorialContent::query()->find($source['content_id'])?->hasPurchasedAccess($user) ?? false),
        };
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
            'avatar_url' => $message->user?->avatar_path
                ? asset($message->user->avatar_path)
                : null,
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
     * @param  array<int, string>  $videoKeys
     * @return array<string, int>
     */
    private function videoViewCounts(array $videoKeys): array
    {
        if ($videoKeys === [] || ! Schema::hasTable('community_video_views')) {
            return [];
        }

        return CommunityVideoView::query()
            ->whereIn('video_key', $videoKeys)
            ->select('video_key')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('video_key')
            ->pluck('aggregate', 'video_key')
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

    private function videoKey(string $postKey, string $url): string
    {
        return 'video-'.substr(hash('sha256', $postKey."\0".$url), 0, 32);
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
