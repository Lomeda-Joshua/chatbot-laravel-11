<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\LocBarangay;

class UpdateLocationStrings implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        LocBarangay::query()
            // Exclude rows that already have 4 parts (already patched)
            // A 4-set string has exactly 3 commas
            ->whereRaw("(LENGTH(brgy_description) - LENGTH(REPLACE(brgy_description, ',', ''))) = 2")
            ->whereNotNull('prov_desc')
            ->where('prov_desc', '!=', '')
            ->chunkById(1000, function ($rows) {

                $updates = [];

                foreach ($rows as $row) {
                    $parts = array_map('trim', explode(',', $row->brgy_description));

                    // Strict guard: must be exactly 3 parts
                    if (count($parts) !== 3) {
                        continue;
                    }

                    // Splice province_desc at index 2 (between municipality and region)
                    // Before: "Taguiporo,Bantay,Region I"
                    // After:  "Taguiporo,Bantay,Ilocos Sur,Region I"
                    array_splice($parts, 2, 0, trim($row->prov_desc));

                    $updates[$row->id] = implode(',', $parts);
                }

                // Batch update instead of individual save() calls
                foreach ($updates as $id => $newDescription) {
                    LocBarangay::where('id', $id)
                        ->update(['brgy_description' => $newDescription]);
                }

            });
    }
}