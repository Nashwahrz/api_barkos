<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimoni extends Model
{
    protected $fillable = [
        'nama',
        'pekerjaan',
        'jenis_kelamin',
        'tanggal_lahir',
    ];
    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

}
