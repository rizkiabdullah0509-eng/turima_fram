<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftTemplate;
use Illuminate\Http\Request;

class ShiftTemplateController extends Controller
{
    public function index()
    {
        return ShiftTemplate::orderBy('start_time')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],
            'color'      => ['required', 'in:amber,teal,plum'],
        ]);

        $shift = ShiftTemplate::create($data);

        return response()->json($shift, 201);
    }

    public function update(Request $request, ShiftTemplate $shiftTemplate)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],
            'color'      => ['required', 'in:amber,teal,plum'],
        ]);

        $shiftTemplate->update($data);

        return response()->json($shiftTemplate);
    }

    public function destroy(ShiftTemplate $shiftTemplate)
    {
        $shiftTemplate->delete();

        return response()->json(['message' => 'Template shift dihapus.']);
    }
}
