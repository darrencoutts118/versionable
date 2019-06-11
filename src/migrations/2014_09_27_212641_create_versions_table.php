<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateVersionsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->increments('version_id');
            $table->string('versionable_id')->nullable();
            $table->string('versionable_type');
            $table->string('version_type');
            $table->string('status')->enum(['approved', 'rejected'])->nullable();
            $table->string('user_id')->nullable();
            $table->binary('model_data');
            $table->string('reason', 100)->nullable();
            $table->index('versionable_id');
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
        Schema::drop('versions');
    }
}
