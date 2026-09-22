<?php

namespace App\Http\Controllers;

use App\Models\LabReagent;
use Illuminate\Http\Request;

class LabInventoryController extends Controller
{
    public function index()
    {
        $reagents = LabReagent::orderBy('name')->paginate(20);

        return view('lab.inventory.index', compact('reagents'));
    }

    public function create()
    {
        return view('lab.inventory.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:lab_reagents,code',
            'batch_number' => 'required|string',
            'current_stock' => 'required|integer|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'unit' => 'required|string',
        ]);

        LabReagent::create($validated);

        return redirect()->route('lab.inventory.index')->with('success', 'Reagent added to inventory.');
    }
}
