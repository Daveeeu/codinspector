<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_domain',
        'api_domain',
        'api_key',
        'secret_api_key',
        'success_tag',
        'reject_tag',
    ];
}
