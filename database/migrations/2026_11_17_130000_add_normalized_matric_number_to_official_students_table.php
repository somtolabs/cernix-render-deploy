<?php

use App\Services\MatriculationNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('official_students')) {
            return;
        }

        if (! Schema::hasColumn('official_students', 'normalized_matric_number')) {
            Schema::table('official_students', function (Blueprint $table) {
                $table->string('normalized_matric_number')->nullable();
            });
        }

        // Populate normalized_matric_number for all existing records safely
        $service = app(MatriculationNumberService::class);
        $records = DB::table('official_students')->orderBy('id')->get();
        $seen = [];
        foreach ($records as $record) {
            $raw = (string) $record->matric_number;
            $norm = $service->normalize($raw);
            if ($norm === '') {
                $norm = null;
            } else {
                if (isset($seen[$norm])) {
                    $norm = $norm . '_dup_' . $record->id;
                } else {
                    $seen[$norm] = $record->id;
                }
            }

            DB::table('official_students')
                ->where('id', $record->id)
                ->update(['normalized_matric_number' => $norm]);
        }

        // Add unique constraint for database-level uniqueness enforcement
        try {
            Schema::table('official_students', function (Blueprint $table) {
                $table->unique('normalized_matric_number', 'official_students_norm_matric_unique');
            });
        } catch (\Throwable) {
            // Index already exists or already applied
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('official_students') && Schema::hasColumn('official_students', 'normalized_matric_number')) {
            Schema::table('official_students', function (Blueprint $table) {
                $table->dropUnique('official_students_norm_matric_unique');
                $table->dropColumn('normalized_matric_number');
            });
        }
    }
};
