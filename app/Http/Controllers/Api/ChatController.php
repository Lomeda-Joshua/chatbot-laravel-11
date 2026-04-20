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
        $sequence_id = $request->query('sequence_id');

        if(isset($sequence_id)){
            // Initial load request to API, send sequence 1 first 
            $data = ChatBotQueries::where('group_id', $group_id)
                ->where('sequence', $sequence_id)       
                ->first();
        }else{
            $data = ChatBotQueries::where('group_id', $group_id)
                ->where('sequence', 1)       
                ->first();
        }

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
    // Determine if this specific action requires a form
    $hasForm = (bool) trim($exploded['is_form'][$i] ?? 0);

    $actions[] = [
        'label'        => trim($exploded['choices'][$i] ?? ''),
        'isForm'       => $hasForm,
        'isSubmit'     => (bool) trim($exploded['is_submit'][$i] ?? 0),
        'isTicket'     => (bool) trim($exploded['is_ticket'][$i] ?? 0),
        'nextSequence' => (int) trim($exploded['navigation'][$i] ?? 0),
        'form'         => $hasForm ? [

            'description' => trim($exploded['form_description'][$i] ?? ''),

            // Access the i-th group of fields and parse them
            'fields'      => collect(explode(',', ($exploded['form_details'][$i] ?? '')))
                ->map(function ($rawField) {
                    $rawField = trim($rawField);
                    
                    // Skip if the field definition is literally 'null' or empty
                    if ($rawField === 'null' || empty($rawField)) return null;

                    // Parse "input[type="email"]:email"
                    $parts     = explode(':', $rawField);
                    $rawType   = $parts[0] ?? '';
                    $fieldName = trim($parts[1] ?? '');

                    // Extract "email" from "input[type="email"]"
                    $cleanType = str_replace(['input[type="', '"]', 'input['], '', $rawType);

                    return [
                        'type'     => $cleanType,
                        'name'     => $fieldName,
                        'label'    => ucwords($fieldName),
                        'value'    => '',
                        'required' => true,
                        'disabled' => false,
                        'option'   => [],
                    ];
                })
                ->filter() // Remove the 'null' entries
                ->values() // Reset keys to [0, 1, 2...] for JSON compatibility
                ->toArray(),
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

