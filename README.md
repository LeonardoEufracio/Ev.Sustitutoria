# Gestión de Unidades Vehiculares
Sistema Angular + Laravel API REST + SQL Server con JWT.

## Instalación
1. Ejecute `database/sqlserver.sql` en SQL Server.
2. En `backend`, copie `.env.example` a `.env`; configure `DB_CONNECTION=sqlsrv`, `DB_HOST`, `DB_PORT=1433`, `DB_DATABASE=GestionFlota`, usuario, clave y `JWT_SECRET`.
3. Ejecute `composer install`, `php artisan key:generate` y `php artisan migrate --seed`.
4. Cree cada cuenta con `php artisan user:create`. El comando solicita la contraseña de manera oculta y guarda únicamente su hash bcrypt en la base de datos.
5. Ejecute `php artisan serve`. En `frontend`, ejecute `npm install` y `npm start`; abra `http://localhost:4200`.

Para una demo rápida con SQLite use la configuración creada por Laravel y `php artisan migrate:fresh --seed`; después cree las cuentas con `php artisan user:create`. No existen usuarios ni contraseñas precargadas en el frontend, backend o `.env`.

## Pruebas
Backend: `php artisan test`. Frontend: `npm run build`.

## Seguridad
No versionar `.env`. Cambiar credenciales demo y `JWT_SECRET` antes de desplegar. Servir exclusivamente por HTTPS en producción.
