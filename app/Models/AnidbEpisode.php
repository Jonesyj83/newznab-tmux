<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Yadakhov\InsertOnDuplicateKey;

class AnidbEpisode extends Model
{
    use InsertOnDuplicateKey;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var string
     */
    protected $primaryKey = 'anidbid';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function title()
    {
        return $this->belongsTo(AnidbTitle::class, 'anidbid');
    }

    public function info()
    {
        return $this->hasMany(AnidbInfo::class, 'anidbid');
    }
}
