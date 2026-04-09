<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reservation_number', 32)->nullable()->after('id');
            $table->unique('reservation_number', 'reservations_reservation_number_unique');
        });

        $rows = DB::table('reservations')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->get();

        $sequenceByDate = [];
        foreach ($rows as $row) {
            $dateKey = $row->created_at
                ? Carbon::parse($row->created_at)->format('Ymd')
                : Carbon::now()->format('Ymd');

            $nextSeq = ($sequenceByDate[$dateKey] ?? 0) + 1;
            $sequenceByDate[$dateKey] = $nextSeq;

            $reservationNumber = sprintf('RSV-%s-%04d', $dateKey, $nextSeq);

            DB::table('reservations')
                ->where('id', $row->id)
                ->update(['reservation_number' => $reservationNumber]);
        }
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_reservation_number_unique');
            $table->dropColumn('reservation_number');
        });
    }
};

