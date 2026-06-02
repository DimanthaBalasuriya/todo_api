<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imagePath = $this->image ? ltrim($this->image, '/') : null;
        $imageUrl = null;

        if ($imagePath) {
            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                $imageUrl = $imagePath;
            } elseif (str_starts_with($imagePath, 'todo_images/')) {
                $imageUrl = rtrim(config('app.url'), '/') . '/' . $imagePath;
            } else {
                $imageUrl = Storage::disk('r2')->url($imagePath);
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => (bool) $this->completed,
            'image' => $this->image,
            'image_url' => $imageUrl,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
