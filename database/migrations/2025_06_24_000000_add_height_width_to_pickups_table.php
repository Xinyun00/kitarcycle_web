<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('pickups', function (Blueprint $table) {
            $table->decimal('height', 8, 2)->nullable()->after('estimated_weight');
            $table->decimal('width', 8, 2)->nullable()->after('height');
        });
    }

    public function down()
    {
        Schema::table('pickups', function (Blueprint $table) {
            $table->dropColumn(['height', 'width']);
        });
    }
};
