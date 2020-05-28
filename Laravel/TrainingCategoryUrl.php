<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingCategoryUrl extends Model
{


    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'url_id', 'category_id', 'user_id', 'cur_user_id', 'session_expire_in' ];
}
