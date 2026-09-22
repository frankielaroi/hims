<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class CollectSpecimenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('collectSpecimen', $this->route('labOrder'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'specimen_type' => 'required|string',
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:lab_order_items,id',
        ];
    }
}
