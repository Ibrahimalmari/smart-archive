<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MySQL ENUM comparison is case-insensitive, so we must pass through VARCHAR first.
            DB::statement("ALTER TABLE organizations MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE departments MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        DB::statement("UPDATE organizations SET status = CASE WHEN LOWER(status) = 'inactive' THEN 'inactive' ELSE 'active' END");
        DB::statement("UPDATE departments SET status = CASE WHEN LOWER(status) = 'inactive' THEN 'inactive' ELSE 'active' END");

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE organizations MODIFY status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE departments MODIFY status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE organizations MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Active'");
            DB::statement("ALTER TABLE departments MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Active'");
        }

        DB::statement("UPDATE organizations SET status = CASE WHEN LOWER(status) = 'inactive' THEN 'Inactive' ELSE 'Active' END");
        DB::statement("UPDATE departments SET status = CASE WHEN LOWER(status) = 'inactive' THEN 'Inactive' ELSE 'Active' END");

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE organizations MODIFY status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
            DB::statement("ALTER TABLE departments MODIFY status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
        }
    }
};
