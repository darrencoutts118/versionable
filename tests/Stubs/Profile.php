<?php

namespace Tests\Stubs;

use Chelout\RelationshipEvents\Concerns\HasBelongsToEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Profile extends Model
{
    use HasBelongsToEvents;

    protected $guarded = [];

    protected $fillable = ['username'];

    public static function setupTable()
    {
        Schema::create('profiles', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->timestamps();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
