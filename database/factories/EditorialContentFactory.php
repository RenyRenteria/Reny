<?php

namespace Database\Factories;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EditorialContent>
 */
class EditorialContentFactory extends Factory
{
    protected $model = EditorialContent::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'type' => ContentType::Post->value,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'status' => EditorialStatus::Draft->value,
            'visibility' => VisibilityAudience::Open->value,
            'needs_approval' => false,
            'metadata' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EditorialStatus::Published->value,
            'published_at' => now(),
            'needs_approval' => false,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EditorialStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ]);
    }
}
