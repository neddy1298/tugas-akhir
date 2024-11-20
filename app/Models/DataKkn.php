<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKkn extends Model
{
    protected $table = 'data_kkns';

    protected $fillable = [
        'user_id',
        'dosen_id',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }
}
