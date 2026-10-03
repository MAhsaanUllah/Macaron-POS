<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Old rows baked the absolute asset() URL; the desktop shell changes
        // ports between launches, so keep only the path.
        DB::table('items')
            ->where('image_path', 'like', 'http://%')
            ->orWhere('image_path', 'like', 'https://%')
            ->pluck('image_path', 'id')
            ->each(function (string $path, $id) {
                DB::table('items')->where('id', $id)->update([
                    'image_path' => ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/'),
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
