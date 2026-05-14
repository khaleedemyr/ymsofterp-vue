<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_use_waste_headers')) {
            Schema::create('internal_use_waste_headers', function (Blueprint $table) {
                $table->id();
                $table->string('type', 32);
                $table->date('date');
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('ruko_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('internal_use_wastes') && ! Schema::hasColumn('internal_use_wastes', 'header_id')) {
            Schema::table('internal_use_wastes', function (Blueprint $table) {
                $table->unsignedBigInteger('header_id')->nullable()->after('id');
            });
        }

        if (! Schema::hasTable('internal_use_wastes')) {
            return;
        }

        $orphanLines = DB::table('internal_use_wastes')
            ->whereNull('header_id')
            ->orderBy('id')
            ->get();

        foreach ($orphanLines as $r) {
            $hid = DB::table('internal_use_waste_headers')->insertGetId([
                'type' => $r->type,
                'date' => $r->date,
                'warehouse_id' => $r->warehouse_id,
                'ruko_id' => $r->ruko_id,
                'notes' => $r->notes,
                'created_by' => $r->created_by ?? null,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            DB::table('internal_use_wastes')->where('id', $r->id)->update(['header_id' => $hid]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('internal_use_wastes') && Schema::hasColumn('internal_use_wastes', 'header_id')) {
            Schema::table('internal_use_wastes', function (Blueprint $table) {
                $table->dropColumn('header_id');
            });
        }
        Schema::dropIfExists('internal_use_waste_headers');
    }
};
