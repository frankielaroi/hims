<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use Illuminate\Http\Request;

class ServicePriceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('category')->orderBy('name')->get();

        return view('admin.services.index', compact('services'));
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $oldPrice = $service->price;
        $service->update(['price' => $request->price]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'price_updated',
            'description' => "Updated price for {$service->name} from {$oldPrice} to {$service->price}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Price updated successfully.');
    }
}
