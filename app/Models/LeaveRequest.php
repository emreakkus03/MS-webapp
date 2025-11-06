<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'member_name',
        'leave_type_id',
        'start_date',
        'end_date',
        'note',
        'status',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

       public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'approved' => 'Goedgekeurd',
            'rejected' => 'Afgewezen',
            'pending'  => 'In behandeling',
            default    => ucfirst($this->status),
        };
    }
}
