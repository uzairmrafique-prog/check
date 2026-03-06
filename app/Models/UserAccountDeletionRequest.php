<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccountDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'mobile_no',
        'reason',
        'status',
    ];

}
