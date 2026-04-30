<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

// Models
use App\Models\ChatBotQueries;
use App\Models\ChatBotLog;
use App\Models\Barangay;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;



class ChatController extends Controller
{
    /* 
    * function for accessing the initial step on chatbot api
    *
    */
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
            $hasForm   = (bool) trim($exploded['is_form'][$i] ?? 0);
            $actions[] = [
                'label'        => trim($exploded['choices'][$i] ?? ''),
                'isForm'       => $hasForm,
                'isSubmit'     => (bool) trim($exploded['is_submit'][$i] ?? 0),
                'isTicket'     => (bool) trim($exploded['is_ticket'][$i] ?? 0),
                'nextSequence' => (int) trim($exploded['navigation'][$i] ?? 0),
                'form'         => $hasForm ? [
                    'description' => trim($exploded['form_description'][$i] ?? ''),
                    'fields'      => $this->formatFields($exploded['form_details'][$i] ?? null),
                ] : null,
            ];
        }

        return [
            'id'      => $query->id,
            'query'   => $query->query_name,
            'actions' => $actions,
        ];
    }


    /**
     * Parse all fields from raw form_details string
     */
    private function formatFields(?string $rawFields): array
    {
        if (empty($rawFields) || $rawFields === 'null') return [];

        //  Split on commas ONLY outside of quotes
        $fields = preg_split('/,(?=[^"]*(?:"[^"]*"[^"]*)*$)/', $rawFields);

        return collect($fields)
            ->map(function ($rawField) {
                $rawField = trim($rawField);

                if ($rawField === 'null' || empty($rawField)) return null;

                $parts   = explode(':', $rawField);
                $rawType = trim($parts[0] ?? '');

                //  Extract type → "text", "email", "select"
                preg_match('/input\[type=["\']?(\w+)["\']?\]/', $rawType, $typeMatch);
                $cleanType = $typeMatch[1] ?? 'text';

                //  Route to correct formatter
                return $cleanType === 'select'
                    ? $this->formatSelectField($parts)
                    : $this->formatInputField($parts, $cleanType);
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Format a select field
     * Raw: input[type="select"]:[name="Barangay, Municipality|City, Province, Region"]
     */
    private function formatSelectField(array $parts): array
    {
        $nameSection = trim($parts[1] ?? '');
        preg_match('/\[name="([^"]+)"\]/', $nameSection, $nameMatch);
        $rawName = $nameMatch[1] ?? $nameSection;

        // "Barangay, Municipality|City, Province, Region"
        // Split on FIRST comma → fieldName vs options
        $commaPos  = strpos($rawName, ',');
        $fieldName = $commaPos !== false 
            ? trim(substr($rawName, 0, $commaPos)) 
            : trim($rawName);
        $optionStr = $commaPos !== false 
            ? trim(substr($rawName, $commaPos + 1)) 
            : '';
        $options   = $optionStr 
            ? array_map('trim', explode('|', $optionStr)) 
            : [];

        return [
            'type'     => 'select',
            'name'     => $fieldName,
            'label'    => ucwords(strtolower($fieldName)),
            'value'    => '',
            'required' => true,
            'disabled' => false,
            'option'   => $options,
        ];
    }

    /**
    * Format a regular input field (text, email, textarea, etc.)
    * Raw: input[type="text"]:[name="Business Name"]:[required="yes"]:[disabled="no"]
    */
    private function formatInputField(array $parts, string $cleanType): array
    {
        // Extract name
        $namePart = trim($parts[1] ?? '');
        preg_match('/\[name="([^"]+)"\]/', $namePart, $nameMatch);
        $fieldName = $nameMatch[1] ?? $namePart;

        // Extract required
        $requiredPart = trim($parts[2] ?? '');
        preg_match('/\[required=["\']?(\w+)["\']?\]/', $requiredPart, $requiredMatch);
        $isRequired = strtolower($requiredMatch[1] ?? 'no') === 'yes';

        // Extract disabled
        $disabledPart = trim($parts[3] ?? '');
        preg_match('/\[disabled=["\']?(\w+)["\']?\]/', $disabledPart, $disabledMatch);
        $isDisabled = strtolower($disabledMatch[1] ?? 'no') === 'yes';

        return [
            'type'     => $cleanType,
            'name'     => $fieldName,
            'label'    => ucwords(strtolower($fieldName)),
            'value'    => '',
            'required' => $isRequired,
            'disabled' => $isDisabled,
            'option'   => [],
        ];
    }








public function saveLog(Request $request)
{
    $request->validate([
        'group_id' => ['required', 'integer'],
        'user_id'  => ['nullable'],
        'details'  => ['required'],
    ]);

    $group_id = $request->group_id;
    $user_id  = $request->user_id ?? null;
    $details  = $request->input('details');

    // Ensure details is always an array
    $details_decoded = is_array($details)
        ? $details
        : json_decode($details, true);

    if (is_string($details) && json_last_error() !== JSON_ERROR_NONE) {
        return response()->json([
            'message' => 'Invalid JSON in details field.',
            'error'   => json_last_error_msg(),
        ], 422);
    }

    // Save log
    ChatBotLog::create([
        'group_id'   => $group_id,
        'user_id'    => $user_id,
        'details'    => is_array($details) ? json_encode($details) : $details,
        'created_by' => Auth::id(),
        'is_active'  => 1,
    ]);

    // Extract form fields safely
    $fields = $details_decoded['actions'][0]['form']['fields'] ?? [];

    $form = collect($fields)
        ->filter(fn($field) => isset($field['name']))
        ->keyBy('name')
        ->map(fn($field) => $field['value'] ?? null);

    // Build payload
    $payload = [
        'CustomerId'          => $user_id,
        'BusinessName2'       => $form['Business Name'] ?? null,
        'RepresentativeName2'=> trim(
            ($form['Representative Last Name'] ?? '') . ' ' .
            ($form['Representative First Name'] ?? '') . ' ' .
            ($form['Representative M.I'] ?? '')
        ),
        'Email2'              => $form['Business email'] ?? null,
        'MobileNumber2'       => $form['Business Contact No'] ?? null,
        'BusinessUrl2'        => $form['Website'] ?? null,
        'CurrentAddress2'     => $form['Complete Address'] ?? null,
        'ChannelTypeId'       => 1,
        'TypeOfFeedback'      => 1,
        'TicketDescription'   => $form['Complete Address'] ?? null,
        'TransactionType1Id'  => 1,
        'TransactionType2Id'  => 1,
        'TransactionType3Id'  => 1,
    ];

    // Send to external API
    $response = Http::asMultipart()->post(
        'https://ticket.f-dci.com/DTI_API/api/Incident/create',
        $payload
    );

    if ($response->failed()) {
        return response()->json([
            'message' => 'External API error.',
            'error'   => $response->json() ?? $response->body(),
        ], $response->status());
    }

    return response()->json([
        'message' => 'Log recorded',
    ], 201);
   }












    /*
    *
    * Saving of chatbot log
    *
     */
//     public function saveLog(Request $request)
//     {

//     $request->validate([
//         'group_id' => ['required', 'integer'],
//         'user_id'  => ['nullable'],
//         'details'  => ['required'],
//     ]);

//     $group_id = 1;
//     $api_key  = $request->api_key;
    
//     $details  = $request->input('details');

//     // Ensure details is always an array
//     $details_decoded = is_array($details)
//         ? $details
//         : json_decode($details, true);

//     if (is_string($details) && json_last_error() !== JSON_ERROR_NONE) {
//         return response()->json([
//             'message' => 'Invalid JSON in details field.',
//             'error'   => json_last_error_msg(),
//         ], 422);
//     }

//     // Save log
//     ChatBotLog::create([
//         'group_id'   => $group_id,
//         'user_id'    => Auth::id(),
//         'details'    => is_array($details) ? json_encode($details) : $details,
//         'created_by' => Auth::id(),
//         'is_active'  => 1,
//     ]);

//     // Extract form fields safely
//     $fields = $details_decoded['actions'][0]['form']['fields'] ?? [];

//     $form = collect($fields)
//         ->filter(fn($field) => isset($field['name']))
//         ->keyBy('name')
//         ->map(fn($field) => $field['value'] ?? null);

//     // Build payload
//     $payload = [
//         'CustomerId'            => "00000001",
//         'BusinessName2'         => $form['Business Name'] ?? null,
//         'RepresentativeName2'   => trim(
//             ($form['Representative Last Name'] ?? '') . ' ' .
//             ($form['Representative First Name'] ?? '') . ' ' .
//             ($form['Representative M.I'] ?? '')
//         ),
//         // 'Email2'              => $form['Business email'] ?? null,
//         // 'MobileNumber2'       => $form['Business Contact No'] ?? null,
//         // 'BusinessUrl2'        => $form['Website'] ?? null,
//         // 'CurrentAddress2'     => $form['Complete Address'] ?? null,
//         // 'ChannelTypeId'       => 1,
//         // 'TypeOfFeedback'      => 1,
//         // 'TicketDescription'   => 'To follow',
//         // 'TransactionType1Id'  => 1,
//         // 'TransactionType2Id'  => 1,
//         // 'TransactionType3Id'  => 1,


//         'Email2'              => "sjoahu@gmail.com_create_guid",
//         'MobileNumber2'       => "094565465464",
//         'BusinessUrl2'        => "asdasd@gmail.com",
//         'CurrentAddress2'     => "eafa stretett",
//         'ChannelTypeId'       => "0000001",
//         'TypeOfFeedback'      => 1,
//         'TicketDescription'   => 'To follow',
//         'TransactionType1Id'  => 1,
//         'TransactionType2Id'  => 1,
//         'TransactionType3Id'  => 1,
//     ];

    

//     // Send to external API
//     $response = Http::asMultipart()->post(
//         'https://ticket.f-dci.com/DTI_API/api/Incident/create',
//         $payload
//     );

//     if ($response->failed()) {
//         return response()->json([
//             'message' => 'External API error.',
//             'error'   => $response->json() ?? $response->body(),
//         ], $response->status());
//     }

//     return response()->json([
//         'message' => 'Log recorded',
//     ], 201);
    
//    }
   /* 
    public function saveLog(Request $request){  
            $group_id   = $request->group_id;
            $user_id    = $request->user_id;
            $details    = $request->details;
       
            
            // Log creation
   /*         ChatBotLog::create([
                'group_id'      => $group_id,
                'user_id'       => $user_id,
                'details'       => $details,
                'created_by'    => Auth::id(),
                'is_active'     => 1
   ]);*/
     /*       ChatBotLog::create([
    'group_id'   => $group_id,
    'user_id'    => $user_id,
    'details'    => is_array($details) ? json_encode($details) : $details,
    'created_by' => Auth::id(),
    'is_active'  => 1
]); 
             	    
            $details_decoded = json_decode($details, true);

            // Guard against invalid JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'Invalid JSON in details field.',
                    'error'   => json_last_error_msg(),
                ], 422);
            }

            // Extract fields array from the nested structure
            $fields = $details_decoded['actions'][0]['form']['fields'] ?? [];            

            // Build a flat key => value map using the `name` property
            $form = collect($fields)->keyBy('name')->map(fn($field) => $field['value']);


            $payload = [
                    'CustomerId'          => $user_id,
                    'BusinessName2'       => $form['Business Name'] ?? null,
                    'RepresentativeName2' => trim(
                                                ($form['Representative Last Name'] ?? '') . ' ' .
                                                ($form['Representative First Name'] ?? '') . ' ' .
                                                ($form['Representative M.I'] ?? '')
                                            ),
                    'Email2'              => $form['Business email'] ?? null,
                    'MobileNumber2'       => $form['Business Contact No'] ?? null,
                    'BusinessUrl2'        => $form['Website'] ?? null,
                    'CurrentAddress2'     => $form['Complete Address'] ?? null,
                    'ChannelTypeId'       => 1,
                    'TypeOfFeedback'      => 1,
                    'TicketDescription'   => $form['Complete Address'] ?? null, // replace with correct field
                    'TransactionType1Id'  => 1,
                    'TransactionType2Id'  => 1,
                    'TransactionType3Id'  => 1,
                ];

            $multipart = Http::asMultipart();

            // Send as multipart form-data to the external API
            $response = $multipart->post(
                'https://ticket.f-dci.com/DTI_API/api/Incident/create',$payload
            );


            if ($response->failed()) {
                return response()->json([
                    'message' => 'External API error.',
                    'error'   => $response->json() ?? $response->body(),
                ], $response->status());
            }


            return response()->json([
                'message' => 'Log recorded',
            ], 201);
            

    }*/


    public function apiTest(){
        dd('hello');
            $payload = [
                'CustomerId'                    => "00000001",    
                'BusinessName2'                 => 1,
                'RepresentativeName2'           => 1,
                'Email2'                        => "1@gmai.com",
                'ChannelTypeId'                 => null,
                'TypeOfFeedback'                => null, 
                'TicketDescription'             => null,
                'TransactionType1Id'            => null,               
                'TransactionType2Id'            => null,     
                'TransactionType3Id'            => null
            ];

            

            $multipart = Http::asMultipart();

            // Send as multipart form-data to the external API
            $response = $multipart->post(
                'https://ticket.f-dci.com/DTI_API/api/Incident/create',$payload
            );

            if ($response->failed()) {
                return response()->json([
                    'message' => 'External API error.',
                    'error'   => $response->json() ?? $response->body(),
                ], $response->status());
            }


            return response()->json([
                'message' => 'Log recorded',
            ], 201);
    }



    /*
    *
    * Select input of location API endpoint requests
    *
    */ 
    public function getRegion(){ 
        $regions = DB::table('loc_regions')->select('id', 'reg_region','reg_description')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'region_id' => $regions,
            ]
        ]);
    }


    public function getProvinces(Request $request){ 
        $region_input = $request->input('region_id');
        
        $provinces = DB::table('loc_provinces')->select('id', 'prov_desc','prov_no')->where('reg_id',$region_input)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'provinces' => $provinces
            ]
        ]);
    }


    public function getMunicipalities(Request $request){ 
        $region_input = $request->input('region_id');
        $province_input = $request->input('prov_id');
        
        $municapalities = DB::table('loc_municipalities')->select('id', 'mun_desc','mun_complete_desc')->where('reg_id',$region_input)->where('prov_id', $province_input)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'municipalities' => $municapalities
            ]
        ]);
    }

    
    public function getBarangays(Request $request){   
        $region_input = $request->input('region_id');
        $province_input = $request->input('prov_id');
        $mun_id = $request->input('mun_id');
    
         $table_baranggays = DB::table('loc_barangays')->select('id', 'brgy_description', 'brgy_name')
         ->where('reg_id', $region_input)->
         where('prov_id', $province_input)->
         where('mun_id', $mun_id)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'baranggays' => $table_baranggays
            ]
        ]);
    }


    public function searchBrgy(Request $request){
        $query = trim((string) $request->get('query', ''));

        // pagination params
        $page   = (int) $request->get('page', 1);
        $limit  = (int) $request->get('limit', 20);
        $offset = ($page - 1) * $limit;

        $baseQuery = Barangay::query()
            ->when($query !== '', function ($q) use ($query) {
                $q->whereRaw('LOWER(brgy_description) LIKE ?', ['%' . strtolower($query) . '%']);
            });

        // total count (for hasMore)
        $total = $baseQuery->count();

        // paginated data
        $data = $baseQuery
            ->orderBy('brgy_description')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'brgy_name', 'brgy_description'])
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => str_replace(',', ', ', $item->brgy_description ?? $item->brgy_name)
            ])
            ->values();

        return response()->json([
            'data'    => $data,
            'hasMore' => ($offset + $limit) < $total,
            'page'    => $page,
            'total'   => $total,
        ]);
    }



}

