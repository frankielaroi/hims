<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugBatch;
use Illuminate\Database\Seeder;

class DrugBatchesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Drug::all()->each(function (Drug $drug) {
            if ($drug->batches()->exists()) {
                return;
            }

            $batches = [
                ['batch_number' => "B-{$drug->id}-A", 'quantity' => 200, 'expiry_days' => 240, 'received_days_ago' => 30],
                ['batch_number' => "B-{$drug->id}-B", 'quantity' => 100, 'expiry_days' => 45, 'received_days_ago' => 90],
            ];

            foreach ($batches as $batch) {
                DrugBatch::create([
                    'drug_id' => $drug->id,
                    'batch_number' => $batch['batch_number'],
                    'expiry_date' => now()->addDays($batch['expiry_days']),
                    'quantity_received' => $batch['quantity'],
                    'quantity_remaining' => $batch['quantity'],
                    'received_at' => now()->subDays($batch['received_days_ago']),
                ]);
            }

            $drug->update(['stock_level' => $drug->batches()->sum('quantity_remaining')]);
        });
    }
}
