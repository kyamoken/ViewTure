<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User; // User モデルをインポート

class Photo extends Model
{
    // クラスのプロパティ
    protected $fillable = ['user_id', 'title', 'image_path'];

    /**
     * Photo は投稿者である User に属します
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
