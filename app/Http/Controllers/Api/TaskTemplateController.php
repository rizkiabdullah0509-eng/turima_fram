<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function index()
    {
        return TaskTemplate::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $template = TaskTemplate::create($data);

        return response()->json($template, 201);
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $taskTemplate->delete();

        return response()->json(['message' => 'Tugas dihapus dari daftar.']);
    }
}
