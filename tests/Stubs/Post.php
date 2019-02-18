<?php

namespace Tests\Stubs;

use Chelout\RelationshipEvents\Concerns\HasMorphManyEvents;
use Chelout\RelationshipEvents\Concerns\HasMorphToManyEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Post extends Model
{
    use HasMorphManyEvents,
        HasMorphToManyEvents;

    protected $guarded = [];

    protected $fillable = ['title'];

    public static function setupTable()
    {
        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot('pivot_field');
    }
}
