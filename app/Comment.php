<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $table = 'comments';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.


    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo('App\User','user_id');
    }

    public function isAuthor($userId)
    {
        return $this->user_id == $userId;
    }

    public function parent()
    {
        return $this->belongsTo('App\Comment', 'parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Replies are stored without commentable, so the report comes from the parent comment.
     */
    public function relatedReport(): ?Report
    {
        $commentable = $this->isReply() ? $this->parent?->commentable : $this->commentable;

        return $commentable instanceof Report ? $commentable : null;
    }

    //Comment have many replies
    public function replies()
    {
        return $this->hasMany('App\Comment', 'parent_id');
    }
}