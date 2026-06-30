<?php

use App\Enums\PhotoStatus;
use App\Enums\PhotoVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('photo_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cover_photo_id')->nullable()->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('photo_albums')->nullOnDelete();
            $table->string('original_disk', 64)->nullable();
            $table->string('original_path')->nullable();
            $table->string('public_disk', 64)->nullable();
            $table->string('public_path')->nullable();
            $table->string('blurred_disk', 64)->nullable();
            $table->string('blurred_path')->nullable();
            $table->string('thumbnail_disk', 64)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('visibility', 32)->default(PhotoVisibility::Public->value)->index();
            $table->string('status', 32)->default(PhotoStatus::Processing->value)->index();
            $table->unsignedInteger('order_index')->default(0)->index();
            $table->text('caption')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['album_id', 'order_index']);
            $table->index(['visibility', 'status']);
            $table->index(['created_at', 'status']);
        });

        Schema::table('photo_albums', function (Blueprint $table) {
            $table->foreign('cover_photo_id')->references('id')->on('photos')->nullOnDelete();
        });

        $this->seedLegacyPhotos();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_albums', function (Blueprint $table) {
            $table->dropForeign(['cover_photo_id']);
        });

        Schema::dropIfExists('photos');
        Schema::dropIfExists('photo_albums');
    }

    private function seedLegacyPhotos(): void
    {
        $now = now();
        $photos = [
            ['image' => 'capri.jpg', 'type' => 'Album', 'tone' => 'travel', 'title' => 'Capri Heartbreak', 'caption' => 'Travel photo set from Capri, Porto, and Roma.', 'size' => 'wide'],
            ['image' => 'studio.jpg', 'type' => 'Single post', 'tone' => 'studio', 'title' => 'Recording Places', 'caption' => 'Studio still for the Places release window.', 'size' => 'tall'],
            ['image' => 'radio.jpg', 'type' => 'Single post', 'tone' => 'press', 'title' => 'Radio Ancon', 'caption' => 'Press cabin image from the promo run.', 'size' => 'standard'],
            ['image' => 'places.jpg', 'type' => 'Album', 'tone' => 'travel', 'title' => 'Places Europe', 'caption' => 'Madrid, Barcelona, Paris, and Milan visual archive.', 'size' => 'tall'],
            ['image' => 'tv.jpg', 'type' => 'Single post', 'tone' => 'press', 'title' => 'Tu Manana', 'caption' => 'TV promo still for the campaign.', 'size' => 'standard'],
            ['image' => 'performance.jpg', 'type' => 'Single post', 'tone' => 'stage', 'title' => 'Performance Frames', 'caption' => 'Movement and live-stage image reference.', 'size' => 'wide'],
            ['image' => 'rehearsal.jpg', 'type' => 'Single post', 'tone' => 'stage', 'title' => 'Organik Rehearsal', 'caption' => 'Choreography-focused rehearsal still.', 'size' => 'standard'],
            ['image' => 'cover.jpg', 'type' => 'Single post', 'tone' => 'studio', 'title' => 'Eight Years Later', 'caption' => 'Cover-session image treatment.', 'size' => 'tall'],
            ['image' => 'campaign.jpg', 'type' => 'Album', 'tone' => 'studio', 'title' => 'Save My Body', 'caption' => 'Campaign stills and 5D Stage release images.', 'size' => 'wide'],
            ['image' => 'merch.jpg', 'type' => 'Album', 'tone' => 'store', 'title' => 'Merch Drop', 'caption' => 'Product-facing photography for the Store bridge.', 'size' => 'standard'],
            ['image' => 'dance.jpg', 'type' => 'Single post', 'tone' => 'stage', 'title' => 'Choreo Session', 'caption' => 'Rehearsal and movement photo treatment.', 'size' => 'tall'],
            ['image' => 'tvVisit.jpg', 'type' => 'Single post', 'tone' => 'press', 'title' => 'Mas23 Visit', 'caption' => 'Panama press still from the music promo run.', 'size' => 'standard'],
        ];

        foreach ($photos as $index => $photo) {
            $albumId = null;

            if ($photo['type'] === 'Album') {
                $albumId = DB::table('photo_albums')->insertGetId([
                    'title' => $photo['title'],
                    'description' => $photo['caption'],
                    'metadata' => json_encode(['legacy_import' => true, 'tone' => $photo['tone']]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $photoId = DB::table('photos')->insertGetId([
                'album_id' => $albumId,
                'visibility' => PhotoVisibility::Public->value,
                'status' => PhotoStatus::Active->value,
                'order_index' => $index,
                'caption' => $photo['caption'],
                'metadata' => json_encode([
                    'legacy_import' => true,
                    'legacy_asset_path' => 'images/photos/'.$photo['image'],
                    'original_filename' => $photo['image'],
                    'title' => $photo['title'],
                    'type' => $photo['type'],
                    'tone' => $photo['tone'],
                    'size' => $photo['size'],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($albumId !== null) {
                DB::table('photo_albums')->where('id', $albumId)->update([
                    'cover_photo_id' => $photoId,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
