<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'user';

    protected $fillable = [
        'username',
        'password',
        'allowedFileTypes',
        'is_admin'
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public $timestamps = true;
}
