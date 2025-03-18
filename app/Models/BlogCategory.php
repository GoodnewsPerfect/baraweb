<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    protected $fillable = ['name', 'image'];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'image' => 'json',
    ];

    public function blogs()
    {
        return $this->belongsToMany(Blog::class);
    }
    
    /**
     * Get the image paths as array.
     *
     * @return array
     */
    public function getImagesAttribute()
    {
        return $this->image ? json_decode($this->image, true) : [];
    }
}