<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('submit_marketplace_review');
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
            'comment' => 'nullable|string|max:2000',
            'pros' => 'nullable|array',
            'pros.*' => 'string|max:200',
            'cons' => 'nullable|array',
            'cons.*' => 'string|max:200',
        ];
    }
}
