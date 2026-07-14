# Arquitectura
Arquitectura cliente-servidor de tres capas: Angular presenta y valida interacción; Laravel expone API REST, autorización y reglas transaccionales; SQL Server persiste e integra restricciones. Angular envía JWT en `Authorization: Bearer`. Laravel valida firma/expiración, rol y entrada antes de acceder mediante Eloquent.
