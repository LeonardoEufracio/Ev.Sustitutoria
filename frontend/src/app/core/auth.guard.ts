import {inject} from '@angular/core'; import {CanActivateFn,Router} from '@angular/router';
export const authGuard:CanActivateFn=()=>localStorage.getItem('token')?true:inject(Router).createUrlTree(['/login']);
export const roleGuard:CanActivateFn=(route)=>{try{const user=JSON.parse(localStorage.getItem('user')||'null');const roles=route.data?.['roles']||[];return roles.length===0||roles.includes(user?.role?.name)?true:inject(Router).createUrlTree(['/dashboard'])}catch{return inject(Router).createUrlTree(['/login'])}};
