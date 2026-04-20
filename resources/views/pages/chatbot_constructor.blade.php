<x-app-layout>
     <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Chatbot constructor') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                       <header>
                            <h2 class="text-lg font-medium text-gray-900">
                                {{ __('Chatbot Information') }}
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                {{ __("Settings for ODRS chatbot construct.") }}
                            </p>
                        </header>


                            <div class="mt-6">
                                <x-input-label for="navigation" :value="__('Attach module')" />

                                <select
                                    id="navigation"
                                    name="form[navigation]"
                                    class="mt-1 block w-3/4 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                    
                                    <option disabled value="">Select module</option>
                                    <option value="0">ODRS</option>
                                    
                                </select>

                                <x-input-error :messages="$errors->get('form.navigation')" class="mt-2" />
                            </div>


                            <div class="mt-6">
                                <x-input-label for="query_name" value="{{ __('Enter Query name') }}" />
                                
                                <x-text-input
                                    id="query_name"
                                    name="query_name"
                                    type="text"
                                    class="mt-1 block w-3/4"
                                    placeholder="{{ __('Query name') }}"
                                />
                                
                                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                            </div>

                            

                            <div class="mt-6">
                                <x-input-label for="form_description" :value="__('Form Description')" />

                                <x-text-input
                                    id="form_description"
                                    name="form[description]"
                                    type="text"
                                    class="mt-1 block w-3/4"
                                    placeholder="Enter form description"
                                />

                                <x-input-error :messages="$errors->get('form.description')" class="mt-2" />
                            </div>


                            <div class="mt-6">
                                <x-input-label for="navigation" :value="__('Navigation')" />

                                <select
                                    id="navigation"
                                    name="form[navigation]"
                                    class="mt-1 block w-3/4 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">

                                    <option value="">Select navigation</option>
                                    <option value="0">Start</option>
                                    <option value="1">Step 1</option>
                                    <option value="2">Step 2</option>
                                </select>

                                <x-input-error :messages="$errors->get('form.navigation')" class="mt-2" />
                            </div>

                             
                </div>
            </div>
        </div>
    </div>
</x-app-layout>