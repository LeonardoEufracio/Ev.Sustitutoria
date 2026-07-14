# Requerimientos
El sistema administra solicitudes y asignaciones de movilidad empresarial. Los actores son solicitante, coordinador, conductor y administrador. El flujo prioritario es autenticación, solicitud, evaluación, programación, hoja de ruta, kilometraje, cierre y actualización estadística.

## Reglas
Las solicitudes registradas fuera de lunes a viernes, 08:00–16:00 (zona `America/Lima`), se marcan sujetas a disponibilidad. No se asignan vehículos en mantenimiento, inoperativos o no disponibles, ni vehículos sin capacidad suficiente. Vehículos y conductores no pueden cruzar horarios. El conductor registra odómetro inicial y final; el servidor calcula automáticamente la diferencia, impide retrocesos del odómetro y registra salida y retorno. El cierre libera ambos recursos y marca la solicitud atendida. Cada operación crítica se audita.
