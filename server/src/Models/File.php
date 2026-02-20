<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $table = 'file';

    protected $fillable = [
        'owner',
        'name',
        'type',
        'size',
        'path',
        'host',
    ];

    protected $casts = [
        'owner' => 'integer',
    ];

    public $timestamps = true;

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner');
    }

    public function downloadLogs()
    {
        return $this->hasMany(DownloadLog::class, 'file_id');
    }
}
