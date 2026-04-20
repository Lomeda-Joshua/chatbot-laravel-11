<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\ChatBotQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $user_id;
    protected $query_name_fill;
    protected $choices;
    protected $is_form_status;
    protected $email;
    protected $tracking_no;
    protected $remarks;
    
    protected $email_address;
    protected $address;
    protected $contact_no;

    protected $business_name;
    protected $business_name_representative;
    protected $url;
    protected $email_address_business;
    protected $phyiscal_address_business;
    protected $contact_no_business;
    
    protected $loc_region;
    protected $brief_description;
    protected $relevant_docs_URL;


    public function index()
    {
        $data = ChatBotQueries::all();
        return response()->json($data);
    }


    public function data(Request $request)
    { 
        $group_id = $request->query('group_id');
        $sequence_id = $request->query('sequence_id');

        // $db_connection_location = DB::connection('mysql2')->select('select reg_region, reg_description from loc_regions where 1=1');

        if(isset($sequence_id)){
            // Load request to API, if sequence is set
            $data = ChatBotQueries::select('id','group_id', 'complaint', 'is_form', 'sequence', 'query_name', 'choices', 'form_description', 'navigation', 'updated_by'    )->where('group_id', $group_id)
                ->where('sequence', $sequence_id)       
                ->first();
        }else{
            $data = ChatBotQueries::where('group_id', $group_id)
                // Initial load request to API, if no sequence is found send, sequence 1 first 
                ->where('sequence', 1)       
                ->first();
        }

        return response()->json($this->formatQuery($data));
    }


    public function nextStep(Request $request)
    {
        $group_id        = $request->query('group_id');
        $target_sequence = $request->input('sequence_id'); 

        // Get data on the front-end 
        $validated = $request->validate([
            // Core Identity
            'user_id'                      => 'required|integer|exists:users,id',
            'query_name'                   => 'nullable|string|max:255',
            'tracking_no'                  => 'required|string|unique:disputes,tracking_no',
            
            // Logic Flags
            'is_form'                      => 'required|integer',
            'choices'                      => 'nullable|array',
            
            // Consumer Details
            'email_address'                => 'required_if:is_form,true|email',
            'address'                      => 'required_if:is_form,true|string',
            'contact_no'                   => 'required_if:is_form,true|string|max:20',
            
            // Business Details
            'business_name'                => 'nullable|string|max:255',
            'business_name_representative' => 'nullable|string|max:255',
            'url'                          => 'nullable|url',
            'email_address_business'       => 'nullable|email',
            'physical_address_business'    => 'nullable|string',
            'contact_no_business'          => 'nullable|string',
            
            // Case Content
            'loc_region'                   => 'string',
            'brief_description'            => 'required|string|min:10',
            'relevant_docs_URL'            => 'nullable|url',
            'remarks'                      => 'nullable|string',
        ]);


        $data = ChatBotQueries::where('group_id', $group_id)
                            ->where('sequence', $target_sequence) 
                            ->where('is_active', true)
                            ->first();

        if (!$data) {
            return response()->json(['error' => 'Step not found'], 404);
        }

        return response()->json($this->formatQuery($data));
    }

    public function processSubmission(Request $request)
    {
        // 1. Validate
        $this->validateData($request);

        // 2. Hydrate (Mapping)
        $this->user_id           = $request->input('user_id');
        $this->query_name_fill   = $request->input('query_name');
        $this->is_form_status    = $request->input('is_form'); // Explicit cast per your md rules
        $this->tracking_no       = $request->input('tracking_no');
        
        // Consumer mapping 
        $this->email_address     = $request->input('email_address');
        $this->address           = $request->input('address');
        $this->contact_no        = $request->input('contact_no');

        // Business mapping
        $this->business_name                = $request->input('business_name');
        $this->business_name_representative = $request->input('business_name_representative');
        $this->url                          = $request->input('url');
        $this->email_address_business       = $request->input('email_address_business');
        $this->phyiscal_address_business    = $request->input('phyiscal_address_business');
        $this->contact_no_business          = $request->input('contact_no_business');

        // Case Details mapping
        $this->loc_region        = $request->input('loc_region');
        $this->brief_description = $request->input('brief_description');
        $this->relevant_docs_URL = $request->input('relevant_docs_URL');
        $this->remarks           = $request->input('remarks');

        return $this->finalize();
        
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
    $hasForm = (int) trim($query->is_form ?? 0); 

    $actions[] = [
        'label'        => trim($exploded['choices'][$i] ?? ''),
        // 'isForm'       => $hasForm,
        'isSubmit' => collect(explode(';;', $query->is_submit ?? ''))
                        ->map(fn($val) => (int) trim($val))
                        ->toArray(),
        'isTicket' => collect(explode(';;', $query->is_ticket ?? ''))
                        ->map(fn($val) => (int) trim($val))
                        ->toArray(),
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
            'isForm'  => $query->is_form,
            'actions' => $actions,
        ];
    }



    public function getDataFromAPI(Request $request){
            $validated = $request->validate([
                'title' => 'required|unique:posts|max:255',
                'body' => 'required',
            ]);
    }


    
}

