<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminSiteSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'issue_daily_target' => ['required', 'integer', 'min:1', 'max:50'],
            'issue_stale_days' => ['required', 'integer', 'min:1', 'max:60'],
            'issue_critical_days' => ['required', 'integer', 'min:1', 'max:120', 'gte:issue_stale_days'],
        ];
    }
}
