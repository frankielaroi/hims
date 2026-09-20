@extends('layouts.app')

@section('title', 'Service Pricing')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Service pricing</h1>
            <p class="text-slate-500 mt-1">What patients are charged for hospital services and procedures.</p>
        </div>
        <button class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all duration-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add a service
        </button>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Service</th>
                        <th class="px-8 py-4">Category</th>
                        <th class="px-8 py-4">Price</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($services as $service)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $service->name }}</div>
                            @if($service->code)
                                <div class="text-xs text-slate-400">{{ $service->code }}</div>
                            @endif
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-medium bg-slate-100 text-slate-600 rounded-lg">
                                {{ $service->category }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-sm font-bold text-slate-900">
                                <span class="text-xs text-slate-400 mr-0.5">GHS</span>{{ number_format($service->price, 2) }}
                            </div>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <button onclick="openPriceModal({{ $service->id }}, '{{ $service->name }}', {{ $service->price }})"
                                    class="text-blue-600 hover:text-blue-700 font-semibold text-sm transition-colors">
                                Change price
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                </div>
                                <p class="text-slate-400 font-medium">No services set up yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Price Update Modal -->
<div id="priceModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center transition-all duration-300">
    <div class="bg-white p-8 rounded-[2rem] shadow-2xl w-[28rem] transform transition-all scale-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900" id="modalTitle">Update price</h3>
            <button onclick="closePriceModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="priceForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="mb-8">
                <label class="block text-sm font-semibold text-slate-700 mb-2">New price (GHS)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <span class="text-slate-400 font-semibold text-sm">GHS</span>
                    </div>
                    <input type="number" step="0.01" name="price" id="modalPrice"
                           class="block w-full pl-14 pr-4 py-4 bg-slate-50 border-none rounded-2xl text-lg font-bold text-slate-900 focus:ring-2 focus:ring-blue-500 transition-all" required>
                </div>
            </div>
            <div class="flex flex-col space-y-3">
                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">
                    Save price
                </button>
                <button type="button" onclick="closePriceModal()" class="w-full py-4 text-slate-500 font-semibold hover:bg-slate-50 rounded-2xl transition-all">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPriceModal(id, name, price) {
    document.getElementById('modalTitle').innerText = 'Update price: ' + name;
    document.getElementById('modalPrice').value = price;
    document.getElementById('priceForm').action = '/admin/services/' + id + '/price';
    document.getElementById('priceModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePriceModal() {
    document.getElementById('priceModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}
</script>
@endsection
