<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Todo;
use App\Http\Resources\TodoResource;

class AdminController extends Controller
{
    public function users()
    {
        return response()->json([
            'success' => true,
            'data' => User::all(),
        ]);
    }

    public function todos()
    {
        return response()->json([
            'success' => true,
            'data' => TodoResource::collection(Todo::all()),
        ]);
    }

    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function deleteTodo($id)
    {
        Todo::findOrFail($id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Todo deleted successfully.',
        ]);
    }

}
