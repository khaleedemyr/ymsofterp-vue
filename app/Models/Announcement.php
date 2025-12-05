<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'announcements';

    public function targets()
    {
        return $this->hasMany(AnnouncementTarget::class, 'announcement_id');
    }

    public function files()
    {
        return $this->hasMany(AnnouncementFile::class, 'announcement_id');
    }
}
