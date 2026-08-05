<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Services\Admin\MusicContentUploadService;
use App\Services\Commerce\CommercePublicationValidator;
use App\Services\EditorialWorkflowService;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaUploadException;
use App\Support\EditorialContentForms;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EditorialActionController extends Controller
{
    private const SCHEDULING_TIMEZONE = 'America/Panama';

    private const STORAGE_TIMEZONE = 'UTC';

    public function __construct(
        private readonly EditorialContentForms $forms,
        private readonly MusicContentUploadService $musicContent
    ) {}

    public function index(Request $request): View
    {
        return view('admin.editorial.index', $this->editorialViewData($request));
    }

    public function edit(Request $request, EditorialContent $content): View
    {
        return view('admin.editorial.index', $this->editorialViewData($request, $content));
    }

    public function preview(Request $request, EditorialContent $content): Response
    {
        $audience = (string) $request->query('audience', VisibilityAudience::Open->value);

        if (! in_array($audience, VisibilityAudience::values(), true)) {
            $audience = VisibilityAudience::Open->value;
        }

        $content->load([
            'mediaAssets',
            'releaseWindows',
            'auditLogs' => fn ($query) => $query->with('actor:id,name,email')->latest('created_at'),
        ]);

        return response()->view('admin.editorial.preview', [
            'content' => $content,
            'panamaTimezone' => self::SCHEDULING_TIMEZONE,
            'previewAudience' => $audience,
            'previewAudiences' => VisibilityAudience::cases(),
        ])->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'no-store, private');
    }

    public function saveDraft(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request);

        try {
            $payload = $this->musicContent->payloadWithUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

        $content = $workflow->createDraft($request->user(), $payload);

        $message = sprintf(
            'Draft "%s" saved for approval.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return back()->with('status', $message);
    }

    public function updateDraft(
        Request $request,
        EditorialContent $content,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true, false, $content);

        try {
            $payload = $this->musicContent->payloadWithUploads($request, $library, $payload, $content);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

        $content = $workflow->updateDraft($request->user(), $content, $payload);

        $message = sprintf(
            'Draft "%s" updated.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return redirect()
            ->route('admin.editorial.edit', $content)
            ->with('status', $message);
    }

    public function publish(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true);
        $existingContent = isset($payload['content_id'])
            ? EditorialContent::query()->findOrFail($payload['content_id'])
            : null;
        $payload = app(CommercePublicationValidator::class)->prepareAndValidate($payload, $existingContent);

        try {
            $payload = $this->musicContent->payloadWithUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

        $content = $existingContent
            ? $workflow->publish($request->user(), $existingContent, $payload)
            : $workflow->publishNew($request->user(), $payload);

        $message = sprintf(
            'Content "%s" approved and published.',
            $content->title
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return back()->with('status', $message);
    }

    public function schedule(
        Request $request,
        EditorialWorkflowService $workflow,
        MediaLibraryService $library
    ): JsonResponse|RedirectResponse {
        $payload = $this->validatedPayload($request, true, scheduledAtRequired: true);
        $scheduledAt = $payload['scheduled_at'];
        $releaseWindows = $payload['release_windows'] ?? [];
        $existingContent = isset($payload['content_id'])
            ? EditorialContent::query()->findOrFail($payload['content_id'])
            : null;
        $payload = app(CommercePublicationValidator::class)->prepareAndValidate($payload, $existingContent);

        try {
            $payload = $this->musicContent->payloadWithUploads($request, $library, $payload);
        } catch (MediaUploadException $exception) {
            return $this->mediaUploadFailure($request, $exception);
        }

        $content = $existingContent
            ? $workflow->schedule(
                $request->user(),
                $existingContent,
                $scheduledAt,
                $releaseWindows,
                $payload
            )
            : $workflow->scheduleNew($request->user(), $payload, $scheduledAt);

        $message = sprintf(
            'Content "%s" scheduled for %s Panama time.',
            $content->title,
            $content->scheduled_at?->timezone(self::SCHEDULING_TIMEZONE)->format('M j, Y g:i A')
        );

        if ($request->expectsJson()) {
            return response()->json($this->responsePayload($content, $message));
        }

        return redirect()
            ->route('admin.editorial.edit', $content)
            ->with('status', $message);
    }

    private function validatedPayload(
        Request $request,
        bool $allowExistingContent = false,
        bool $scheduledAtRequired = false,
        ?EditorialContent $content = null
    ): array {
        $rules = [
            'content_id' => [
                $allowExistingContent ? 'nullable' : 'prohibited',
                'integer',
                Rule::exists('editorial_contents', 'id'),
            ],
            'type' => ['nullable', Rule::in(ContentType::values())],
            'title' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::in(VisibilityAudience::values())],
            'purchase_key' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => [$scheduledAtRequired ? 'required' : 'nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'media_asset_ids' => ['nullable', 'array'],
            'media_asset_ids.*' => ['integer', Rule::exists('media_assets', 'id')],
            'release_windows' => ['nullable', 'array'],
            'release_windows.*.audience' => ['required_with:release_windows', Rule::in(VisibilityAudience::values())],
            'release_windows.*.starts_at' => ['nullable', 'date'],
            'release_windows.*.ends_at' => ['nullable', 'date'],
            'release_windows.*.country_codes' => ['nullable', 'array'],
            'release_windows.*.country_codes.*' => ['string', 'size:2'],
        ];

        $type = $this->contentType($request, $content);

        if ($type instanceof ContentType) {
            $rules = [
                ...$rules,
                ...$this->validationRulesFor($type),
            ];
        }

        $payload = $this->normalizePayload($request->validate($rules), $type);

        $this->musicContent->validate($request, $payload, $type);

        if (($payload['type'] ?? null) === ContentType::Poll->value && count($payload['metadata']['options'] ?? []) < 2) {
            throw ValidationException::withMessages([
                'metadata.options' => 'Polls require at least two options.',
            ]);
        }

        return $this->musicContent->payloadWithReleaseWindows($payload, $type);
    }

    private function contentType(Request $request, ?EditorialContent $content = null): ?ContentType
    {
        if ($request->filled('type')) {
            return ContentType::tryFrom((string) $request->input('type'));
        }

        return $content?->type instanceof ContentType
            ? $content->type
            : ContentType::tryFrom((string) $content?->type);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function validationRulesFor(ContentType $type): array
    {
        if ($this->musicContent->isMusicType($type)) {
            return $this->musicContent->rulesFor($type, requireBaseFields: true);
        }

        return $this->forms->validationRules($type);
    }

    private function responsePayload(EditorialContent $content, string $message): array
    {
        return [
            'id' => $content->id,
            'message' => $message,
            'type' => $content->type->value,
            'status' => $content->status->value,
            'visibility' => $content->visibility->value,
            'needs_approval' => $content->needs_approval,
            'scheduled_at' => $content->scheduled_at?->timezone(self::SCHEDULING_TIMEZONE)->toISOString(),
            'preview_url' => route('admin.editorial.preview', $content),
        ];
    }

    private function normalizePayload(array $payload, ContentType|string|null $type = null): array
    {
        if (isset($payload['metadata'])) {
            $payload['metadata'] = $this->cleanMetadata($payload['metadata']);

            if ($this->musicContent->isMusicType($type)) {
                $payload['metadata'] = $this->musicContent->normalizeMetadataForType($payload['metadata'], $type);
            }

            $resolvedType = $type instanceof ContentType ? $type : ContentType::tryFrom((string) $type);

            if (in_array($resolvedType, [ContentType::Product, ContentType::Event], true)) {
                $metadata = $payload['metadata'];
                $price = $metadata['price'] ?? null;

                if (is_numeric($price) && ! isset($metadata['price_cents'])) {
                    $metadata['price_cents'] = (int) round(((float) $price) * 100);
                }

                if ($resolvedType === ContentType::Product) {
                    $metadata['product_kind'] = $metadata['product_kind'] ?? $metadata['product_type'] ?? 'digital';
                } else {
                    $metadata['event_kind'] = $metadata['event_kind'] ?? $metadata['event_type'] ?? 'physical';
                    $metadata['starts_at'] = $metadata['starts_at'] ?? $metadata['event_starts_at'] ?? null;
                    $metadata['location'] = $metadata['location'] ?? $metadata['venue'] ?? null;
                    $metadata['ticketing_mode'] = $metadata['ticketing_mode'] ?? ((float) ($price ?? 0) > 0 ? 'ticket' : 'rsvp');
                }

                $ticketingMode = (string) ($metadata['ticketing_mode'] ?? 'ticket');
                $metadata['currency'] = strtoupper((string) ($metadata['currency'] ?? 'USD'));
                $metadata['checkout_enabled'] = array_key_exists('checkout_enabled', $metadata)
                    ? filter_var($metadata['checkout_enabled'], FILTER_VALIDATE_BOOL)
                    : true;
                $metadata['action_type'] = (string) ($metadata['action_type'] ?? ($ticketingMode === 'rsvp' ? 'rsvp' : 'buy'));
                $metadata['cta_label'] = (string) ($metadata['cta_label'] ?? ($ticketingMode === 'rsvp' ? 'RSVP' : ($resolvedType === ContentType::Event ? 'BUY TICKETS' : 'BUY NOW')));
                $payload['metadata'] = $metadata;

                if ($resolvedType === ContentType::Event && blank($payload['purchase_key'] ?? null)) {
                    $payload['purchase_key'] = Str::slug((string) ($payload['slug'] ?? $payload['title'] ?? 'event'));
                }
            }
        }

        if (array_key_exists('scheduled_at', $payload) && $payload['scheduled_at'] !== null) {
            $payload['scheduled_at'] = CarbonImmutable::parse(
                $payload['scheduled_at'],
                self::SCHEDULING_TIMEZONE
            )->setTimezone(self::STORAGE_TIMEZONE);
        }

        $payload['release_windows'] = collect($payload['release_windows'] ?? [])
            ->filter(fn (array $window): bool => filled($window['starts_at'] ?? null) || filled($window['ends_at'] ?? null))
            ->map(function (array $window): array {
                foreach (['starts_at', 'ends_at'] as $key) {
                    if (filled($window[$key] ?? null)) {
                        $window[$key] = CarbonImmutable::parse($window[$key], self::SCHEDULING_TIMEZONE)
                            ->setTimezone(self::STORAGE_TIMEZONE);
                    }
                }

                return $window;
            })
            ->values()
            ->all();

        $payload['media_assets'] = collect($payload['media_asset_ids'] ?? [])
            ->unique()
            ->values()
            ->map(fn (int|string $assetId, int $index): array => [
                'id' => (int) $assetId,
                'role' => $index === 0 ? 'primary' : 'supporting',
                'sort_order' => $index,
            ])
            ->all();

        unset($payload['media_asset_ids']);

        return $payload;
    }

    private function cleanMetadata(array $metadata): array
    {
        return collect($metadata)
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return $this->cleanMetadata($value);
                }

                return is_string($value) ? trim($value) : $value;
            })
            ->reject(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value === [];
                }

                return $value === null || $value === '';
            })
            ->all();
    }

    private function mediaUploadFailure(Request $request, MediaUploadException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return back()->withErrors(['media' => $exception->getMessage()])->withInput();
    }

    /**
     * @return array<string, mixed>
     */
    private function editorialViewData(Request $request, ?EditorialContent $content = null): array
    {
        $selectedType = $content?->type
            ?? ContentType::tryFrom((string) $request->query('type'))
            ?? ContentType::Post;

        $content?->load(['mediaAssets', 'releaseWindows']);

        $releaseWindows = collect(VisibilityAudience::cases())
            ->mapWithKeys(function (VisibilityAudience $audience) use ($content): array {
                $window = $content?->releaseWindows->first(
                    fn ($window): bool => $window->audience === $audience
                );

                return [$audience->value => [
                    'audience' => $audience->value,
                    'starts_at' => $window?->starts_at?->timezone(self::SCHEDULING_TIMEZONE)->format('Y-m-d\TH:i'),
                    'ends_at' => $window?->ends_at?->timezone(self::SCHEDULING_TIMEZONE)->format('Y-m-d\TH:i'),
                ]];
            })
            ->all();

        return [
            'content' => $content,
            'contents' => EditorialContent::query()
                ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
                ->latest()
                ->limit(50)
                ->get(),
            'forms' => $this->forms->definitions(),
            'mediaAssets' => MediaAsset::query()->ready()->latest()->limit(100)->get(),
            'panamaTimezone' => self::SCHEDULING_TIMEZONE,
            'releaseWindows' => $releaseWindows,
            'selectedDefinition' => $this->forms->definition($selectedType),
            'selectedMediaIds' => $content?->mediaAssets->pluck('id')->all() ?? [],
            'selectedType' => $selectedType,
        ];
    }
}
