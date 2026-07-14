<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Employee extends Model { protected $fillable=['area_id','dni','full_name','position','phone']; public function area(){return $this->belongsTo(Area::class);} }
