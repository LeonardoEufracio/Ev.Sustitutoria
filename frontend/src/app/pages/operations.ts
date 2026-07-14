import {Component, OnInit} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {DatePipe, DecimalPipe} from '@angular/common';
import {Api} from '../core/api.service';

@Component({
  imports: [FormsModule],
  template: `<div class="title"><div><h1>Bandeja del coordinador</h1><p>Solicitudes pendientes de evaluación</p></div></div>
  @if(message){<p class="notice">{{message}}</p>}
  <section class="panel"><table><thead><tr><th>Código</th><th>Área</th><th>Destino</th><th>Hora</th><th>Personas</th><th>Condición</th><th>Acciones</th></tr></thead><tbody>
  @for(x of rows;track x.id){<tr><td>{{x.code}}</td><td>{{x.employee?.area?.name}}</td><td>{{x.destination}}</td><td>{{x.required_time}}</td><td>{{x.passenger_count}}</td><td>{{x.subject_to_availability?'Sujeta a disponibilidad':'Horario regular'}}</td><td><button (click)="approve(x.id)">Aprobar</button> <button class="danger" (click)="reject(x.id)">Rechazar</button></td></tr>}
  @empty{<tr><td colspan="7">No hay solicitudes pendientes.</td></tr>}
  </tbody></table></section>`
})
export class Bandeja implements OnInit {
  rows:any[]=[]; message='';
  constructor(private api:Api){}
  ngOnInit(){this.load()}
  load(){this.api.get('/solicitudes/pendientes').subscribe(x=>this.rows=x)}
  approve(id:number){this.api.patch(`/solicitudes/${id}/aprobar`).subscribe({next:()=>{this.message='Solicitud aprobada.';this.load()},error:e=>this.message=e.error?.message||'No se pudo aprobar.'})}
  reject(id:number){const reason=window.prompt('Indique el motivo del rechazo:')?.trim();if(!reason)return;this.api.patch(`/solicitudes/${id}/rechazar`,{reason}).subscribe({next:()=>{this.message='Solicitud rechazada.';this.load()},error:e=>this.message=e.error?.message||'No se pudo rechazar.'})}
}

@Component({
  imports:[FormsModule],
  template:`<div class="title"><div><h1>Programación vehicular</h1><p>Asigna recursos y genera la hoja de ruta</p></div></div>
  <form class="panel form" (ngSubmit)="save()">
    <label>Solicitud<select name="request" [(ngModel)]="f.vehicle_request_id" required><option [ngValue]="undefined">Seleccione</option>@for(x of requests;track x.id){<option [ngValue]="x.id">{{x.code}} · {{x.destination}} · {{x.service_date}}</option>}</select></label>
    <label>Vehículo<select name="vehicle" [(ngModel)]="f.vehicle_id" required><option [ngValue]="undefined">Seleccione</option>@for(x of vehicles;track x.id){<option [ngValue]="x.id">{{x.plate}} · {{x.brand}} {{x.model}} ({{x.passenger_capacity}} pasajeros)</option>}</select></label>
    <label>Conductor<select name="driver" [(ngModel)]="f.driver_id" required><option [ngValue]="undefined">Seleccione</option>@for(x of drivers;track x.id){<option [ngValue]="x.id">{{x.full_name}}</option>}</select></label>
    <label>Inicio<input type="datetime-local" name="start" [(ngModel)]="f.starts_at" required></label>
    <label>Fin<input type="datetime-local" name="end" [(ngModel)]="f.ends_at" required></label>
    <label class="wide">Indicaciones<textarea name="instructions" [(ngModel)]="f.instructions"></textarea></label>
    <button>Crear programación</button><p>{{message}}</p>
  </form>`
})
export class Programacion implements OnInit {
  requests:any[]=[];vehicles:any[]=[];drivers:any[]=[];f:any={};message='';
  constructor(private api:Api){}
  ngOnInit(){this.load()}
  load(){this.api.get('/solicitudes').subscribe(x=>this.requests=x.data.filter((r:any)=>r.status==='Evaluada'));this.api.get('/vehiculos/disponibles').subscribe(x=>this.vehicles=x);this.api.get('/conductores/disponibles').subscribe(x=>this.drivers=x)}
  save(){this.message='';this.api.post('/programacion',this.f).subscribe({next:()=>{this.message='Programación creada correctamente.';this.f={};this.load()},error:e=>this.message=e.error?.message||this.validation(e)||'No se pudo programar.'})}
  validation(e:any){const errors=e.error?.errors;return errors?Object.values(errors).flat().join(' '):''}
}

