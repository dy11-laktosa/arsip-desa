<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuratKeluar extends Model
{
    protected $table = 'surat_keluar';

    protected $fillable = [
        'no_surat',
        'tgl_ns',
        'pengirim',
        'penerima',
        'perihal',
        'token_lampiran',
        'user_id',
        'tgl_sk'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lampiran()
    {
        return $this->hasMany(Lampiran::class, 'token_lampiran', 'token_lampiran');
    }
}
