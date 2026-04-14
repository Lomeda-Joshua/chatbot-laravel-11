<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\ChatBotQueries;

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
        $data = collect([
            (object)[
                'id'      => 1,
                'query'   => 'How may I help you today?',
                'actions' => collect([                        
                    (object)[
                        'label'         => 'I would like to access the status of my complaint',
                        'is_form'       => true,
                        'is_submit'     => true,
                        'next_sequence' => 0,
                        'form'          => (object)[
                            'description' => 'Please input your email address and tracking number',
                            'fields'      => collect([        
                                (object)[
                                    'type'     => 'email',
                                    'name'     => 'email',
                                    'label'    => 'Email Address',
                                    'value'    => '',
                                    'required' => true,
                                    'disabled' => false,
                                    'options'  => collect([]) 
                                ],
                                (object)[
                                    'type'     => 'text',
                                    'name'     => 'tracking_number',
                                    'label'    => 'Tracking Number',
                                    'value'    => '',
                                    'required' => true,
                                    'disabled' => false,
                                    'options'  => collect([]) 
                                ],
                            ]),
                        ],
                    ],
                    (object)[
                        'label'         => 'Speak to a live agent',
                        'is_form'       => false,
                        'is_submit'     => false,
                        'next_sequence' => 2,
                        'form'          => null,
                    ],
                ]),
            ],
        ]);

        $formatted = $data->map(function ($query) {
            return [
                'id'      => $query->id,
                'query'   => $query->query,
                'actions' => $query->actions->map(function ($action) {
                    return [
                        'label'        => $action->label,
                        'isForm'       => $action->is_form,
                        'isSubmit'     => $action->is_submit,
                        'nextSequence' => $action->next_sequence,
                        'form'         => $action->form ? [
                            'description' => $action->form->description,
                            'fields'      => $action->form->fields->map(function ($field) {
                                return [
                                    'type'     => $field->type,
                                    'name'     => $field->name,
                                    'label'    => $field->label,
                                    'value'    => $field->value ?? '',
                                    'required' => $field->required,
                                    'disabled' => $field->disabled,
                                    'option'   => $field->options
                                                    ->pluck('option_value')
                                                    ->toArray(),
                                ];
                            })->toArray(),
                        ] : null,
                    ];
                })->toArray(),
            ];
        });



        return response()->json($formatted);
    }
}
