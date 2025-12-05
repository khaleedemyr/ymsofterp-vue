<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketHistory extends Model
{
    use HasFactory;

    protected $table = 'ticket_history';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    // Helper methods
    public function getTimeAgo()
    {
        return $this->created_at->diffForHumans();
    }

    public function getActionText()
    {
        $actions = [
            'created' => 'Ticket created',
            'updated' => 'Ticket updated',
            'assigned' => 'Ticket assigned',
            'status_changed' => 'Status changed',
            'priority_changed' => 'Priority changed',
            'category_changed' => 'Category changed',
            'comment_added' => 'Comment added',
            'attachment_added' => 'Attachment added',
            'resolved' => 'Ticket resolved',
            'closed' => 'Ticket closed',
            'reopened' => 'Ticket reopened',
        ];

        return $actions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    public function getChangeDescription()
    {
        if ($this->field_name && $this->old_value && $this->new_value) {
            return "Changed {$this->field_name} from '{$this->old_value}' to '{$this->new_value}'";
        }

        if ($this->description) {
            return $this->description;
        }

        return $this->getActionText();
    }
}
