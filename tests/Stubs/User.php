<?php

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Mpociot\Versionable\VersionableTrait;

class User extends Model
{
    
    use VersionableTrait;

    public $fillable = ['name'];

    public static function setupTable()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('note');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }
}
