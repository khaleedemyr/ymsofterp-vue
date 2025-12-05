<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationTarget extends Model
{
    /**
     * Menentukan connection database yang digunakan
     * 
     * @var string
     */
    protected $connection = 'mysql_second';

    /**
     * Nama tabel di database kedua
     * 
     * @var string
     */
    protected $table = 'pushnotification_target';

    /**
     * Primary key
     * 
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Mass assignment protection
     * 
     * @var array
     */
    protected $fillable = [
        'email_member',
        'token',
        'id_pushnotification'
    ];

    /**
     * Disable timestamps if table doesn't have created_at/updated_at columns
     */
    public $timestamps = false;

    /**
     * Cast attributes
     * 
     * @var array
     */
    protected $casts = [
        'id_pushnotification' => 'integer',
    ];

    /**
     * Relasi dengan PushNotification
     */
    public function pushNotification()
    {
        return $this->belongsTo(PushNotification::class, 'id_pushnotification', 'id');
    }
}

