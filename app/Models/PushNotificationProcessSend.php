<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationProcessSend extends Model
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
    protected $table = 'pushnotification_process_send';

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
        'id_pushnotification',
        'status_send',
        'created_at',
        'updated_at'
    ];

    /**
     * Cast attributes
     * 
     * @var array
     */
    protected $casts = [
        'id_pushnotification' => 'integer',
        'status_send' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi dengan PushNotification
     */
    public function pushNotification()
    {
        return $this->belongsTo(PushNotification::class, 'id_pushnotification', 'id');
    }
}

