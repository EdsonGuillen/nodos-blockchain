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
        Schema::create('personas', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->string('nombre', 100);
    $table->string('apellido_paterno', 100);
    $table->string('apellido_materno', 100)->nullable();
    $table->string('curp', 18)->unique()->nullable();
    $table->string('correo', 150)->nullable();
    $table->timestamp('creado_en')->useCurrent();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
