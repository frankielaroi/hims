<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class RejectSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('sample'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string',
        ];
    }
}
