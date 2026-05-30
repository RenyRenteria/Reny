<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Single;
use App\Models\SiteHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_displays_saved_site_content(): void
    {
        SiteHero::query()->create([
            'title' => 'New Era',
            'subtitle' => 'Live now',
            'body' => 'Fresh release notes.',
        ]);

        Album::query()->create([
            'title' => 'Road Sessions',
            'track_count' => 9,
            'cover_label' => 'Road',
            'is_published' => true,
        ]);

        Single::query()->create([
            'title' => 'Midnight Drop',
            'artist' => 'Reny Renteria',
            'is_published' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('New Era')
            ->assertSee('Live now')
            ->assertSee('Road Sessions')
            ->assertSee('Midnight Drop');
    }

    public function test_admin_dashboard_requires_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_update_hero_content(): void
    {
        config(['admin.password' => 'secret']);

        $this->post('/admin/login', ['password' => 'secret'])
            ->assertRedirect('/admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Content admin');

        $this->put('/admin/hero', [
            'title' => 'Admin Launch',
            'subtitle' => 'Updated from panel',
            'body' => 'Editable site content.',
        ])->assertRedirect();

        $this->assertDatabaseHas('site_heroes', [
            'title' => 'Admin Launch',
            'subtitle' => 'Updated from panel',
        ]);
    }

    public function test_admin_can_upload_single_audio(): void
    {
        Storage::fake('public');
        config(['admin.password' => 'secret']);

        $this->post('/admin/login', ['password' => 'secret']);

        $this->post('/admin/singles', [
            'title' => 'Uploaded Track',
            'artist' => 'Reny Renteria',
            'audio_file' => UploadedFile::fake()->create('uploaded-track.mp3', 128, 'audio/mpeg'),
            'is_published' => '1',
        ])->assertRedirect();

        $single = Single::query()->where('title', 'Uploaded Track')->firstOrFail();

        $this->assertNotNull($single->audio_path);
        Storage::disk('public')->assertExists($single->audio_path);

        $this->get('/')
            ->assertOk()
            ->assertSee('Uploaded Track')
            ->assertSee('/storage/'.$single->audio_path);
    }
}
