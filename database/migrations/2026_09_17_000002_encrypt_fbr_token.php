<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_configs')
            ->whereNotNull('fbr_bearer_token')
            ->orderBy('id')
            ->each(function ($config) {
                if ($config->fbr_bearer_token && ! str_starts_with($config->fbr_bearer_token, 'eyJpdiI6')) {
                    DB::table('system_configs')->where('id', $config->id)->update([
                        'fbr_bearer_token' => Crypt::encryptString($config->fbr_bearer_token),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Tokens intentionally remain encrypted when rolling back.
    }
};
