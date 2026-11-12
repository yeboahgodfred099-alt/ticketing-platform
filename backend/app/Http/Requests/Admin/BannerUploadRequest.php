<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class BannerUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        return $event instanceof Event && ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banner' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120', // 5MB max
                'dimensions:min_width=600,min_height=300,max_width=4000,max_height=3000',
            ],
        ];
    }
}

