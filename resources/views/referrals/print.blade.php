<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Referral Note - {{ $referral->visit->patient->full_name }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #1e293b; max-width: 720px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
        h1 { font-size: 22px; border-bottom: 2px solid #1e293b; padding-bottom: 12px; }
        .meta { color: #64748b; font-size: 13px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        td { padding: 6px 0; vertical-align: top; }
        td.label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; width: 180px; }
        .section { margin-top: 24px; }
        .section h2 { font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 8px; }
        .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; }
        .print-btn { margin: 20px 0; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print this note</button>

    <h1>Referral Note</h1>
    <p class="meta">Generated {{ now()->format('d M, Y H:i') }} &bull; Referral #{{ $referral->id }}</p>

    <table>
        <tr><td class="label">Patient</td><td>{{ $referral->visit->patient->full_name }}</td></tr>
        <tr><td class="label">Hospital ID</td><td>{{ $referral->visit->patient->hospital_id }}</td></tr>
        <tr><td class="label">Sex / Age</td><td>{{ ucfirst($referral->visit->patient->sex) }}, {{ $referral->visit->patient->age }} years</td></tr>
        <tr><td class="label">Referring facility</td><td>{{ config('app.name') }} &mdash; {{ $referral->fromDepartment->name }}</td></tr>
        <tr><td class="label">Referred to</td><td>{{ $referral->type === 'external' ? $referral->external_facility_name : $referral->toDepartment->name }}
            @if($referral->external_facility_contact) ({{ $referral->external_facility_contact }}) @endif</td></tr>
        <tr><td class="label">Referred by</td><td>{{ $referral->referredBy->name }}</td></tr>
        <tr><td class="label">Urgency</td><td>{{ ucfirst($referral->urgency) }}</td></tr>
    </table>

    <div class="section">
        <h2>Reason for referral</h2>
        <div class="box">{{ $referral->reason }}</div>
    </div>

    @if($referral->visit->patient->allergies)
    <div class="section">
        <h2>Known allergies</h2>
        <div class="box">{{ implode(', ', $referral->visit->patient->allergies) }}</div>
    </div>
    @endif

    <div class="section">
        <h2>Signature</h2>
        <p style="margin-top: 40px;">_________________________<br>{{ $referral->referredBy->name }}</p>
    </div>
</body>
</html>
