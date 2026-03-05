<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMenu extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['parent_id', 'title', 'url', 'order', 'icon'];

    public function parent()
    {
        return $this->belongsTo(AdminMenu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AdminMenu::class, 'parent_id');
    }
}
