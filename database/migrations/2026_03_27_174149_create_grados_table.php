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
        Schema::create('grados', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->uuid('persona_id')->nullable();
    $table->foreign('persona_id')->references('id')->on('personas')->onDelete('cascade');
    $table->uuid('institucion_id')->nullable();
    $table->foreign('institucion_id')->references('id')->on('instituciones');
    $table->uuid('programa_id')->nullable();
    $table->foreign('programa_id')->references('id')->on('programas');
    $table->date('fecha_inicio')->nullable();
    $table->date('fecha_fin')->nullable();
    $table->string('titulo_obtenido', 255)->nullable();
    $table->string('numero_cedula', 50)->nullable();
    $table->text('titulo_tesis')->nullable();
    $table->string('menciones', 100)->nullable();
    // Campos blockchain
    $table->text('hash_actual');
    $table->text('hash_anterior')->nullable();
    $table->integer('nonce')->nullable();
    $table->string('firmado_por', 255)->nullable();
    $table->timestamp('creado_en')->useCurrent();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grados');
    }
};
