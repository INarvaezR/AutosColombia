<?php
require_once __DIR__ . '/conexion.php';

if ($conexion->connect_error) {
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$mensaje = "";
$tipoMensaje = "info";

/* Protección de salida HTML */
function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* Validar compatibilidad entre vehículo y celda */
function tipoCompatible($tipoCelda, $tipoVehiculo) {

    if ($tipoCelda === "Mixta") {
        return in_array(
            $tipoVehiculo,
            ["Automóvil", "Motocicleta", "Camioneta"],
            true
        );
    }

    if ($tipoCelda === "Carro") {
        return in_array(
            $tipoVehiculo,
            ["Automóvil", "Camioneta"],
            true
        );
    }

    if ($tipoCelda === "Moto") {
        return $tipoVehiculo === "Motocicleta";
    }

    return false;
}

/* =====================================================
   REGISTRAR ENTRADA Y ASIGNAR CELDA
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["registrar_entrada"])
) {

    $id_vehiculo = (int)($_POST["id_vehiculo"] ?? 0);
    $id_celda = (int)($_POST["id_celda"] ?? 0);

    if ($id_vehiculo <= 0 || $id_celda <= 0) {

        $mensaje = "Debes seleccionar un vehículo y una celda.";
        $tipoMensaje = "error";

    } else {

        $conexion->begin_transaction();

        try {

            /* Consultar y bloquear el vehículo */
            $consulta = $conexion->prepare(
                "SELECT tipo_vehiculo
                 FROM vehiculos
                 WHERE id_vehiculo = ?
                 FOR UPDATE"
            );

            if (!$consulta) {
                throw new Exception("No fue posible consultar el vehículo.");
            }

            $consulta->bind_param("i", $id_vehiculo);
            $consulta->execute();
            $consulta->bind_result($tipoVehiculo);

            if (!$consulta->fetch()) {
                $consulta->close();
                throw new Exception("El vehículo seleccionado no existe.");
            }

            $consulta->close();

            /* Comprobar que el vehículo no esté dentro */
            $consulta = $conexion->prepare(
                "SELECT id_registro
                 FROM registros_parqueo
                 WHERE id_vehiculo = ?
                 AND fecha_salida IS NULL
                 LIMIT 1"
            );

            if (!$consulta) {
                throw new Exception("No fue posible validar el ingreso.");
            }

            $consulta->bind_param("i", $id_vehiculo);
            $consulta->execute();
            $consulta->store_result();

            $yaEstaDentro = $consulta->num_rows > 0;
            $consulta->close();

            if ($yaEstaDentro) {
                throw new Exception(
                    "Este vehículo ya se encuentra dentro del parqueadero."
                );
            }

            /* Consultar y bloquear la celda */
            $consulta = $conexion->prepare(
                "SELECT tipo, estado
                 FROM celdas
                 WHERE id_celda = ?
                 FOR UPDATE"
            );

            if (!$consulta) {
                throw new Exception("No fue posible consultar la celda.");
            }

            $consulta->bind_param("i", $id_celda);
            $consulta->execute();
            $consulta->bind_result($tipoCelda, $estadoCelda);

            if (!$consulta->fetch()) {
                $consulta->close();
                throw new Exception("La celda seleccionada no existe.");
            }

            $consulta->close();

            if ($estadoCelda !== "Disponible") {
                throw new Exception(
                    "La celda seleccionada no está disponible."
                );
            }

            if (!tipoCompatible($tipoCelda, $tipoVehiculo)) {
                throw new Exception(
                    "El tipo de celda no es compatible con este vehículo."
                );
            }

            /* Registrar entrada */
            $consulta = $conexion->prepare(
                "INSERT INTO registros_parqueo
                    (id_vehiculo, fecha_entrada)
                 VALUES (?, NOW())"
            );

            if (!$consulta) {
                throw new Exception("No fue posible preparar la entrada.");
            }

            $consulta->bind_param("i", $id_vehiculo);

            if (!$consulta->execute()) {
                throw new Exception("No fue posible registrar la entrada.");
            }

            $id_registro = $conexion->insert_id;
            $consulta->close();

            /* Crear asignación de celda */
            $consulta = $conexion->prepare(
                "INSERT INTO asignaciones_celdas
                    (id_celda, id_vehiculo, fecha_asignacion)
                 VALUES (?, ?, NOW())"
            );

            if (!$consulta) {
                throw new Exception("No fue posible preparar la asignación.");
            }

            $consulta->bind_param(
                "ii",
                $id_celda,
                $id_vehiculo
            );

            if (!$consulta->execute()) {
                throw new Exception("No fue posible asignar la celda.");
            }

            $consulta->close();

            /* Actualizar estado de la celda */
            $consulta = $conexion->prepare(
                "UPDATE celdas
                 SET estado = 'Ocupada'
                 WHERE id_celda = ?
                 AND estado = 'Disponible'"
            );

            if (!$consulta) {
                throw new Exception("No fue posible actualizar la celda.");
            }

            $consulta->bind_param("i", $id_celda);
            $consulta->execute();

            if ($consulta->affected_rows !== 1) {
                $consulta->close();
                throw new Exception("No se pudo ocupar la celda.");
            }

            $consulta->close();

            $conexion->commit();

            $mensaje = "Entrada registrada y celda asignada correctamente.";
            $tipoMensaje = "exito";

        } catch (Exception $e) {

            $conexion->rollback();

            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

/* =====================================================
   REGISTRAR SALIDA Y LIBERAR CELDA
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["registrar_salida"])
) {

    $id_registro = (int)($_POST["id_registro"] ?? 0);

    if ($id_registro <= 0) {

        $mensaje = "El registro seleccionado no es válido.";
        $tipoMensaje = "error";

    } else {

        $conexion->begin_transaction();

        try {

            /* Consultar el registro activo y su asignación */
            $consulta = $conexion->prepare(
                "SELECT
                    r.id_registro,
                    a.id_asignacion,
                    a.id_celda
                 FROM registros_parqueo r
                 LEFT JOIN asignaciones_celdas a
                    ON a.id_vehiculo = r.id_vehiculo
                    AND a.fecha_liberacion IS NULL
                 WHERE r.id_registro = ?
                 AND r.fecha_salida IS NULL
                 LIMIT 1
                 FOR UPDATE"
            );

            if (!$consulta) {
                throw new Exception("No fue posible consultar la entrada.");
            }

            $consulta->bind_param("i", $id_registro);
            $consulta->execute();
            $consulta->bind_result(
                $registroEncontrado,
                $id_asignacion,
                $id_celda
            );

            if (!$consulta->fetch()) {
                $consulta->close();

                throw new Exception(
                    "El registro no existe o ya tiene salida."
                );
            }

            $consulta->close();

            /* Registrar fecha de salida */
            $consulta = $conexion->prepare(
                "UPDATE registros_parqueo
                 SET fecha_salida = NOW()
                 WHERE id_registro = ?
                 AND fecha_salida IS NULL"
            );

            if (!$consulta) {
                throw new Exception("No fue posible preparar la salida.");
            }

            $consulta->bind_param("i", $id_registro);
            $consulta->execute();

            if ($consulta->affected_rows !== 1) {
                $consulta->close();
                throw new Exception("No fue posible registrar la salida.");
            }

            $consulta->close();

            /* Cerrar asignación y liberar celda */
            if ($id_asignacion !== null) {

                $consulta = $conexion->prepare(
                    "UPDATE asignaciones_celdas
                     SET fecha_liberacion = NOW()
                     WHERE id_asignacion = ?
                     AND fecha_liberacion IS NULL"
                );

                if (!$consulta) {
                    throw new Exception(
                        "No fue posible cerrar la asignación."
                    );
                }

                $consulta->bind_param("i", $id_asignacion);
                $consulta->execute();

                if ($consulta->affected_rows !== 1) {
                    $consulta->close();
                    throw new Exception(
                        "No fue posible liberar la asignación."
                    );
                }

                $consulta->close();

                $consulta = $conexion->prepare(
                    "UPDATE celdas
                     SET estado = 'Disponible'
                     WHERE id_celda = ?
                     AND estado = 'Ocupada'"
                );

                if (!$consulta) {
                    throw new Exception("No fue posible liberar la celda.");
                }

                $consulta->bind_param("i", $id_celda);
                $consulta->execute();

                if ($consulta->affected_rows !== 1) {
                    $consulta->close();
                    throw new Exception(
                        "No fue posible actualizar el estado de la celda."
                    );
                }

                $consulta->close();

            }

            $conexion->commit();

            $mensaje = "Salida registrada y celda liberada correctamente.";
            $tipoMensaje = "exito";

        } catch (Exception $e) {

            $conexion->rollback();

            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

/* =====================================================
   GUARDAR OBSERVACIONES
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["guardar_observacion"])
) {

    $id_registro = (int)($_POST["id_registro"] ?? 0);
    $observaciones = trim($_POST["observaciones"] ?? "");

    if ($id_registro <= 0) {

        $mensaje = "El registro seleccionado no es válido.";
        $tipoMensaje = "error";

    } else {

        $consulta = $conexion->prepare(
            "UPDATE registros_parqueo
             SET observaciones = ?
             WHERE id_registro = ?
             AND fecha_salida IS NULL"
        );

        if ($consulta) {

            $consulta->bind_param(
                "si",
                $observaciones,
                $id_registro
            );

            if ($consulta->execute()) {
                $mensaje = "Observación guardada correctamente.";
                $tipoMensaje = "exito";
            } else {
                $mensaje = "No fue posible guardar la observación.";
                $tipoMensaje = "error";
            }

            $consulta->close();

        } else {

            $mensaje = "No fue posible procesar la observación.";
            $tipoMensaje = "error";
        }
    }
}

/* =====================================================
   CONSULTAR VEHÍCULOS DISPONIBLES PARA INGRESO
===================================================== */

$vehiculos = $conexion->query(
    "SELECT
        v.id_vehiculo,
        v.placa,
        v.tipo_vehiculo
     FROM vehiculos v
     WHERE NOT EXISTS (
        SELECT 1
        FROM registros_parqueo r
        WHERE r.id_vehiculo = v.id_vehiculo
        AND r.fecha_salida IS NULL
     )
     ORDER BY v.placa"
);

/* Consultar celdas disponibles */
$celdas = $conexion->query(
    "SELECT id_celda, codigo, tipo, ubicacion
     FROM celdas
     WHERE estado = 'Disponible'
     ORDER BY codigo"
);

/* Consultar vehículos dentro del parqueadero */
$registros = $conexion->query(
    "SELECT
        r.id_registro,
        v.placa,
        v.tipo_vehiculo,
        r.fecha_entrada,
        r.observaciones,
        u.nombres,
        u.apellidos,
        c.codigo AS codigo_celda
     FROM registros_parqueo r
     INNER JOIN vehiculos v
        ON r.id_vehiculo = v.id_vehiculo
     LEFT JOIN usuarios u
        ON v.id_usuario = u.id_usuario
     LEFT JOIN asignaciones_celdas a
        ON a.id_vehiculo = v.id_vehiculo
        AND a.fecha_liberacion IS NULL
     LEFT JOIN celdas c
        ON a.id_celda = c.id_celda
     WHERE r.fecha_salida IS NULL
     ORDER BY r.fecha_entrada DESC"
);

$errorConsulta = (
    $vehiculos === false
    || $celdas === false
    || $registros === false
);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Parqueadero | Autos Colombia</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6fa;
            margin: 0;
            color: #243447;
        }

        header {
            background: #0d3761;
            color: white;
            padding: 24px 6%;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            color: #dce8f5;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 14px 6%;
            background: white;
            border-bottom: 1px solid #dce3eb;
        }

        nav a {
            padding: 10px 14px;
            color: #0d3761;
            text-decoration: none;
            font-weight: bold;
            border-radius: 6px;
        }

        nav a:hover,
        nav a.activo {
            background: #0d3761;
            color: white;
        }

        main {
            max-width: 1300px;
            margin: 32px auto;
            padding: 0 20px;
        }

        section {
            background: white;
            padding: 24px;
            margin-bottom: 22px;
            border-radius: 12px;
            border: 1px solid #e0e7ef;
            box-shadow: 0 3px 10px rgba(13, 55, 97, 0.05);
        }

        h2, h3 {
            color: #0d3761;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
        }

        select, textarea {
            width: 100%;
            max-width: 500px;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: Arial, sans-serif;
        }

        button {
            padding: 10px 15px;
            background: #1a8a52;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #146b40;
        }

        .btn-salida {
            background: #b45309;
        }

        .btn-salida:hover {
            background: #92400e;
        }

        .mensaje {
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .exito {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .info {
            background: #e0f2fe;
            color: #075985;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e0e7ef;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #0d3761;
            color: white;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .sin-datos {
            color: #64748b;
        }

        footer {
            text-align: center;
            padding: 24px;
            color: #627184;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            header {
                padding: 22px 20px;
            }

            main {
                margin-top: 20px;
            }

            section {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Autos Colombia</h1>
    <p>Sistema de información para la gestión del parqueadero</p>
</header>

<nav aria-label="Navegación principal">
    <a href="index.php">Inicio</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="vehiculos.php">Vehículos</a>
    <a href="celdas.php">Celdas</a>
    <a href="parqueadero.php" class="activo">Parqueadero</a>
    <a href="historial.php">Historial</a>
</nav>

<main>

    <h2>Control de entradas y salidas</h2>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= escapar($tipoMensaje) ?>">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorConsulta): ?>

        <div class="mensaje error">
            No fue posible consultar toda la información.
            Verifica las tablas de la base de datos.
        </div>

    <?php else: ?>

        <section>

            <h3>Registrar entrada y asignar celda</h3>

            <p>
                Selecciona el vehículo y una celda disponible.
                El sistema validará su compatibilidad.
            </p>

            <form method="POST">

                <label for="id_vehiculo">
                    Vehículo:
                </label>

                <select
                    id="id_vehiculo"
                    name="id_vehiculo"
                    required
                >

                    <option value="">
                        Seleccionar vehículo
                    </option>

                    <?php while ($v = $vehiculos->fetch_assoc()): ?>

                        <option value="<?= (int)$v["id_vehiculo"] ?>">

                            <?= escapar($v["placa"]) ?>
                            -
                            <?= escapar($v["tipo_vehiculo"]) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <label for="id_celda">
                    Celda disponible:
                </label>

                <select
                    id="id_celda"
                    name="id_celda"
                    required
                >

                    <option value="">
                        Seleccionar celda
                    </option>

                    <?php while ($c = $celdas->fetch_assoc()): ?>

                        <option value="<?= (int)$c["id_celda"] ?>">

                            <?= escapar($c["codigo"]) ?>
                            -
                            <?= escapar($c["tipo"]) ?>

                            <?php if (!empty($c["ubicacion"])): ?>
                                - <?= escapar($c["ubicacion"]) ?>
                            <?php endif; ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <br><br>

                <button
                    type="submit"
                    name="registrar_entrada"
                >
                    Registrar entrada
                </button>

            </form>

        </section>

        <section>

            <h3>Vehículos actualmente en el parqueadero</h3>

            <div class="tabla-contenedor">

                <table>

                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Tipo</th>
                            <th>Propietario</th>
                            <th>Celda</th>
                            <th>Fecha de entrada</th>
                            <th>Observaciones</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($registros->num_rows > 0): ?>

                        <?php while ($r = $registros->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= escapar($r["placa"]) ?>
                                </td>

                                <td>
                                    <?= escapar($r["tipo_vehiculo"]) ?>
                                </td>

                                <td>
                                    <?= !empty($r["nombres"])
                                        ? escapar(
                                            $r["nombres"] . " " .
                                            $r["apellidos"]
                                        )
                                        : "Sin propietario asociado" ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        $r["codigo_celda"] ?? "Sin celda"
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar($r["fecha_entrada"]) ?>
                                </td>

                                <td>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="id_registro"
                                            value="<?= (int)$r["id_registro"] ?>"
                                        >

                                        <textarea
                                            name="observaciones"
                                            rows="3"
                                            maxlength="255"
                                            placeholder="Escribe una novedad..."
                                        ><?= escapar($r["observaciones"]) ?></textarea>

                                        <br>

                                        <button
                                            type="submit"
                                            name="guardar_observacion"
                                        >
                                            Guardar observación
                                        </button>

                                    </form>

                                </td>

                                <td>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="id_registro"
                                            value="<?= (int)$r["id_registro"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="registrar_salida"
                                            class="btn-salida"
                                            onclick="return confirm('¿Confirmas la salida de este vehículo?');"
                                        >
                                            Registrar salida
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="sin-datos">
                                No hay vehículos dentro del parqueadero.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

    <p>
        <a href="vehiculos.php">Consultar vehículos registrados</a>
        |
        <a href="celdas.php">Administrar celdas</a>
    </p>

</main>

<footer>
    Autos Colombia — Sistema de gestión de parqueadero
</footer>

</body>
</html>

<?php
if ($vehiculos instanceof mysqli_result) {
    $vehiculos->free();
}

if ($celdas instanceof mysqli_result) {
    $celdas->free();
}

if ($registros instanceof mysqli_result) {
    $registros->free();
}

$conexion->close();
?>