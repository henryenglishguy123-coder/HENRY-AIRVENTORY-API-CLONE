<?php

namespace App\Console\Commands;

use App\Models\Sales\Order\Item\SalesOrderItemDesign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConvertDesignDataToFiles extends Command
{
    protected $signature = 'sales:convert-design-data';

    protected $description = 'Convert large design_data in sales_order_item_designs to files to prevent truncation.';

    public function handle()
    {
        $this->info('Checking for design_data entries exceeding 255 characters...');

        $query = SalesOrderItemDesign::query()
            ->whereNotNull('design_data')
            ->whereRaw('CHAR_LENGTH(design_data) > 255');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No records found with design_data > 255 characters.');

            return;
        }

        $this->info("Found {$count} records. Starting conversion...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunk(100, function ($designs) use ($bar) {
            foreach ($designs as $design) {
                $data = $design->design_data;

                // Double check length in PHP just in case
                if (mb_strlen($data) <= 255) {
                    $bar->advance();

                    continue;
                }

                // Generate a unique filename
                $filename = 'design-data/'.$design->id.'-'.Str::random(10).'.json';

                // Save to public disk
                Storage::put($filename, $data);

                // Update record
                $design->update(['design_data' => $filename]);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Conversion complete.');
    }
}
