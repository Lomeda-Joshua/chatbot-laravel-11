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
use Str;



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

    public function formatFields($string){
                    $parts = explode(';;', $string);

                    // STEP 2: get fields part
                    $fieldsRaw = $parts[1] ?? '';

                    // STEP 3: split each field
                    $fieldItems = explode(',', $fieldsRaw);

                    $fields = [];

                    foreach ($fieldItems as $item) {
                        $item = trim($item);

                        // STEP 4: explode by :
                        $segments = explode(':', $item);

                        // Extract TYPE
                        preg_match('/input\[type="(.*?)"\]/', $segments[0], $typeMatch);
                        $type = $typeMatch[1] ?? 'text';

                        $name = '';
                        $required = false;
                        $disabled = false;
                        $options = [];

                        foreach ($segments as $seg) {
                            // NAME
                            if (preg_match('/name="(.*?)"/', $seg, $match)) {
                                $name = $match[1];

                                // handle select options (|)
                                if ($type === 'select' && str_contains($name, '|')) {
                                    $options = explode('|', $name);
                                    $name = 'select_field';
                                }
                            }

                            // REQUIRED
                            if (preg_match('/required="(.*?)"/', $seg, $match)) {
                                $required = $match[1] === 'yes';
                            }

                            // DISABLED
                            if (preg_match('/disabled="(.*?)"/', $seg, $match)) {
                                $disabled = $match[1] === 'yes';
                            }
                        }

                        $fields[] = [
                            "type" => $type,
                            "name" => Str::slug($name, '_'),
                            "label" => strtoupper($name),
                            "value" => "",
                            "required" => $required,
                            "disabled" => $disabled,
                            "option" => $options
                        ];
                    }

                    return $fields;
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
                        'fields'      => $this->formatFields($exploded['form_details'][$i] ?? null),
                    ] : null, // ✅ closing the ternary
                ]; // ✅ closing $actions[]
            }

            return [
                'id'      => $query->id,
                'query'   => $query->query_name,
                'actions' => $actions,
            ];
    }





    /*
    *
    * Consume receiver API function
    *
    */ 
    public function saveLog(Request $request){     
        $group_id   = $request->group_id;
        $user_id    = $request->user_id;
        $details    = $request->details;
            
        ChatBotLog::create([
            'group_id'      => $group_id,
            'user_id'       => $user_id,
            'details'       => $details,
            'created_by'    =>  Auth::id(),
            'is_active'     => 1
        ]);

        return response()->json([
            'message' => 'Log recorded',
        ], 201);

    }

    /*
    *
    * Location API endpoint requests
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



    public function searchBrgy(Request $request)
    {
        $query = $request->get('q');

        $brgy = Barangay::query()
            ->when($query, function ($q) use ($query) {
                $q->whereRaw('LOWER(brgy_description) LIKE ?', ['%' . strtolower($query) . '%']);
            })
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'brgy_name', 'brgy_description']);

        return response()->json(
            $brgy->map(fn ($item) => [
                'value' => $item->id, 
                'label' => $item->brgy_description 
            ])
        );
    }




    //   public function getMunicipalitieAndBarangays(Request $request){   

    //     $mun_id = $request->input('mun_id');

    //     $table_barangays_municipalities = DB::table('loc_barangays')
    //         ->select('id', 'mun_id', 'mun_desc', 'brgy_name', 'brgy_description')
    //         ->where('mun_id', $mun_id)
    //     ->get();
        
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => [
    //             'municipal_barangays' => $table_barangays_municipalities,
    //         ]
    //     ]);
    // }
    
}

