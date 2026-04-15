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
        
        // Initial load request to API, send only sequence 1
        $data = ChatBotQueries::where('group_id', $group_id)
                ->where('sequence', 1)       
                ->where('is_active', 1)
                ->first();

        return response()->json($this->formatQuery($data));
    }


    public function nextStep(Request $request)
    {
        $group_id        = $request->query('group_id');
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
        $is_form_values = explode(';;', $query->is_form);

        // Step 1 : insert all of the related columnds to be exploded.
        $columns = [
                'choices'          => 'label',
                'is_form'          => 'isForm',
                'form_description' => 'formDescription',
                'form_details'     => 'formDescription',
                'is_submit'        => 'isSubmit',
                'navigation'       => 'nextSequence',
                'is_ticket'        => 'isTicket',
        ];

        // Step 2 : Apply exploded function to array.
        $exploded = [];
        foreach ($columns as $column => $outputKey) {
            $exploded[$column] = explode(';;', $query->$column ?? '');
        }

        // Step 3 — get the count from the first column (choices)
        $count = count($exploded['choices']);

        // Step 4 — loop by index and build each action
        $actions = [];
        for ($i = 0; $i < $count; $i++) {
            $actions[] = [
                'label'           => trim($exploded['choices'][$i]          ?? ''),
                'isForm'          => (bool) trim($exploded['is_form'][$i]   ?? 0),
                'isSubmit'        => (bool) trim($exploded['is_submit'][$i] ?? 0),
                'isTicket'        => (bool) trim($exploded['is_ticket'][$i] ?? 0),
                'nextSequence'    => (int)  trim($exploded['navigation'][$i]?? 0),
                'form'            => (bool) trim($exploded['is_form'][$i]   ?? 0) ? [
                    'description' => trim($exploded['form_description'][$i] ?? ''),
                    'fields'      => collect($query->form_details)->map(function ($field) {
                        return [
                            'type'     => $field['type']     ?? '',
                            'name'     => $field['name']     ?? '',
                            'label'    => $field['label']    ?? '',
                            'date'     => $field['date']    ?? '',
                            'value'    => $field['value']    ?? '',
                            'required' => (bool) ($field['required'] ?? false),
                            'disabled' => (bool) ($field['disabled'] ?? false),
                            'option'   => $field['option']   ?? [],
                        ];
                    })->toArray(),
                ] : null,
            ];
        }

            
        return [
            'id'      => $query->id,
            'query'   => $query->query_name,
            'actions' => $actions,
        ];
    }


    
}

