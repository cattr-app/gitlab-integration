<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class MigrateSettingsFromPropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $companySettings = DB::table('properties')
            ->where('entity_type', 'company')
            ->whereRaw('LOWER(name) like ?', 'gitlab_%')
            ->get();

        foreach ($companySettings as $property) {
            DB::table('settings')->insert([
                'module_name' => 'gitlab',
                'key' => strtolower(substr(strstr($property->name, '_'), 1)),
                'value' => $property->value,
            ]);

            DB::table('properties')->delete($property->id);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        $gitlabSettings = DB::table('settings')
            ->where('module_name', 'gitlab')
            ->get();

        foreach ($gitlabSettings as $setting) {
            DB::table('properties')->insert([
                'entity_id' => 0,
                'entity_type' => 'company',
                'name' => "gitlab_{$setting->key}",
                'value' => $setting->value,
            ]);

            DB::table('settings')->delete($setting->id);
        }
    }
}
