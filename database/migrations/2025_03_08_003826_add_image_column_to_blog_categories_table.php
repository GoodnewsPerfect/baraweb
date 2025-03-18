<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageColumnToBlogCategoriesTable extends Migration
{
    public function up()
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('name'); 
        });
    }
    public function down()
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropColumn('image'); 
        });
    }
}
