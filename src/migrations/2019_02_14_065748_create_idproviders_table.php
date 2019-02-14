<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIdprovidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('id_providers', function (Blueprint $table) {
            $table->string('company_slug')->primary();
            $table->string('domain')->unique();
            $table->string('entity_id')->unique();
            $table->string('sso_url');
            $table->string('slo_url');
            $table->string('cert_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('id_providers');
    }
}
