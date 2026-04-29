<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\ApiKey;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:generate-api-key';
    protected $signature = 'apikey:generate {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate api key for odrs front-end consumption';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = Str::random(64); 

        $apiKey = ApiKey::create([
            'name'      => $this->argument('name'),
            'key'       => hash('sha256', $key),  // ← hash before storing
            'is_active' => true,
        ]);

        // show the PLAIN key once — never stored in DB
        $this->info('API Key Generated Successfully!');
        $this->info('Name : ' . $apiKey->name);
        $this->info('Key  : ' . $key);          // ← copy this to your React .env
        $this->warn('Copy this key now — it will never be shown again!');

        
    }
}
