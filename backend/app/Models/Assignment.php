<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assignment extends Model { protected $guarded=[]; protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime']; public function request(){return $this->belongsTo(VehicleRequest::class,'vehicle_request_id');} public function vehicle(){return $this->belongsTo(Vehicle::class);} public function driver(){return $this->belongsTo(Driver::class);} public function route(){return $this->hasOne(RouteSheet::class);} public function mileage(){return $this->hasOne(Mileage::class);} }
