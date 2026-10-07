<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Envío por WhatsApp (WasenderAPI) además del correo.
 * Cada invitación guarda el estado de cada canal; estado_envio sigue siendo el estado general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('modo', 10)->default('log');           // api (envío real) | log (modo de prueba)
            $table->text('token')->nullable();                     // cifrado con APP_KEY
            $table->string('codigo_pais', 4)->default('502');
            $table->unsignedSmallInteger('pausa_segundos')->default(5);
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('invitaciones', function (Blueprint $table) {
            $table->string('correo', 150)->nullable()->change();  // quien solo tiene teléfono se invita por WhatsApp
            $table->string('correo_estado', 20)->nullable()->after('estado_envio');   // pendiente | enviada | fallida
            $table->string('telefono', 20)->nullable()->after('correo');
            $table->string('whatsapp_estado', 20)->nullable()->after('correo_estado'); // pendiente | enviada | fallida
            $table->timestamp('whatsapp_at')->nullable()->after('enviada_at');
        });

        // Todo lo enviado hasta ahora salió por correo
        DB::table('invitaciones')->where('estado_envio', '!=', 'no_enviada')->update(['correo_estado' => DB::raw('estado_envio')]);

        Schema::table('envios', function (Blueprint $table) {
            $table->string('canal', 10)->default('correo')->after('motivo'); // correo | whatsapp
        });
    }

    public function down(): void
    {
        Schema::table('envios', fn (Blueprint $table) => $table->dropColumn('canal'));
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->dropColumn(['correo_estado', 'telefono', 'whatsapp_estado', 'whatsapp_at']);
        });
        Schema::dropIfExists('configuracion_whatsapp');
    }
};
