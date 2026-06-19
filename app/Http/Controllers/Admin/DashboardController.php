<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessState;
use App\Enums\EditorialStatus;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Models\FanEvent;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\PointLedgerEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $contents = EditorialContent::query()
            ->with(['mediaAssets'])
            ->latest()
            ->limit(8)
            ->get();

        $queueItems = $contents->map(fn (EditorialContent $content): array => $this->contentCard($content));

        $ordersThisMonth = Order::query()
            ->where('status', 'completed')
            ->whereNull('refunded_at')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount_cents');

        return view('admin.dashboard', [
            'canPublish' => $request->user()->canPublishContent(),
            'queueItems' => $queueItems->isNotEmpty() ? $queueItems : $this->fallbackQueue(),
            'stats' => [
                'royalActive' => User::query()
                    ->whereIn('royal_status', [AccessState::RoyalActive->value, AccessState::RoyalGrace->value])
                    ->count(),
                'publishedContent' => EditorialContent::query()
                    ->where('status', EditorialStatus::Published->value)
                    ->count(),
                'scheduledContent' => EditorialContent::query()
                    ->where('status', EditorialStatus::Scheduled->value)
                    ->count(),
                'draftContent' => EditorialContent::query()
                    ->where('status', EditorialStatus::Draft->value)
                    ->count(),
                'monthlySales' => $ordersThisMonth / 100,
                'mediaAssets' => MediaAsset::query()->count(),
                'users' => User::query()->count(),
            ],
            'recentAssets' => MediaAsset::query()->latest()->limit(4)->get(),
            'recentUsers' => User::query()->latest()->limit(6)->get(),
            'upcomingEvents' => FanEvent::query()
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->limit(3)
                ->get(),
            'topFans' => PointLedgerEntry::query()
                ->with('user')
                ->where('status', 'posted')
                ->orderByDesc('balance_after')
                ->limit(5)
                ->get(),
        ]);
    }

    private function contentCard(EditorialContent $content): array
    {
        $visibility = $content->visibility->value;
        $status = $content->status->value;

        return [
            'id' => $content->id,
            'title' => $content->title,
            'summary' => $content->summary,
            'type' => str_replace('_', ' ', $content->type->value),
            'status' => $status,
            'visibility' => $visibility,
            'needsApproval' => $content->needs_approval,
            'filter' => $status === EditorialStatus::Draft->value
                ? 'borrador'
                : (in_array($visibility, ['royal', 'member', 'purchased'], true) ? 'royal' : 'publico'),
            'editUrl' => route('admin.content.edit', $content),
            'previewUrl' => route('admin.content.preview', $content),
            'timestamp' => $content->scheduled_at
                ? 'Programado para '.$content->scheduled_at->copy()->timezone(config('admin.publishing_timezone', 'America/Panama'))->format('j M Y, g:i A')
                : $content->created_at->format('j M Y, g:i A'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fallbackQueue(): Collection
    {
        return collect([
            [
                'id' => null,
                'title' => 'Detras de camaras: Grabando el nuevo album acustico',
                'summary' => 'Video Royal preparado para la comunidad premium.',
                'type' => 'video',
                'status' => 'published',
                'visibility' => 'royal',
                'needsApproval' => false,
                'filter' => 'royal',
                'editUrl' => route('admin.content.create', ['type' => 'video']),
                'previewUrl' => route('admin.content.index'),
                'timestamp' => 'Publicado recientemente',
            ],
            [
                'id' => null,
                'title' => 'Nueva cancion preview: Luces del puerto',
                'summary' => 'Audio publico listo para programacion editorial.',
                'type' => 'song',
                'status' => 'scheduled',
                'visibility' => 'open',
                'needsApproval' => false,
                'filter' => 'publico',
                'editUrl' => route('admin.content.create', ['type' => 'song']),
                'previewUrl' => route('admin.content.index'),
                'timestamp' => 'Programado para manana',
            ],
            [
                'id' => null,
                'title' => 'Galeria de fotos de la gira 2026',
                'summary' => 'Borrador pendiente de media final.',
                'type' => 'gallery',
                'status' => 'draft',
                'visibility' => 'royal',
                'needsApproval' => true,
                'filter' => 'borrador',
                'editUrl' => route('admin.content.create', ['type' => 'gallery']),
                'previewUrl' => route('admin.content.index'),
                'timestamp' => 'No publicado aun',
            ],
        ]);
    }
}
