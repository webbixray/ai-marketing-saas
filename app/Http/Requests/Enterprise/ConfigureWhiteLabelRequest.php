<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureWhiteLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('configure_white_label');
    }

    public function rules(): array
    {
        return [
            'enabled' => 'boolean',
            'custom_domain' => 'nullable|string|max:255|regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            'theme' => 'nullable|in:light,dark,auto',
            'brand_name' => 'nullable|string|max:100',
            'logo_url' => 'nullable|url|max:500',
            'favicon_url' => 'nullable|url|max:500',
            'colors' => 'nullable|array',
            'colors.primary' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.secondary' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.success' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.warning' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.danger' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.info' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.light' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.dark' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.surface' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'fonts' => 'nullable|array',
            'fonts.heading' => 'nullable|string|max:100',
            'fonts.body' => 'nullable|string|max:100',
            'fonts.monospace' => 'nullable|string|max:100',
            'custom_css' => 'nullable|string|max:50000',
            'custom_js' => 'nullable|string|max:50000',
            'assets' => 'nullable|array',
            'assets.*.type' => 'required|in:logo,favicon,background,illustration,icon',
            'assets.*.url' => 'nullable|url',
            'assets.*.name' => 'nullable|string|max:255',
            'assets.*.width' => 'nullable|integer|min:1|max:5000',
            'assets.*.height' => 'nullable|integer|min:1|max:5000',
            'email_templates' => 'nullable|array',
            'email_templates.*.subject' => 'nullable|string|max:200',
            'email_templates.*.html' => 'nullable|string',
            'email_templates.*.text' => 'nullable|string',
            'email_templates.*.variables' => 'nullable|array',
            'login_page' => 'nullable|array',
            'login_page.show_powered_by' => 'boolean',
            'login_page.background_image' => 'nullable|url',
            'login_page.custom_message' => 'nullable|string|max:1000',
            'login_page.show_remember_me' => 'boolean',
            'login_page.show_forgot_password' => 'boolean',
            'login_page.show_sso_buttons' => 'boolean',
        ];
    }
}
