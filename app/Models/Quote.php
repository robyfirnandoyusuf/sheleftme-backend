<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;
    protected $appends = [
        'created_at_formatted',
        'account_name'
    ];

    public function getCreatedAtFormattedAttribute()
    {
        return $this->created_at->format('d, M Y H:i:s');
    }

    public function getAccountNameAttribute()
    {
        $j = json_decode($this->json);
        // dd();
        $firstName = $j->from->first_name;
        $lastName = $j->from->last_name;

        return "$firstName $lastName";
    }
}
