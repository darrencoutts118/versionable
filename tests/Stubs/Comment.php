<?php

namespace Tests\Stubs;

use Chelout\RelationshipEvents\Concerns\HasMorphToEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Comment extends Model
{
    use HasMorphToEvents;

    protected $guarded = [];

    public static function setupTable()
    {
        Schema::create('comments', function ($table) {
            $table->increments('id');
            $table->string('commentable_id')->nullable();
            $table->string('commentable_type')->nullable();
            $table->string('body')->nullable();
            $table->timestamps();
        });
    }

    public function post()
    {
        return $this->morphTo(Post::class);
    }
}
