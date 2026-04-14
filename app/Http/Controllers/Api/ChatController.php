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


    public function data(Request $request)
    { 
        $group_id = $request->query('group_id');
        
        $data = ChatBotQueries::where('group_id', $group_id)
                ->where('sequence', 1)       
                ->where('is_active', 1)
                ->first();

        return response()->json($this->formatQuery($data));
    }


    public function nextStep(Request $request)
    {
        $group_id        = $request->query('group_id');
        $user_id         = $request->query('user_id');
        $target_sequence = $request->input('sequence_id'); 

        $data = ChatBotQueries::where('group_id', $group_id)
                            ->where('sequence', $target_sequence) 
                            ->where('is_active', true)
                            ->first();

        if (!$data) {
            return response()->json(['error' => 'Step not found'], 404);
        }

        return response()->json($this->formatQuery($data));
    }

    private function formatQuery($query)
    {
        return [
            'id'       => $query->id,
            'sequence' => $query->sequence,
            'query'    => $query->query_name,
            'isForm'   => (bool) $query->is_form,
            'isSubmit' => (bool) $query->is_submit,
            'isTicket' => (bool) $query->is_ticket,
            'imageUrl' => $query->image_url,
            'actions'  => collect($query->choices)->map(function ($choice) {
                return [
                    'label'        => $choice['label']      ?? '',
                    'nextSequence' => $choice['navigation'] ?? null,
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
    }


    
}
