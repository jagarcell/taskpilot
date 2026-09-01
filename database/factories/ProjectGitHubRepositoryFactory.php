<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectGitHubRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectGitHubRepository>
 */
class ProjectGitHubRepositoryFactory extends Factory
{
    protected $model = ProjectGitHubRepository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $githubOwner = $this->faker->userName();
        $githubRepo = $this->faker->slug(2);

        return [
            'project_id' => Project::factory(),
            'github_owner' => $githubOwner,
            'github_repo' => $githubRepo,
            'default_branch' => 'main',
            'repository_url' => sprintf('https://github.com/%s/%s', $githubOwner, $githubRepo),
            'is_active' => true,
        ];
    }
}
