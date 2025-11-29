<?php

namespace Mamdouh\TenancyModules\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['tenant_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
