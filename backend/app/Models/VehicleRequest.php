<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleRequest extends Model { protected $table='vehicle_requests'; protected $guarded=[]; protected $casts=['service_date'=>'date','registered_at'=>'datetime','subject_to_availability'=>'boolean']; public function employee(){return $this->belongsTo(Employee::class);} }
