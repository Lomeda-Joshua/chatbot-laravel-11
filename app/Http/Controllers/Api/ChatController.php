<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\ChatBotQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $data = ChatBotQueries::all();
        return response()->json($data);
    }

    public function data()
    {
        $data = ChatBotQueries::all();

        $formatted = $data->map(function ($query) {
            return [
                'id'       => $query->id,
                'sequence' => $query->sequence,
                'query'    => $query->query_name,
                'isForm'   => (bool) $query->is_form,
                'isSubmit' => (bool) $query->is_submit,
                'isActive' => (bool) $query->is_active,
                'isTicket' => (bool) $query->is_ticket,
                'imageUrl' => $query->image_url,
                'actions'  => collect($query->choices)->map(function ($choice) {
                    return [
                        'label'      => $choice['label']      ?? '',
                        'navigation' => $choice['navigation'] ?? null,
                    ];
                })->toArray(),
                'form' => $query->is_form ? [
                    'description' => $query->form_description,
                    'fields'      => collect($query->form_details)->map(function ($field) {
                        return [
                            'type'     => $field['type']     ?? '',
                            'name'     => $field['name']     ?? '',
                            'label'    => $field['label']    ?? '',
                            'value'    => $field['value']    ?? '',
                            'required' => (bool) ($field['required'] ?? false),
                            'disabled' => (bool) ($field['disabled'] ?? false),
                            'option'   => $field['option']   ?? [],
                        ];
                    })->toArray(),
                ] : null,
            ];
        });

        return response()->json($formatted);
    }
}

