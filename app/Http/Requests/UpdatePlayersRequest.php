<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'players' => ['present', 'array'],
            'players.*.player_name' => ['required', 'string', 'max:255'],
            // F1 vozači često nemaju broj na dresu, pa je broj opcionalan.
            'players.*.player_number' => ['nullable', 'string', 'max:10'],
        ];
    }
}
