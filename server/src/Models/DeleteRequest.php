<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeleteRequest extends Model
{
    protected $table = 'delete_request';

    protected $fillable = [
        'file_id',
        'user_id',
        'reason',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'file_id' => 'integer',
        'user_id' => 'integer',
    ];

    public $timestamps = true;

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
