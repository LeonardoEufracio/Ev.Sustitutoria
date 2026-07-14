import {Component,OnInit} from '@angular/core';
import {DecimalPipe} from '@angular/common';
import {Api} from '../core/api.service';

@Component({
  imports:[DecimalPipe],
  template:`<div class="title"><div><h1>Dashboard</h1><p>Resumen operativo de la flota</p></div></div>
  <div class="cards"><article><i>Solicitudes pendientes</i><strong>{{d?.requests?.pending||0}}</strong></article><article><i>Solicitudes atendidas</i><strong>{{d?.requests?.attended||0}}</strong></article><article><i>Vehículos disponibles</i><strong>{{d?.vehicles?.available||0}}</strong></article><article><i>En mantenimiento</i><strong>{{d?.vehicles?.maintenance||0}}</strong></article><article><i>Conductores disponibles</i><strong>{{d?.drivers_available||0}}</strong></article><article><i>Kilómetros recorridos</i><strong>{{d?.kilometers|number:'1.0-2'}}</strong></article></div>
  <div class="summary-grid"><section class="panel"><h2>Servicios del mes</h2><div class="metric">{{d?.monthly_services||0}} <small>completados</small></div></section><section class="panel"><h2>Promedio diario</h2><div class="metric">{{d?.daily_average||0}} <small>servicios por día</small></div></section></div>
  <section class="panel"><h2>Rendimiento por conductor</h2><table><thead><tr><th>Conductor</th><th>Servicios</th><th>Kilómetros recorridos</th></tr></thead><tbody>@for(x of d?.driver_stats;track x.id){<tr><td>{{x.full_name}}</td><td>{{x.services}}</td><td>{{x.kilometers|number:'1.0-2'}} km</td></tr>}</tbody></table></section>
  <section class="panel"><h2>Uso por vehículo</h2><table><thead><tr><th>Vehículo</th><th>Placa</th><th>Atenciones</th><th>Kilómetros</th></tr></thead><tbody>@for(x of d?.vehicle_stats;track x.id){<tr><td>{{x.brand}} {{x.model}}</td><td>{{x.plate}}</td><td>{{x.services}}</td><td>{{x.kilometers|number:'1.0-2'}} km</td></tr>}</tbody></table></section>`
})
export class Dashboard implements OnInit {d:any;constructor(private api:Api){}ngOnInit(){this.api.get('/dashboard/resumen').subscribe(x=>this.d=x)}}
