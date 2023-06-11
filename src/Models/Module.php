<?php

namespace Mamdouh\TenancyModules\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;
    protected $fillable = ['tenant_id','isActive','name'];
}
