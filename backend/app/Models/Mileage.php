<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Mileage extends Model
{
    protected $guarded = [];
    protected $appends = ['kilometers_traveled'];
    protected $casts = ['initial' => 'float', 'final' => 'float'];

    protected function kilometersTraveled(): Attribute
    {
        return Attribute::get(fn () => $this->initial !== null && $this->final !== null
            ? round($this->final - $this->initial, 2)
            : null);
    }
}
