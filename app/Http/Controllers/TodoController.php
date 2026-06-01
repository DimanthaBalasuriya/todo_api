<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Todo;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TodoResource;

class TodoController extends Controller
{

    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();
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
        $user = JWTAuth::parseToken()->authenticate();

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('todo_images', 'public');
        }

        $todo = Todo::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'completed' => false,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success'=> true,
            'message' => 'Todo created Successfully',
            'data' => new TodoResource($todo),
        ], 201);
    }

    public function show($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $todo = Todo::where('user_id', $user->id)->findOrFail($id);

        if ($todo->image) {
            $todo->image_url = Storage::disk('public')->url($todo->image);
        }

        return response()->json([
            'success'=> true,
            'message'=> 'Todo fetched Successfully',
            'data' => new TodoResource($todo)
        ]);
    }

    public function update(UpdateTodoRequest $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $todo = Todo::where('user_id', $user->id)->findOrFail($id);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($todo->image) {
                Storage::disk('public')->delete($todo->image);
            }
            // Store new image
            $todo->image = $request->file('image')->store('todo_images', 'public');
        }

        $todo->title = $request->title ?? $todo->title;
        $todo->description = $request->description ?? $todo->description;
        $todo->completed = $request->completed ?? $todo->completed;

        // Use $request->validated() instead of $request->all()
        // This ensures only validated fields are sent to the DB
        $todo->save();

        return response()->json([
            'success' => true,
            'message' => 'Todo updated Successfully',
            'data' => new TodoResource($todo),
        ]);
    }

    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $todo = Todo::where('user_id', $user->id)->findOrFail($id);
        $todo->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Todo deleted Successfully',
        ]);
    }

}
