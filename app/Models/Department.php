<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'status',
    ];

    // القسم يتبع لمؤسسة
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }


    // القسم لديه موظفون
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
