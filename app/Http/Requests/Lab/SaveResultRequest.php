<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class SaveResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('enterResult', $this->route('item'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result_value' => 'required|string',
            'result_flag' => 'required|in:normal,abnormal,critical',
            'lab_reagent_id' => 'nullable|exists:lab_reagents,id',
        ];
    }
}
