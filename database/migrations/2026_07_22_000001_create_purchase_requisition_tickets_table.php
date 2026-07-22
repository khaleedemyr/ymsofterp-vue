<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requisition_tickets')) {
            Schema::create('purchase_requisition_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_requisition_id');
                $table->unsignedBigInteger('ticket_id');
                $table->timestamps();

                $table->unique(
                    ['purchase_requisition_id', 'ticket_id'],
                    'pr_tickets_pr_id_ticket_id_unique'
                );
                $table->index('ticket_id', 'pr_tickets_ticket_id_index');

                $table->foreign('purchase_requisition_id', 'pr_tickets_pr_id_fk')
                    ->references('id')
                    ->on('purchase_requisitions')
                    ->onDelete('cascade');

                $table->foreign('ticket_id', 'pr_tickets_ticket_id_fk')
                    ->references('id')
                    ->on('tickets')
                    ->onDelete('cascade');
            });
        }

        // Backfill from primary ticket_id
        if (Schema::hasColumn('purchase_requisitions', 'ticket_id')) {
            $rows = DB::table('purchase_requisitions')
                ->whereNotNull('ticket_id')
                ->select('id', 'ticket_id', 'created_at', 'updated_at')
                ->get();

            $now = now();
            $insert = [];
            foreach ($rows as $row) {
                $insert[] = [
                    'purchase_requisition_id' => $row->id,
                    'ticket_id' => $row->ticket_id,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ];
                if (count($insert) >= 500) {
                    DB::table('purchase_requisition_tickets')->insertOrIgnore($insert);
                    $insert = [];
                }
            }
            if ($insert !== []) {
                DB::table('purchase_requisition_tickets')->insertOrIgnore($insert);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_tickets');
    }
};
