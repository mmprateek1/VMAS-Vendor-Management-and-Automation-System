<?php

use App\Models\User;
use App\Models\VendorImport;
use App\Services\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('vmas:needs-admin', function () {
    return User::exists() ? 0 : 10;
})->purpose('Check whether first administrator setup is needed');

Artisan::command('vmas:status', function () {
    DB::select('SELECT 1');
    $this->info('VMAS database connected. Active employees: '.User::where('is_active', true)->count());
})->purpose('Check the independent VMAS database');

Artisan::command('vmas:prune-imports', function () {
    $count = 0;
    VendorImport::where('expires_at', '<=', now())->whereHas('rows')->chunkById(100, function ($batches) use (&$count) {
        foreach ($batches as $batch) {
            DB::transaction(function () use ($batch, &$count) {
                $batch = VendorImport::lockForUpdate()->findOrFail($batch->id);
                $batch->rows()->delete();
                $batch->update(['status' => $batch->status === 'committed' ? 'committed' : 'expired', 'mapping' => null, 'headers' => [], 'version' => $batch->version + 1]);
                Audit::record('vendor.import_rows_expired', 'vendor_import', $batch->id);
                $count++;
            });
        }
    });
    $this->info('Expired staged rows removed from '.$count.' imports. Summary records retained.');
    foreach (Storage::disk('local')->files('vendor-imports') as $path) {
        if (Storage::disk('local')->lastModified($path) < now()->subDay()->timestamp) {
            Storage::disk('local')->delete($path);
        }
    }
})->purpose('Remove private spreadsheet rows after the seven-day retention period');

Schedule::command('vmas:prune-imports')->daily()->withoutOverlapping();
