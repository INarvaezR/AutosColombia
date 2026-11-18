<?php
require_once __DIR__ . '/conexion.php';

if ($conexion->connect_error) {
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$mensaje = "";
$tipoMensaje = "";
$celdaEditar = null;

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

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

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    // Registrar o actualizar celda
    if ($accion === "guardar_celda") {

        $id = (int)($_POST["id_celda"] ?? 0);
        $codigo = trim($_POST["codigo"] ?? "");
        $ubicacion = trim($_POST["ubicacion"] ?? "");
        $tipo = $_POST["tipo"] ?? "";
        $observaciones = trim($_POST["observaciones"] ?? "");

        if ($codigo === "") {
            $mensaje = "El código de la celda es obligatorio.";
            $tipoMensaje = "error";

        } elseif (!in_array($tipo, ["Carro", "Moto", "Mixta"], true)) {
            $mensaje = "Selecciona un tipo de celda válido.";
            $tipoMensaje = "error";

        } else {

            $puedeGuardar = true;

            // No permitir cambiar el tipo de una celda ocupada
            if ($id > 0) {
                $stmt = $conexion->prepare(
                    "SELECT tipo, estado FROM celdas WHERE id_celda = ?"
                );

                $stmt->bind_param("i", $id);
                $stmt->execute();
                $actual = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$actual) {
                    $mensaje = "La celda que intentas editar no existe.";
                    $tipoMensaje = "error";
                    $puedeGuardar = false;

                } elseif (
                    $actual["estado"] === "Ocupada" &&
                    $actual["tipo"] !== $tipo
                ) {
                    $mensaje = "No puedes cambiar el tipo de una celda ocupada.";
                    $tipoMensaje = "error";
                    $puedeGuardar = false;
                }
            }

            if ($puedeGuardar) {

                if ($id > 0) {
                    $sql = "UPDATE celdas
                            SET codigo = ?, ubicacion = ?,
                                tipo = ?, observaciones = ?
                            WHERE id_celda = ?";

                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param(
                        "ssssi",
                        $codigo,
                        $ubicacion,
                        $tipo,
                        $observaciones,
                        $id
                    );

                } else {
                    $sql = "INSERT INTO celdas
                            (codigo, ubicacion, tipo, observaciones)
                            VALUES (?, ?, ?, ?)";

                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param(
                        "ssss",
                        $codigo,
                        $ubicacion,
                        $tipo,
                        $observaciones
                    );
                }

                if ($stmt && $stmt->execute()) {
                    $stmt->close();
                    header("Location: celdas.php?guardado=1");
                    exit;

                } else {
                    if ($stmt && $stmt->errno === 1062) {
                        $mensaje = "Ya existe una celda con ese código.";
                    } else {
                        $mensaje = "No fue posible guardar la celda.";
                    }

                    $tipoMensaje = "error";

                    if ($stmt) {
                        $stmt->close();
                    }
                }
            }
        }

        $celdaEditar = [
            "id_celda" => $id,
            "codigo" => $codigo,
            "ubicacion" => $ubicacion,
            "tipo" => $tipo,
            "observaciones" => $observaciones
        ];
    }

    // Cambiar una celda entre disponible y mantenimiento
    if ($accion === "cambiar_mantenimiento") {

        $id = (int)($_POST["id_celda"] ?? 0);

        if ($id > 0) {
            $stmt = $conexion->prepare(
                "SELECT estado FROM celdas WHERE id_celda = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $celda = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($celda && $celda["estado"] === "Disponible") {

                $nuevoEstado = "Mantenimiento";

                $stmt = $conexion->prepare(
                    "UPDATE celdas SET estado = ? WHERE id_celda = ?"
                );

                $stmt->bind_param("si", $nuevoEstado, $id);
                $stmt->execute();
                $stmt->close();

            } elseif ($celda && $celda["estado"] === "Mantenimiento") {

                $nuevoEstado = "Disponible";

                $stmt = $conexion->prepare(
                    "UPDATE celdas SET estado = ? WHERE id_celda = ?"
                );

                $stmt->bind_param("si", $nuevoEstado, $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        header("Location: celdas.php");
        exit;
    }

    // Asignar vehículo a una celda
    if ($accion === "asignar") {

        $idCelda = (int)($_POST["id_celda"] ?? 0);
        $idVehiculo = (int)($_POST["id_vehiculo"] ?? 0);
        $observaciones = trim($_POST["observaciones_asignacion"] ?? "");

        if ($idCelda <= 0 || $idVehiculo <= 0) {
            $mensaje = "Selecciona una celda y un vehículo.";
            $tipoMensaje = "error";

        } else {

            $conexion->begin_transaction();

            try {
                // Bloquear y consultar la celda
                $stmt = $conexion->prepare(
                    "SELECT tipo, estado
                     FROM celdas
                     WHERE id_celda = ?
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $idCelda);
                $stmt->execute();
                $celda = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$celda) {
                    throw new Exception("La celda seleccionada no existe.");
                }

                if ($celda["estado"] !== "Disponible") {
                    throw new Exception(
                        "La celda seleccionada no está disponible."
                    );
                }

                // Consultar y bloquear el vehículo
                $stmt = $conexion->prepare(
                    "SELECT tipo_vehiculo
                     FROM vehiculos
                     WHERE id_vehiculo = ?
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $idVehiculo);
                $stmt->execute();
                $vehiculo = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$vehiculo) {
                    throw new Exception("El vehículo seleccionado no existe.");
                }

                // Validar compatibilidad
                if (!tipoCompatible(
                    $celda["tipo"],
                    $vehiculo["tipo_vehiculo"]
                )) {
                    throw new Exception(
                        "El tipo de vehículo no es compatible con esta celda."
                    );
                }

                // Verificar si el vehículo ya tiene una asignación activa
                $stmt = $conexion->prepare(
                    "SELECT id_asignacion
                     FROM asignaciones_celdas
                     WHERE id_vehiculo = ?
                     AND fecha_liberacion IS NULL
                     LIMIT 1"
                );

                $stmt->bind_param("i", $idVehiculo);
                $stmt->execute();
                $asignacionExistente = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($asignacionExistente) {
                    throw new Exception(
                        "Este vehículo ya tiene una celda asignada."
                    );
                }

                // Crear asignación
                $stmt = $conexion->prepare(
                    "INSERT INTO asignaciones_celdas
                     (id_celda, id_vehiculo, observaciones)
                     VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "iis",
                    $idCelda,
                    $idVehiculo,
                    $observaciones
                );

                if (!$stmt->execute()) {
                    throw new Exception("No fue posible registrar la asignación.");
                }

                $stmt->close();

                // Actualizar estado de la celda
                $estadoOcupada = "Ocupada";

                $stmt = $conexion->prepare(
                    "UPDATE celdas
                     SET estado = ?
                     WHERE id_celda = ? AND estado = 'Disponible'"
                );

                $stmt->bind_param("si", $estadoOcupada, $idCelda);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    throw new Exception(
                        "No se pudo actualizar el estado de la celda."
                    );
                }

                $stmt->close();

                $conexion->commit();

                header("Location: celdas.php?asignado=1");
                exit;

            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = $e->getMessage();
                $tipoMensaje = "error";
            }
        }
    }

    // Liberar celda
    if ($accion === "liberar") {

        $idAsignacion = (int)($_POST["id_asignacion"] ?? 0);

        if ($idAsignacion > 0) {

            $conexion->begin_transaction();

            try {
                $stmt = $conexion->prepare(
                    "SELECT id_celda
                     FROM asignaciones_celdas
                     WHERE id_asignacion = ?
                     AND fecha_liberacion IS NULL
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $idAsignacion);
                $stmt->execute();
                $asignacion = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$asignacion) {
                    throw new Exception(
                        "La asignación no existe o ya fue liberada."
                    );
                }

                $stmt = $conexion->prepare(
                    "UPDATE asignaciones_celdas
                     SET fecha_liberacion = NOW()
                     WHERE id_asignacion = ?
                     AND fecha_liberacion IS NULL"
                );

                $stmt->bind_param("i", $idAsignacion);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    throw new Exception("No fue posible liberar la asignación.");
                }

                $stmt->close();

                $estadoDisponible = "Disponible";

                $stmt = $conexion->prepare(
                    "UPDATE celdas
                     SET estado = ?
                     WHERE id_celda = ? AND estado = 'Ocupada'"
                );

                $stmt->bind_param(
                    "si",
                    $estadoDisponible,
                    $asignacion["id_celda"]
                );

                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    throw new Exception(
                        "No fue posible actualizar la disponibilidad de la celda."
                    );
                }

                $stmt->close();

                $conexion->commit();

                header("Location: celdas.php?liberado=1");
                exit;

            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = $e->getMessage();
                $tipoMensaje = "error";
            }
        }
    }
}

