<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TodoController extends Controller
{
    private const IMAGE_DISK = 'r2';
    private const IMAGE_DIRECTORY = 'todos';

    public function index(Request $request)
    {
        $user = $request->user();

        $todos = Todo::where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Todo list retrieved Successfully',
            'data' => TodoResource::collection($todos),
        ]);
    }

    public function store(StoreTodoRequest $request)
    {
        $user = $request->user();
        $imagePath = $this->storeImage($request);

        $todo = Todo::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'completed' => false,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Todo created Successfully',
            'data' => new TodoResource($todo),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $todo = Todo::where('user_id', $user->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Todo fetched Successfully',
            'data' => new TodoResource($todo),
        ]);
    }

    public function update(UpdateTodoRequest $request, $id)
    {
        $user = $request->user();

        $todo = Todo::where('user_id', $user->id)
            ->findOrFail($id);

        // Handle image update
        if ($request->hasFile('image')) {

            $file = $request->file('image');

            // Upload new image to R2
            $newImagePath = Storage::disk('r2')->putFile('todos', $file);

            if ($newImagePath) {

                // Delete old image (if exists)
                if (!empty($todo->image)) {
                    try {
                        Storage::disk('r2')->delete($todo->image);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old R2 image', [
                            'todo_id' => $todo->id,
                            'old_image' => $todo->image,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Save new path in DB
                $todo->image = $newImagePath;

            } else {
                Log::error('R2 image upload failed during update', [
                    'todo_id' => $todo->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        // Update other fields
        $todo->title = $request->title ?? $todo->title;
        $todo->description = $request->description ?? $todo->description;

        if ($request->has('completed')) {
            $todo->completed = (bool) $request->completed;
        }

        $todo->save();

        return response()->json([
            'success' => true,
            'message' => 'Todo updated successfully',
            'data' => new TodoResource($todo),
        ]);
    }

    // public function update(UpdateTodoRequest $request, $id)
    // {
    //     $user = $request->user();
    //     $todo = Todo::where('user_id', $user->id)->findOrFail($id);

    //     if ($request->hasFile('image')) {
    //         // Store the new image first. Only delete the old image if the new one stored successfully.
    //         $newImagePath = $this->storeImage($request);

    //         if ($newImagePath) {
    //             $this->deleteImage($todo->image);
    //             $todo->image = $newImagePath;
    //         } else {
    //             Log::warning('Todo image update: failed to store new image, keeping existing image.', [
    //                 'todo_id' => $todo->id,
    //                 'user_id' => $user->id,
    //             ]);
    //         }
    //     }

    //     $todo->title = $request->title ?? $todo->title;
    //     $todo->description = $request->description ?? $todo->description;

    //     if ($request->has('completed')) {
    //         $todo->completed = $request->completed;
    //     }

    //     $todo->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Todo updated Successfully',
    //         'data' => new TodoResource($todo),
    //     ]);
    // }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $todo = Todo::where('user_id', $user->id)->findOrFail($id);
        $todo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Todo move to trash Successfully',
        ]);
    }

    public function restore(Request $request, $id)
    {
        $user = $request->user();
        $todo = Todo::withTrashed()->find($id);

        if (!$todo) {
            return response()->json([
                'success' => false,
                'message' => 'Todo not found',
            ], 404);
        }

        if ($todo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized to restore this todo',
            ], 403);
        }

        if (!$todo->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Todo is not in trash',
            ], 400);
        }

        $todo->restore();

        return response()->json([
            'success' => true,
            'message' => 'Todo restored Successfully',
            'data' => new TodoResource($todo),
        ]);
    }

    public function forceDelete(Request $request, $id)
    {
        $user = $request->user();
        $todo = Todo::withTrashed()->find($id);

        if (!$todo) {
            return response()->json([
                'success' => false,
                'message' => 'Todo not found',
            ], 404);
        }

        if ($todo->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized to delete this todo',
            ], 403);
        }

        if (!$todo->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Todo is not in trash. Soft-delete it first.',
            ], 400);
        }

        $this->deleteImage($todo->image);
        $todo->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Todo permanently deleted Successfully',
        ]);
    }

    public function trash(Request $request)
    {
        $user = $request->user();

        $todos = Todo::onlyTrashed()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Trashed todos fetched Successfully',
            'data' => TodoResource::collection($todos),
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            Log::info('Todo image upload skipped: request has no image file.');
            return null;
        }

        $image = $request->file('image');
        Log::info('Todo image upload received.', [
            'original_name' => $image->getClientOriginalName(),
            'mime_type' => $image->getClientMimeType(),
            'size' => $image->getSize(),
            'disk' => self::IMAGE_DISK,
        ]);

        $filename = now()->format('YmdHis') . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
        $storedPath = $image->storePubliclyAs(self::IMAGE_DIRECTORY, $filename, self::IMAGE_DISK);

        if (!$storedPath) {
            Log::warning('Todo image upload failed during storage.', [
                'filename' => $filename,
                'disk' => self::IMAGE_DISK,
            ]);
            return null;
        }

        Log::info('Todo image uploaded to storage.', [
            'stored_path' => $storedPath,
            'public_url' => Storage::disk(self::IMAGE_DISK)->url($storedPath),
        ]);

        return Storage::disk(self::IMAGE_DISK)->url($storedPath);
    }

    private function deleteImage(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($path, PHP_URL_PATH);

            if (!$parsedPath) {
                return;
            }

            $path = ltrim($parsedPath, '/');
        }

        $normalizedPath = ltrim($path, '/');

        if (str_starts_with($normalizedPath, 'todo_images/')) {
            $localPath = public_path($normalizedPath);

            if (File::exists($localPath)) {
                File::delete($localPath);
            }

            return;
        }

        if (str_starts_with($normalizedPath, self::IMAGE_DIRECTORY . '/')) {
            Storage::disk(self::IMAGE_DISK)->delete($normalizedPath);
        }
    }
}
