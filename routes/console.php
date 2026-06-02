<?php

use App\Models\Todo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('todos:migrate-images-to-r2', function () {
    $this->info('Starting todo image migration to R2...');
    $command = $this;

    Todo::whereNotNull('image')
        ->orderBy('id')
        ->chunkById(100, function ($todos) use ($command) {
            foreach ($todos as $todo) {
                $imagePath = ltrim((string) $todo->image, '/');

                if ($imagePath === '' || str_starts_with($imagePath, 'todos/')) {
                    continue;
                }

                $localPath = public_path($imagePath);

                if (!File::exists($localPath)) {
                    $command->warn("Skipping todo {$todo->id}: local image not found at {$localPath}");
                    continue;
                }

                $filename = basename($imagePath);
                $newPath = 'todos/' . $filename;

                $uploaded = Storage::disk('r2')->put(
                    $newPath,
                    File::get($localPath),
                    ['visibility' => 'public']
                );

                if (!$uploaded) {
                    $command->warn("Failed to upload todo {$todo->id} to R2");
                    continue;
                }

                $todo->image = $newPath;
                $todo->save();
                File::delete($localPath);

                $command->line("Migrated todo {$todo->id} -> {$newPath}");
            }
        });

    $this->info('Todo image migration complete.');
})->purpose('Migrate legacy todo images from public storage to Cloudflare R2');
