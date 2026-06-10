<?php

namespace Twdnhfr\BesRag\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Models\Run;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    protected $model = Run::class;

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence().'?',
            'status' => Run::STATUS_PENDING,
            'budget' => 30,
            'used_budget' => 0,
            'llm_calls' => 0,
            'steps_without_progress' => 0,
            'best_score' => 0,
            'config_json' => (new BesConfig)->toArray(),
        ];
    }
}
