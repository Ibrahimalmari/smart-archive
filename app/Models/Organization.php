<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'country',
        'city',
        'address',
        'status',
    ];

    // مؤسسة لديها أقسام
    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }


    // مؤسسة لديها مستخدمون
    public function users()
    {
        return $this->hasMany(User::class);
    }

    
}