// Consultar celda para edición
if (isset($_GET["editar"])) {

    $idEditar = (int)$_GET["editar"];

    if ($idEditar > 0) {
        $stmt = $conexion->prepare(
            "SELECT * FROM celdas WHERE id_celda = ?"
        );

        $stmt->bind_param("i", $idEditar);
        $stmt->execute();

        $celdaEditar = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

// Valores iniciales del formulario
if (!$celdaEditar) {
    $celdaEditar = [
        "id_celda" => "",
        "codigo" => "",
        "ubicacion" => "",
        "tipo" => "Carro",
        "observaciones" => ""
    ];
}

// Consultar celdas
$listaCeldas = [];

$sqlCeldas = "
    SELECT
        c.id_celda,
        c.codigo,
        c.ubicacion,
        c.tipo,
        c.estado,
        c.observaciones,
        a.id_asignacion,
        a.fecha_asignacion,
        v.placa,
        v.tipo_vehiculo
    FROM celdas c
    LEFT JOIN asignaciones_celdas a
        ON c.id_celda = a.id_celda
        AND a.fecha_liberacion IS NULL
    LEFT JOIN vehiculos v
        ON a.id_vehiculo = v.id_vehiculo
    ORDER BY c.id_celda DESC
";

$resultadoCeldas = $conexion->query($sqlCeldas);

if ($resultadoCeldas) {
    while ($fila = $resultadoCeldas->fetch_assoc()) {
        $listaCeldas[] = $fila;
    }
}

// Indicadores
$totalCeldas = count($listaCeldas);
$celdasDisponibles = 0;
$celdasOcupadas = 0;
$celdasMantenimiento = 0;

foreach ($listaCeldas as $celda) {
    if ($celda["estado"] === "Disponible") {
        $celdasDisponibles++;
    } elseif ($celda["estado"] === "Ocupada") {
        $celdasOcupadas++;
    } elseif ($celda["estado"] === "Mantenimiento") {
        $celdasMantenimiento++;
    }
}

// Vehículos que todavía no tienen asignación activa
$vehiculosDisponibles = [];

$sqlVehiculos = "
    SELECT v.id_vehiculo, v.placa, v.tipo_vehiculo, v.marca
    FROM vehiculos v
    WHERE NOT EXISTS (
        SELECT 1
        FROM asignaciones_celdas a
        WHERE a.id_vehiculo = v.id_vehiculo
        AND a.fecha_liberacion IS NULL
    )
    ORDER BY v.placa ASC
";

$resultadoVehiculos = $conexion->query($sqlVehiculos);

if ($resultadoVehiculos) {
    while ($fila = $resultadoVehiculos->fetch_assoc()) {
        $vehiculosDisponibles[] = $fila;
    }
}

// Historial de asignaciones
$historial = [];

$sqlHistorial = "
    SELECT
        a.id_asignacion,
        a.fecha_asignacion,
        a.fecha_liberacion,
        a.observaciones,
        c.codigo,
        v.placa,
        v.tipo_vehiculo
    FROM asignaciones_celdas a
    INNER JOIN celdas c ON a.id_celda = c.id_celda
    INNER JOIN vehiculos v ON a.id_vehiculo = v.id_vehiculo
    ORDER BY a.id_asignacion DESC
    LIMIT 30
";

$resultadoHistorial = $conexion->query($sqlHistorial);

if ($resultadoHistorial) {
    while ($fila = $resultadoHistorial->fetch_assoc()) {
        $historial[] = $fila;
    }
}

if (isset($_GET["guardado"])) {
    $mensaje = "Celda guardada correctamente.";
    $tipoMensaje = "exito";
} elseif (isset($_GET["asignado"])) {
    $mensaje = "Vehículo asignado correctamente.";
    $tipoMensaje = "exito";
} elseif (isset($_GET["liberado"])) {
    $mensaje = "Celda liberada correctamente.";
    $tipoMensaje = "exito";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Celdas | Autos Colombia</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6fa;
            color: #243447;
        }

        header {
            background: #0d3761;
            color: white;
            padding: 25px 8%;
        }

        header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        header p {
            color: #dce8f5;
        }

        nav {
            background: white;
            padding: 16px 8%;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid #dce3eb;
        }

        nav a {
            color: #0d3761;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 14px;
            border-radius: 6px;
        }

        nav a:hover,
        nav a.activo {
            background: #0d3761;
            color: white;
        }

        main {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .encabezado {
            margin-bottom: 25px;
        }

        .encabezado h2 {
            color: #0d3761;
            margin-bottom: 8px;
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .estadistica {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e7ef;
        }

        .estadistica span {
            color: #627184;
            font-size: 14px;
        }

        .estadistica h3 {
            color: #0d3761;
            font-size: 27px;
            margin-top: 8px;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e0e7ef;
            margin-bottom: 25px;
        }

        .panel h3 {
            color: #0d3761;
            margin-bottom: 20px;
        }

        .formulario {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .campo.completo,
        .botones {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 75px;
            resize: vertical;
        }

        .botones {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .boton {
            display: inline-block;
            border: none;
            background: #1a8a52;
            color: white;
            padding: 10px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }

        .boton:hover {
            background: #146b40;
        }

        .secundario {
            background: #64748b;
        }

        .secundario:hover {
            background: #475569;
        }

        .advertencia {
            background: #b7791f;
        }

        .advertencia:hover {
            background: #925d16;
        }

        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e5eaf0;
            font-size: 14px;
        }

        th {
            background: #f3f6fa;
            color: #0d3761;
        }

        .estado {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .disponible {
            background: #dcfce7;
            color: #166534;
        }

        .ocupada {
            background: #dbeafe;
            color: #1e40af;
        }

        .mantenimiento {
            background: #fef3c7;
            color: #92400e;
        }

        .acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .acciones form {
            margin: 0;
        }

        .pequeno {
            padding: 7px 9px;
            font-size: 12px;
        }

        .mensaje {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .mensaje.exito {
            background: #dcfce7;
            color: #166534;
        }

        .mensaje.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .vacio {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        footer {
            text-align: center;
            padding: 25px;
            margin-top: 40px;
            color: #627184;
            font-size: 14px;
        }

        @media (max-width: 650px) {
            .formulario {
                grid-template-columns: 1fr;
            }

            .campo.completo,
            .botones {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Autos Colombia</h1>
    <p>Sistema de información para la gestión del parqueadero</p>
</header>

<nav>
    <a href="index.php">Inicio</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="vehiculos.php">Vehículos</a>
    <a href="celdas.php" class="activo">Celdas</a>
    <a href="parqueadero.php">Parqueadero</a>
    <a href="historial.php">Historial</a>
</nav>

<main>

    <section class="encabezado">
        <h2>Gestión de celdas</h2>
        <p>Administra las celdas, consulta su disponibilidad y controla las asignaciones de vehículos.</p>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= escapar($tipoMensaje) ?>">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="estadisticas">

        <article class="estadistica">
            <span>Total de celdas</span>
            <h3><?= $totalCeldas ?></h3>
        </article>

        <article class="estadistica">
            <span>Disponibles</span>
            <h3><?= $celdasDisponibles ?></h3>
        </article>

        <article class="estadistica">
            <span>Ocupadas</span>
            <h3><?= $celdasOcupadas ?></h3>
        </article>

        <article class="estadistica">
            <span>En mantenimiento</span>
            <h3><?= $celdasMantenimiento ?></h3>
        </article>

    </section>

    <section class="panel">

        <h3>
            <?= !empty($celdaEditar["id_celda"])
                ? "Actualizar celda"
                : "Registrar nueva celda" ?>
        </h3>

        <form method="POST" action="celdas.php" class="formulario">

            <input type="hidden" name="accion" value="guardar_celda">

            <input type="hidden" name="id_celda"
                   value="<?= escapar($celdaEditar["id_celda"]) ?>">

            <div class="campo">
                <label for="codigo">Código de la celda *</label>
                <input type="text" id="codigo" name="codigo"
                       maxlength="20" required
                       placeholder="Ej. C-001"
                       value="<?= escapar($celdaEditar["codigo"]) ?>">
            </div>

            <div class="campo">
                <label for="ubicacion">Ubicación</label>
                <input type="text" id="ubicacion" name="ubicacion"
                       maxlength="100"
                       placeholder="Ej. Primer nivel"
                       value="<?= escapar($celdaEditar["ubicacion"]) ?>">
            </div>

            <div class="campo">
                <label for="tipo">Tipo de celda *</label>
                <select id="tipo" name="tipo" required>
                    <option value="Carro"
                        <?= $celdaEditar["tipo"] === "Carro" ? "selected" : "" ?>>
                        Carro
                    </option>

                    <option value="Moto"
                        <?= $celdaEditar["tipo"] === "Moto" ? "selected" : "" ?>>
                        Moto
                    </option>

                    <option value="Mixta"
                        <?= $celdaEditar["tipo"] === "Mixta" ? "selected" : "" ?>>
                        Mixta
                    </option>
                </select>
            </div>

            <div class="campo completo">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones"
                          maxlength="255"><?= escapar($celdaEditar["observaciones"]) ?></textarea>
            </div>

            <div class="botones">
                <button type="submit" class="boton">
                    <?= !empty($celdaEditar["id_celda"])
                        ? "Guardar cambios"
                        : "Registrar celda" ?>
                </button>

                <?php if (!empty($celdaEditar["id_celda"])): ?>
                    <a href="celdas.php" class="boton secundario">
                        Cancelar edición
                    </a>
                <?php endif; ?>
            </div>

        </form>

    </section>

    <section class="panel">

        <h3>Asignar vehículo a una celda</h3>

        <?php if ($celdasDisponibles > 0 && count($vehiculosDisponibles) > 0): ?>

            <form method="POST" action="celdas.php" class="formulario">

                <input type="hidden" name="accion" value="asignar">

                <div class="campo">
                    <label for="id_celda">Celda disponible</label>
                    <select name="id_celda" id="id_celda" required>
                        <option value="">Selecciona una celda</option>

                        <?php foreach ($listaCeldas as $celda): ?>
                            <?php if ($celda["estado"] === "Disponible"): ?>
                                <option value="<?= escapar($celda["id_celda"]) ?>">
                                    <?= escapar($celda["codigo"]) ?>
                                    - <?= escapar($celda["tipo"]) ?>
                                    - <?= escapar($celda["ubicacion"]) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="campo">
                    <label for="id_vehiculo">Vehículo sin asignación activa</label>
                    <select name="id_vehiculo" id="id_vehiculo" required>
                        <option value="">Selecciona un vehículo</option>

                        <?php foreach ($vehiculosDisponibles as $vehiculo): ?>
                            <option value="<?= escapar($vehiculo["id_vehiculo"]) ?>">
                                <?= escapar($vehiculo["placa"]) ?>
                                - <?= escapar($vehiculo["tipo_vehiculo"]) ?>
                                - <?= escapar($vehiculo["marca"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="campo completo">
                    <label for="observaciones_asignacion">Observaciones de la asignación</label>
                    <textarea name="observaciones_asignacion"
                              id="observaciones_asignacion"
                              maxlength="255"></textarea>
                </div>

                <div class="botones">
                    <button type="submit" class="boton">
                        Registrar asignación
                    </button>
                </div>

            </form>

        <?php else: ?>

            <p class="vacio">
                Para realizar una asignación necesitas al menos una celda disponible
                y un vehículo sin asignación activa.
            </p>

        <?php endif; ?>

    </section>

    <section class="panel">

        <h3>Listado de celdas</h3>

        <div class="tabla-contenedor">

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Ubicación</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Vehículo asignado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($listaCeldas) > 0): ?>

                    <?php foreach ($listaCeldas as $celda): ?>

                        <tr>
                            <td><?= escapar($celda["codigo"]) ?></td>
                            <td><?= escapar($celda["ubicacion"]) ?></td>
                            <td><?= escapar($celda["tipo"]) ?></td>

                            <td>
                                <span class="estado
                                    <?= strtolower(escapar($celda["estado"])) ?>">
                                    <?= escapar($celda["estado"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= $celda["placa"]
                                    ? escapar($celda["placa"])
                                    : "—" ?>
                            </td>

                            <td>
                                <div class="acciones">

                                    <a class="boton pequeno"
                                       href="celdas.php?editar=<?= escapar($celda["id_celda"]) ?>">
                                        Editar
                                    </a>

                                    <?php if ($celda["estado"] === "Disponible"): ?>

                                        <form method="POST" action="celdas.php">
                                            <input type="hidden"
                                                   name="accion"
                                                   value="cambiar_mantenimiento">

                                            <input type="hidden"
                                                   name="id_celda"
                                                   value="<?= escapar($celda["id_celda"]) ?>">

                                            <button type="submit"
                                                    class="boton pequeno advertencia">
                                                Mantenimiento
                                            </button>
                                        </form>

                                    <?php elseif ($celda["estado"] === "Mantenimiento"): ?>

                                        <form method="POST" action="celdas.php">
                                            <input type="hidden"
                                                   name="accion"
                                                   value="cambiar_mantenimiento">

                                            <input type="hidden"
                                                   name="id_celda"
                                                   value="<?= escapar($celda["id_celda"]) ?>">

                                            <button type="submit"
                                                    class="boton pequeno">
                                                Habilitar
                                            </button>
                                        </form>

                                    <?php elseif ($celda["estado"] === "Ocupada"): ?>

                                        <form method="POST" action="celdas.php"
                                              onsubmit="return confirm('¿Confirmas la liberación de esta celda?');">

                                            <input type="hidden"
                                                   name="accion"
                                                   value="liberar">

                                            <input type="hidden"
                                                   name="id_asignacion"
                                                   value="<?= escapar($celda["id_asignacion"]) ?>">

                                            <button type="submit"
                                                    class="boton pequeno secundario">
                                                Liberar
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="vacio">
                            Todavía no hay celdas registradas.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

    <section class="panel">

        <h3>Historial de ocupación</h3>

        <div class="tabla-contenedor">

            <table>
                <thead>
                    <tr>
                        <th>Celda</th>
                        <th>Placa</th>
                        <th>Tipo de vehículo</th>
                        <th>Fecha de asignación</th>
                        <th>Fecha de liberación</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($historial) > 0): ?>

                    <?php foreach ($historial as $registro): ?>

                        <tr>
                            <td><?= escapar($registro["codigo"]) ?></td>
                            <td><?= escapar($registro["placa"]) ?></td>
                            <td><?= escapar($registro["tipo_vehiculo"]) ?></td>
                            <td><?= escapar($registro["fecha_asignacion"]) ?></td>

                            <td>
                                <?= $registro["fecha_liberacion"]
                                    ? escapar($registro["fecha_liberacion"])
                                    : "—" ?>
                            </td>

                            <td>
                                <?php if ($registro["fecha_liberacion"] === null): ?>
                                    <span class="estado ocupada">Activa</span>
                                <?php else: ?>
                                    <span class="estado disponible">Liberada</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="vacio">
                            Todavía no hay asignaciones registradas.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

</main>

<footer>
    Autos Colombia — Sistema de gestión de parqueadero
</footer>

</body>
</html>
