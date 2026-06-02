<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Todo;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TodoResource;
use Illuminate\Support\Facades\File;

class TodoController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();
        $todos = Todo::where('user_id', $user->id)->latest()->get()->map(function ($todo) {
            if ($todo->image) {
                $todo->image_url = Storage::disk('public')->url($todo->image);
            }
            return $todo;
        });
        return response()->json([
            'success' => true,
            'message' => 'Todo list retrieved Successfully',
            'data' => TodoResource::collection($todos),
        ]);
    }

    public function store(StoreTodoRequest $request)
    {
        $user = $request->user();

        $imagePath = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // 1. Generate a completely unique filename to avoid overwriting files
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            // 2. Move the file physically to the public/todo_images directory
            $image->move(public_path('todo_images'), $filename);

            // 3. Save the path string that matches your desired web URL 
            // This will save as: "/todo_images/filename.png"
            $imagePath = '/todo_images/' . $filename;
        }

        $todo = Todo::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'completed' => false,
            'image' => $imagePath, // Saves the clean web path
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

        if ($todo->image) {
            $todo->image_url = Storage::disk('public')->url($todo->image);
        }

        return response()->json([
            'success' => true,
            'message' => 'Todo fetched Successfully',
            'data' => new TodoResource($todo)
        ]);
    }

    public function update(UpdateTodoRequest $request, $id)
    {
        $user = $request->user();

        $todo = Todo::where('user_id', $user->id)->findOrFail($id);

        if ($request->hasFile('image')) {
            // 1. Delete the old image if it exists in the public directory
            if ($todo->image) {
                // $todo->image contains "/todo_images/filename.png"
                // public_path($todo->image) converts it to the full server path
                $oldImagePath = public_path($todo->image);

                if (File::exists($oldImagePath)) {
                    File::delete($oldImagePath);
                }
            }

            // 2. Process and store the new image directly into public/todo_images
            $image = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('todo_images'), $filename);

            // 3. Update the path string for the database
            $todo->image = '/todo_images/' . $filename;
        }

        // Only update fields if they are provided in the request
        $todo->title = $request->title ?? $todo->title;
        $todo->description = $request->description ?? $todo->description;

        // Explicitly check for null/presence because 'completed' is a boolean (false evaluates to falsy)
        if ($request->has('completed')) {
            $todo->completed = $request->completed;
        }

        $todo->save();

        return response()->json([
            'success' => true,
            'message' => 'Todo updated Successfully',
            'data' => new TodoResource($todo),
        ]);
    }
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

        // Delete associated image if exists
        if ($todo->image) {
            Storage::disk('public')->delete($todo->image);
        }

        $todo->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Todo permanently deleted Successfully',
        ]);
    }

    public function trash(Request $request)
    {
        $user = $request->user();
        $todos = Todo::onlyTrashed()->where('user_id', $user->id)->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Trashed todos fetched Successfully',
            'data' => TodoResource::collection($todos),
        ]);
    }

}
