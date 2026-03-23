<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('organizations', function (Blueprint $table) {
        $table->id();
        $table->string('name');                       // اسم المؤسسة
        $table->string('type')->nullable();          // نوعها (شركة، جامعة، مكتب...)
        $table->string('country')->nullable();
        $table->string('city')->nullable();
        $table->string('address')->nullable();
        $table->enum('status', ['Active', 'Inactive'])->default('Active');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('organizations');
}

};
