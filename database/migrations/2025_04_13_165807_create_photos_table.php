<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhotosTable extends Migration
{
    public function up()
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            // 投稿したユーザーID
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 投稿時に入力するタイトル
            $table->string('title');
            // 画像のファイルパスを保存
            $table->string('image_path');
            // 必要に応じて後で AI タグを紐付けるためのカラムなども追加できます
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('photos');
    }
}
