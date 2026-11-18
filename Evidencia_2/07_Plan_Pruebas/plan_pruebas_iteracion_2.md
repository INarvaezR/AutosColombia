# PLAN DE PRUEBAS
## Sistema de gestión de parqueadero Autos Colombia
**Evidencia de Aprendizaje 2 – Iteración 2**

### 1. Objetivo

Definir las pruebas necesarias para verificar el funcionamiento de los módulos de gestión de usuarios y gestión de celdas del sistema Autos Colombia, comprobando que las operaciones principales respondan correctamente y que se validen los datos ingresados.

### 2. Alcance

Las pruebas contemplan el registro, consulta y actualización de usuarios; el registro y consulta de celdas; la asignación y liberación de celdas; y el control de disponibilidad y mantenimiento.

### 3. Tipos de prueba

- **Pruebas funcionales:** verifican que cada operación cumpla el comportamiento esperado.
- **Pruebas de validación:** comprueban que el sistema controle los datos obligatorios y los registros duplicados.
- **Pruebas de integridad:** verifican que no se presenten asignaciones inconsistentes de celdas.
- **Pruebas de interfaz:** revisan que los formularios, mensajes y opciones sean comprensibles.

### 4. Casos de prueba

| Código | Funcionalidad | Acción de prueba | Resultado esperado |
|---|---|---|---|
| CP-01 | Registro de usuarios | Ingresar los datos completos de un usuario. | El sistema registra el usuario correctamente. |
| CP-02 | Validación de usuarios | Intentar registrar un usuario sin completar los campos obligatorios. | El sistema solicita completar la información. |
| CP-03 | Usuarios duplicados | Intentar registrar un documento de identidad ya existente. | El sistema impide el registro duplicado. |
| CP-04 | Consulta de usuarios | Consultar los usuarios registrados. | El sistema muestra la información disponible. |
| CP-05 | Actualización de usuarios | Modificar los datos de un usuario existente. | El sistema actualiza la información. |
| CP-06 | Registro de celdas | Registrar una celda con identificación válida. | La celda queda registrada como Disponible. |
| CP-07 | Celdas duplicadas | Registrar una celda con una identificación existente. | El sistema impide duplicar la celda. |
| CP-08 | Consulta de disponibilidad | Consultar los estados de las celdas. | Se identifican las celdas Disponibles, Ocupadas y En mantenimiento. |
| CP-09 | Asignación de celdas | Asignar una celda disponible a un vehículo. | La celda cambia a estado Ocupada. |
| CP-10 | Asignación no permitida | Intentar asignar una celda ocupada o en mantenimiento. | El sistema rechaza la asignación. |
| CP-11 | Liberación de celdas | Liberar una celda ocupada. | Se finaliza la ocupación y se actualiza el estado correspondiente. |
| CP-12 | Mantenimiento | Marcar una celda como En mantenimiento. | La celda deja de estar disponible para asignaciones. |

### 5. Registro de resultados

Los resultados obtenidos deberán registrarse después de ejecutar cada prueba en la aplicación. Para ello se utilizarán los siguientes estados:

- **Aprobada:** el resultado obtenido coincide con el esperado.
- **Fallida:** el resultado obtenido no coincide con el esperado.
- **Pendiente:** la prueba todavía no se ha ejecutado.

No se deben marcar las pruebas como aprobadas sin haberlas ejecutado y comprobado.

### 6. Evidencias

Para respaldar la ejecución de las pruebas se recopilarán capturas de pantalla de los formularios, consultas, mensajes de validación y operaciones realizadas en el sistema.

### 7. Conclusión

El plan de pruebas permite establecer un procedimiento organizado para evaluar los módulos propuestos en la segunda iteración de Autos Colombia. Su aplicación ayudará a identificar errores, validar las reglas de negocio y comprobar que las funcionalidades de usuarios y celdas respondan a las necesidades del parqueadero.


## 8. Ejecución y resultados de las pruebas

Se realizaron pruebas funcionales sobre los módulos de usuarios, vehículos, celdas y parqueadero del sistema Autos Colombia.

| Código | Prueba realizada | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|
| PF-01 | Registrar entrada de un vehículo y asignar una celda | Registrar la entrada y ocupar la celda | La operación se realizó correctamente | Aprobada |
| PF-02 | Registrar la salida de un vehículo | Registrar la salida y liberar la celda | La operación se realizó correctamente | Aprobada |
| PF-03 | Intentar ingresar un vehículo que ya está dentro | Impedir el registro duplicado | El sistema controló el registro duplicado | Aprobada |
| PF-04 | Intentar asignar una celda ocupada | Impedir una segunda asignación | El sistema evitó la asignación | Aprobada |
| PF-05 | Asignar una celda incompatible con el vehículo | Rechazar la asignación | El sistema rechazó la operación | Aprobada |

### 9. Conclusión de las pruebas

Las pruebas funcionales permitieron comprobar el comportamiento de los procesos principales del sistema Autos Colombia. Se verificó el registro de entradas y salidas, la asignación y liberación de celdas, el control de registros duplicados y las restricciones de ocupación y compatibilidad.

Los resultados obtenidos fueron satisfactorios y coincidieron con los resultados esperados. Por lo tanto, se considera validada la integración funcional de los módulos evaluados en esta iteración.
