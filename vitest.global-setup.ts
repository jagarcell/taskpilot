import { existsSync } from 'node:fs';
import { execSync } from 'node:child_process';

export default async function setup(): Promise<void> {
    const hasLaravelInstall = existsSync('./vendor/autoload.php') && existsSync('./artisan');

    if (!hasLaravelInstall) {
        return;
    }

    try {
        execSync('php artisan wayfinder:generate', { stdio: 'inherit' });
    } catch (error) {
        throw new Error(
            `Failed to generate Laravel Wayfinder routes for Vitest: ${error instanceof Error ? error.message : String(error)}`,
        );
    }
}