@Component({template:`<div class="title"><div><h1>{{title}}</h1><p>Catálogo operativo</p></div></div><section class="panel"><table><thead><tr>@for(h of headers;track h){<th>{{h}}</th>}</tr></thead><tbody>@for(x of rows;track x.id){<tr>@for(k of keys;track k){<td>{{x[k]}}</td>}</tr>}</tbody></table></section>`})
export class Catalogo implements OnInit {
  rows:any[]=[];title='';headers:string[]=[];keys:string[]=[];
  constructor(private api:Api){}
  ngOnInit(){const vehicle=location.pathname.includes('vehiculos');this.title=vehicle?'Vehículos':'Conductores';this.headers=vehicle?['Placa','Marca','Modelo','Capacidad','Estado']:['DNI','Nombre','Licencia','Categoría','Estado'];this.keys=vehicle?['plate','brand','model','passenger_capacity','status']:['dni','full_name','license','category','status'];this.api.get(vehicle?'/vehiculos':'/conductores').subscribe(x=>this.rows=x)}
}

@Component({
  imports:[FormsModule,DatePipe,DecimalPipe],
  template:`<div class="title"><div><h1>Mis rutas</h1><p>Registra los odómetros; los kilómetros se calculan automáticamente</p></div></div>
  @if(globalMessage){<p class="notice">{{globalMessage}}</p>}
  @for(x of rows;track x.id){
    <article class="panel route">
      <div class="route-head"><div><span class="badge">{{x.status}}</span><h2>{{x.route?.origin}} → {{x.route?.destination}}</h2><p>{{x.starts_at|date:'dd/MM/yyyy HH:mm'}} · {{x.vehicle?.brand}} {{x.vehicle?.model}} · {{x.vehicle?.plate}}</p></div></div>
      @if(x.status==='Programada'){
        <label>Odómetro inicial (km)<input type="number" min="0" step="0.01" [(ngModel)]="form[x.id].initial"></label>
        <button (click)="start(x)">Iniciar ruta</button>
      } @else {
        <div class="odometer"><span>Odómetro inicial<strong>{{x.mileage?.initial|number:'1.0-2'}} km</strong></span><span>Odómetro final<strong>{{form[x.id].final||'—'}} km</strong></span><span>Kilómetros calculados<strong>{{calculated(x.id)|number:'1.0-2'}} km</strong></span></div>
        <label>Odómetro final (km)<input type="number" [min]="x.mileage?.initial" step="0.01" [(ngModel)]="form[x.id].final"></label>
        <button (click)="finish(x)">Registrar retorno y finalizar</button>
      }
      <small class="error">{{form[x.id].message}}</small>
    </article>
  } @empty {<section class="panel">No tienes rutas activas.</section>}`
})
export class Rutas implements OnInit {
  rows:any[]=[];form:any={};globalMessage='';
  constructor(private api:Api){}
  ngOnInit(){this.load()}
  load(){this.api.get('/conductor/rutas').subscribe(x=>{this.rows=x;x.forEach((a:any)=>this.form[a.id]={...(this.form[a.id]||{}),initial:a.mileage?.initial??undefined})})}
  calculated(id:number){const route=this.rows.find(x=>x.id===id);const initial=Number(route?.mileage?.initial??this.form[id]?.initial);const final=Number(this.form[id]?.final);return Number.isFinite(initial)&&Number.isFinite(final)&&final>=initial?final-initial:0}
  start(route:any){const initial=Number(this.form[route.id].initial);if(!Number.isFinite(initial)||initial<0){this.form[route.id].message='Ingrese un odómetro inicial válido.';return}this.api.post('/kilometraje',{assignment_id:route.id,initial}).subscribe({next:(response:any)=>{route.status='En ruta';route.mileage=response.data;this.form[route.id].message=response.message},error:e=>this.form[route.id].message=e.error?.message||this.validation(e)})}
  finish(route:any){const final=Number(this.form[route.id].final);if(!Number.isFinite(final)||final<Number(route.mileage?.initial)){this.form[route.id].message='El odómetro final debe ser igual o mayor al inicial.';return}this.api.post('/kilometraje',{assignment_id:route.id,final}).subscribe({next:(response:any)=>{const kilometers=response.data.kilometers_traveled;this.api.patch(`/conductor/finalizar-servicio/${route.id}`).subscribe({next:()=>{this.globalMessage=`Servicio finalizado: ${kilometers} km recorridos.`;this.load()},error:e=>this.form[route.id].message=e.error?.message||this.validation(e)})},error:e=>this.form[route.id].message=e.error?.message||this.validation(e)})}
  validation(e:any){const errors=e.error?.errors;return errors?Object.values(errors).flat().join(' '):'No se pudo procesar la operación.'}
}
