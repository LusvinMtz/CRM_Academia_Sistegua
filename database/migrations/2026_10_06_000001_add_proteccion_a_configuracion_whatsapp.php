<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protección contra bloqueos de WhatsApp: pausa mínima y variación aleatoria entre mensajes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_whatsapp', function (Blueprint $table) {
            $table->boolean('proteccion')->default(true)->after('pausa_segundos');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_whatsapp', fn (Blueprint $table) => $table->dropColumn('proteccion'));
    }
};
