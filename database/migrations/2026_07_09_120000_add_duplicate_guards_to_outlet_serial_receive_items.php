<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('outlet_serial_receive_items')) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_osri_prevent_duplicate_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_osri_prevent_duplicate_update');

        DB::unprepared(
            'CREATE TRIGGER trg_osri_prevent_duplicate_insert
            BEFORE INSERT ON outlet_serial_receive_items
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM outlet_serial_receive_items
                    WHERE header_id = NEW.header_id
                      AND serial_id = NEW.serial_id
                    LIMIT 1
                ) THEN
                    SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate serial_id for the same GR header is not allowed.";
                END IF;
            END'
        );

        DB::unprepared(
            'CREATE TRIGGER trg_osri_prevent_duplicate_update
            BEFORE UPDATE ON outlet_serial_receive_items
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM outlet_serial_receive_items
                    WHERE header_id = NEW.header_id
                      AND serial_id = NEW.serial_id
                      AND id <> NEW.id
                    LIMIT 1
                ) THEN
                    SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate serial_id for the same GR header is not allowed.";
                END IF;
            END'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_osri_prevent_duplicate_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_osri_prevent_duplicate_update');
    }
};
