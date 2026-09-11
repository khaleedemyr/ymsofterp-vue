<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_submissions')) {
            return;
        }

        Schema::table('overtime_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_submissions', 'edit_reason')) {
                $table->text('edit_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('overtime_submissions', 'edit_changes')) {
                $table->json('edit_changes')->nullable()->after('edit_reason');
            }
            if (! Schema::hasColumn('overtime_submissions', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('edit_changes');
            }
            if (! Schema::hasColumn('overtime_submissions', 'edited_by')) {
                $table->unsignedBigInteger('edited_by')->nullable()->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('overtime_submissions')) {
            return;
        }

        Schema::table('overtime_submissions', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('overtime_submissions', 'edit_reason') ? 'edit_reason' : null,
                Schema::hasColumn('overtime_submissions', 'edit_changes') ? 'edit_changes' : null,
                Schema::hasColumn('overtime_submissions', 'edited_at') ? 'edited_at' : null,
                Schema::hasColumn('overtime_submissions', 'edited_by') ? 'edited_by' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
