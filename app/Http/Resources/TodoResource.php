<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storedPath = $this->image ? ltrim($this->image, '/') : null;
        $publicUrl = $this->resolveImageUrl($storedPath);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => (bool) $this->completed,
            'image' => $publicUrl,
            'image_url' => $publicUrl,
            'image_path' => $storedPath,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (str_starts_with($path, 'todo_images/')) {
            return rtrim(config('app.url'), '/') . '/' . $path;
        }

        return Storage::disk('r2')->url($path);
    }
}
