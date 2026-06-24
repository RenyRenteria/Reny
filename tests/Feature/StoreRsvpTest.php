<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\FanEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_confirm_free_store_rsvp(): void
    {
        $user = User::factory()->create([
            'name' => 'RSVP Fan',
        ]);

        $response = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'concert',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('event.name', 'Reny Renteria en Concierto')
            ->assertJsonPath('ticket.status', 'reserved')
            ->assertJsonPath('ticket.rsvp_status', 'confirmed');

        $event = FanEvent::where('title', 'Reny Renteria en Concierto')->firstOrFail();
        $ticket = Ticket::where('user_id', $user->id)->where('event_id', $event->id)->firstOrFail();

        $this->assertSame('store_rsvp', $event->metadata['source']);
        $this->assertSame('concert', $event->metadata['store_event_key']);
        $this->assertSame('reserved', $ticket->status);
        $this->assertSame('confirmed', $ticket->rsvp_status);
        $this->assertNull($ticket->order_id);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('TKT-'.$ticket->id.'-', false);
    }

    public function test_store_rsvp_is_idempotent_for_same_user_and_event(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertOk();

        $second = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertOk();

        $this->assertSame($first->json('ticket.id'), $second->json('ticket.id'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_store_rsvp_event_can_be_resolved_from_versioned_config(): void
    {
        config()->set('reny_catalog.rsvp_events.config-night', [
            'title' => 'Configured RSVP Night',
            'venue' => 'Config Venue',
            'address' => 'Config Address',
            'starts_at' => '2026-10-05 20:00:00',
            'timezone' => 'America/Panama',
        ]);

        $user = User::factory()->create([
            'name' => 'Config Fan',
        ]);

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'config-night',
            ])
            ->assertOk()
            ->assertJsonPath('event.name', 'Configured RSVP Night')
            ->assertJsonPath('event.venue', 'Config Venue');

        $this->assertDatabaseHas('events', [
            'title' => 'Configured RSVP Night',
            'venue' => 'Config Venue',
        ]);

        $event = FanEvent::where('title', 'Configured RSVP Night')->firstOrFail();

        $this->assertSame('store_rsvp', $event->metadata['source']);
        $this->assertSame('config-night', $event->metadata['store_event_key']);
    }

    public function test_cms_rsvp_event_takes_precedence_over_configured_event_with_same_key(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'CMS RSVP Concert',
            'slug' => 'cms-rsvp-concert',
            'purchase_key' => 'concert',
            'metadata' => [
                'ticketing_mode' => 'rsvp',
                'starts_at' => '2026-10-01 18:30:00',
                'location' => 'CMS Venue',
                'address' => 'CMS Address',
                'timezone' => 'America/Panama',
            ],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'concert',
            ])
            ->assertOk()
            ->assertJsonPath('event.name', 'CMS RSVP Concert')
            ->assertJsonPath('event.venue', 'CMS Venue');

        $this->assertDatabaseHas('events', [
            'title' => 'CMS RSVP Concert',
            'venue' => 'CMS Venue',
        ]);
        $this->assertDatabaseMissing('events', [
            'title' => 'Reny Renteria en Concierto',
        ]);
    }

    public function test_cms_checkout_event_blocks_rsvp_config_fallback_with_same_key(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'CMS Paid Concert',
            'slug' => 'cms-paid-concert',
            'purchase_key' => 'concert',
            'metadata' => [
                'ticketing_mode' => 'ticket',
                'starts_at' => '2026-10-01 18:30:00',
                'location' => 'CMS Venue',
                'price_cents' => 2400,
            ],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'concert',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_empty_or_invalid_rsvp_config_returns_validation_without_creating_ticket(): void
    {
        config()->set('reny_catalog.rsvp_events', [
            'missing-start' => [
                'title' => 'Missing Start',
                'venue' => 'Config Venue',
            ],
            'bad-date' => [
                'title' => 'Bad Date',
                'venue' => 'Config Venue',
                'starts_at' => 'not-a-date',
            ],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'missing-start',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'bad-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        config()->set('reny_catalog.rsvp_events', []);

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'making',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_guest_rsvp_requires_login_and_does_not_create_ticket(): void
    {
        $this->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_unknown_store_rsvp_event_returns_retryable_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'missing-event',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        $this->assertDatabaseCount('tickets', 0);
    }
}
