<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class ExecuteAccountActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $rules = [
            'action_type' => 'required|string|in:stop_production,start_production,apply_buff,send_specialist',
        ];

        $actionType = $this->input('action_type');

        if ($actionType === 'stop_production' || $actionType === 'start_production') {
            $rules['grid'] = 'required|integer';
        } elseif ($actionType === 'apply_buff') {
            $rules['grid'] = 'required|integer';
            $rules['unique_id1'] = 'required|integer';
            $rules['unique_id2'] = 'required|integer';
        } elseif ($actionType === 'send_specialist') {
            $rules['task_type'] = 'required|integer';
            $rules['sub_task_id'] = 'required|integer';
            $rules['unique_id1'] = 'required|integer';
            $rules['unique_id2'] = 'required|integer';
        }

        return $rules;
    }
}
