<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadLog extends Model
{
    protected $table = 'download_log';

    protected $fillable = [
        'file_id',
        'ip_address',
        'user_agent',
    ];

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
