@extends('layouts.app')

@section('title', 'Referrals')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Referrals</h1>
        <p class="text-slate-500 mt-1">Patients being handed to another department or facility.</p>
    </div>

    @if($incoming->isNotEmpty())
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Incoming, waiting on you</h3>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($incoming as $referral)
                <div class="px-8 py-5">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $referral->visit->patient->full_name }}</p>
                            <p class="text-xs text-slate-500">From {{ $referral->fromDepartment->name }} &bull; {{ $referral->created_at->diffForHumans() }}</p>
                            <p class="text-sm text-slate-600 mt-2">{{ $referral->reason }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($referral->urgency === 'emergency') bg-red-100 text-red-700
                                @elseif($referral->urgency === 'urgent') bg-orange-100 text-orange-700
                                @else bg-blue-100 text-blue-700 @endif">
                                {{ ucfirst($referral->urgency) }}
                            </span>
                            <form action="{{ route('referrals.accept', $referral) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-green-700 transition-all">Accept</button>
                            </form>
                            <button type="button" onclick="document.getElementById('decline-{{ $referral->id }}').classList.toggle('hidden')" class="text-red-600 font-semibold text-sm hover:underline">Decline</button>
                        </div>
                    </div>
                    <form id="decline-{{ $referral->id }}" action="{{ route('referrals.decline', $referral) }}" method="POST" class="hidden mt-4 flex gap-2">
                        @csrf
                        <input type="text" name="feedback_notes" required placeholder="Reason for declining" class="flex-1 px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-red-500">
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-red-700 transition-all">Confirm</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($external->isNotEmpty())
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">External referrals</h3>
            <p class="text-sm text-slate-500 mt-1">Sent to facilities outside this system &mdash; print the referral note to travel with the patient.</p>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($external as $referral)
                <div class="px-8 py-5 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $referral->visit->patient->full_name }}</p>
                        <p class="text-xs text-slate-500">To {{ $referral->external_facility_name }} &bull; {{ $referral->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('referrals.print', $referral) }}" target="_blank" class="bg-white text-slate-700 px-4 py-2 rounded-xl font-semibold text-sm border border-slate-200 hover:bg-slate-50 transition-all">Print note</a>
                        <form action="{{ route('referrals.complete', $referral) }}" method="POST">
                            @csrf
                            <button type="submit" class="bg-teal-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-teal-700 transition-all">Mark sent</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Referrals you've made</h3>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($sent as $referral)
                <div class="px-8 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $referral->visit->patient->full_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $referral->type === 'internal' ? 'To '.$referral->toDepartment->name : 'To '.$referral->external_facility_name }}
                            &bull; {{ $referral->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                        @if($referral->status === 'accepted' || $referral->status === 'completed') bg-green-100 text-green-700
                        @elseif($referral->status === 'declined') bg-red-100 text-red-700
                        @else bg-slate-100 text-slate-600 @endif">
                        {{ ucfirst($referral->status) }}
                    </span>
                </div>
            @empty
                <p class="px-8 py-10 text-center text-slate-500 text-sm font-medium">You haven't made any referrals yet</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
