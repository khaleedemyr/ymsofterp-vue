<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
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
    protected $table = 'pushnotification';

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
        'title',
        'body',
        'target',
        'photo',
        'status_send'
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
        // status_send is enum('0','1','2') as string, not integer
    ];

    /**
     * Relasi dengan PushNotificationTarget
     */
    public function targets()
    {
        return $this->hasMany(PushNotificationTarget::class, 'id_pushnotification', 'id');
    }

    /**
     * Relasi dengan PushNotificationProcessSend
     */
    public function processSends()
    {
        return $this->hasMany(PushNotificationProcessSend::class, 'id_pushnotification', 'id');
    }

    /**
     * Get status text
     * status_send is enum('0','1','2') as string
     */
    public function getStatusTextAttribute()
    {
        $statusMessages = [
            '0' => 'belum terkirim',
            '1' => 'terkirim',
            '2' => 'sedang di proses',
        ];
        
        return $statusMessages[$this->status_send] ?? 'status tidak diketahui';
    }
}

