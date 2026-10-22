<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class GenerateComplianceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('view_compliance_reports');
    }

    public function rules(): array
    {
        return [
            'framework' => 'required|in:SOC2,GDPR,HIPAA,PCI-DSS',
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'options' => 'nullable|array',
            'options.include_recommendations' => 'boolean',
            'options.include_evidence' => 'boolean',
            'options.format' => 'nullable|in:json,pdf,csv',
        ];
    }
}
