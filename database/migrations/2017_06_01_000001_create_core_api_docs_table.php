<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoreApiDocsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('core_api_docs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identifier');
            $table->string('title');
            $table->text('description');
            $table->string('method');
            $table->string('uri');
            $table->json("parameters")->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('jwt_tokens');
    }
}
