namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceMember extends Model
{
    protected $table = 'maintenance_members';
    
    protected $fillable = [
        'task_id',
        'user_id',
        'role',
    ];

    public function task()
    {
        return $this->belongsTo(MaintenanceTask::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
