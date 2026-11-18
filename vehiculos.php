<?php
require_once __DIR__ . '/conexion.php';

if ($conexion->connect_error) {
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$mensaje = "";
$tipoMensaje = "info";

/* Función para proteger la salida HTML */
function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* Asociar un propietario a un vehículo */
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["asociar_usuario"])) {

    $id_vehiculo = (int)($_POST["id_vehiculo"] ?? 0);
    $id_usuario = ($_POST["id_usuario"] ?? "") === ""
        ? null
        : (int)$_POST["id_usuario"];

    if ($id_vehiculo <= 0) {
        $mensaje = "El vehículo seleccionado no es válido.";
        $tipoMensaje = "error";
    } else {

        /* Comprobar que el vehículo existe */
        $consultaVehiculo = $conexion->prepare(
            "SELECT id_vehiculo
             FROM vehiculos
             WHERE id_vehiculo = ?"
        );

        $consultaVehiculo->bind_param("i", $id_vehiculo);
        $consultaVehiculo->execute();
        $vehiculoExiste = $consultaVehiculo->get_result()->num_rows > 0;
        $consultaVehiculo->close();

        if (!$vehiculoExiste) {
            $mensaje = "No se encontró el vehículo seleccionado.";
            $tipoMensaje = "error";

        } else {

            /* Comprobar que el usuario existe, si fue seleccionado */
            $usuarioValido = true;

            if ($id_usuario !== null) {
                $consultaUsuario = $conexion->prepare(
                    "SELECT id_usuario
                     FROM usuarios
                     WHERE id_usuario = ?"
                );

                $consultaUsuario->bind_param("i", $id_usuario);
                $consultaUsuario->execute();

                $usuarioValido =
                    $consultaUsuario->get_result()->num_rows > 0;

                $consultaUsuario->close();
            }

            if (!$usuarioValido) {
                $mensaje = "El usuario seleccionado no existe.";
                $tipoMensaje = "error";

            } else {

                /* Actualizar la relación vehículo-propietario */
                $actualizar = $conexion->prepare(
                    "UPDATE vehiculos
                     SET id_usuario = ?
                     WHERE id_vehiculo = ?"
                );

                $actualizar->bind_param(
                    "ii",
                    $id_usuario,
                    $id_vehiculo
                );

                if ($actualizar->execute()) {
                    $mensaje = "Propietario asociado correctamente.";
                    $tipoMensaje = "exito";
                } else {
                    $mensaje = "No fue posible asociar el propietario.";
                    $tipoMensaje = "error";
                }

                $actualizar->close();
            }
        }
    }
}

/* Consultar usuarios registrados */
$usuarios = [];

$resultadoUsuarios = $conexion->query(
    "SELECT id_usuario, nombres, apellidos, documento, estado
     FROM usuarios
     ORDER BY nombres, apellidos"
);

if ($resultadoUsuarios) {
    while ($usuarioRegistrado = $resultadoUsuarios->fetch_assoc()) {
        $usuarios[] = $usuarioRegistrado;
    }

    $resultadoUsuarios->free();
}

/* Consultar vehículos y sus propietarios */
$sql = "
    SELECT
        v.id_vehiculo,
        v.placa,
        v.tipo_vehiculo,
        v.marca,
        v.color,
        v.id_usuario,
        u.nombres,
        u.apellidos,
        u.documento
    FROM vehiculos v
    LEFT JOIN usuarios u
        ON v.id_usuario = u.id_usuario
    ORDER BY v.id_vehiculo DESC
";

$resultado = $conexion->query($sql);
$errorConsulta = ($resultado === false);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vehículos | Autos Colombia</title>

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
            display: inline-block;
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

        .panel {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #e0e7ef;
            box-shadow: 0 3px 10px rgba(13, 55, 97, 0.05);
        }

        h2 {
            color: #0d3761;
            margin-top: 0;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 950px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e0e7ef;
            text-align: left;
        }

        th {
            background: #0d3761;
            color: white;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        select {
            width: 100%;
            min-width: 190px;
            padding: 9px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
        }

        button {
            margin-top: 7px;
            padding: 9px 13px;
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

        .sin-propietario {
            color: #b45309;
            font-weight: bold;
        }

        .propietario {
            color: #166534;
            font-weight: bold;
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

            .panel {
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
    <a href="vehiculos.php" class="activo">Vehículos</a>
    <a href="celdas.php">Celdas</a>
    <a href="parqueadero.php">Parqueadero</a>
    <a href="historial.php">Historial</a>
</nav>

<main>
    <section class="panel">

        <h2>Vehículos registrados</h2>

        <p>
            Consulta los vehículos almacenados en el sistema
            y asocia cada uno con su propietario.
        </p>

        <?php if ($mensaje !== ""): ?>
            <div class="mensaje <?= escapar($tipoMensaje) ?>">
                <?= escapar($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorConsulta): ?>

            <div class="mensaje error">
                No fue posible consultar los vehículos.
                Verifica la estructura de la base de datos.
            </div>

        <?php else: ?>

            <div class="tabla-contenedor">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Placa</th>
                            <th>Tipo</th>
                            <th>Marca</th>
                            <th>Color</th>
                            <th>Propietario actual</th>
                            <th>Asociar propietario</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($resultado->num_rows > 0): ?>

                        <?php while ($vehiculo = $resultado->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <?= escapar($vehiculo["id_vehiculo"]) ?>
                                </td>

                                <td>
                                    <?= escapar($vehiculo["placa"]) ?>
                                </td>

                                <td>
                                    <?= escapar($vehiculo["tipo_vehiculo"]) ?>
                                </td>

                                <td>
                                    <?= escapar($vehiculo["marca"]) ?>
                                </td>

                                <td>
                                    <?= escapar($vehiculo["color"]) ?>
                                </td>

                                <td>
                                    <?php if ($vehiculo["id_usuario"] !== null): ?>

                                        <span class="propietario">
                                            <?= escapar(
                                                $vehiculo["nombres"] . " " .
                                                $vehiculo["apellidos"]
                                            ) ?>
                                        </span>

                                        <br>

                                        Documento:
                                        <?= escapar($vehiculo["documento"]) ?>

                                    <?php else: ?>

                                        <span class="sin-propietario">
                                            Sin propietario asociado
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>

                                    <?php if (count($usuarios) > 0): ?>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="id_vehiculo"
                                                value="<?= (int)$vehiculo["id_vehiculo"] ?>"
                                            >

                                            <select
                                                name="id_usuario"
                                                required
                                            >

                                                <option value="">
                                                    Sin propietario
                                                </option>

                                                <?php foreach ($usuarios as $u): ?>

                                                    <option
                                                        value="<?= (int)$u["id_usuario"] ?>"
                                                        <?= (
                                                            (string)$vehiculo["id_usuario"]
                                                            === (string)$u["id_usuario"]
                                                        ) ? "selected" : "" ?>
                                                    >

                                                        <?= escapar(
                                                            $u["nombres"] . " " .
                                                            $u["apellidos"] .
                                                            " - " . $u["documento"] .
                                                            ($u["estado"] === "Inactivo"
                                                                ? " (Inactivo)"
                                                                : "")
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <br>

                                            <button
                                                type="submit"
                                                name="asociar_usuario"
                                            >
                                                Guardar propietario
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="sin-propietario">
                                            Primero registra un usuario.
                                        </span>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">
                                No hay vehículos registrados.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>
</main>

<footer>
    Autos Colombia — Sistema de gestión de parqueadero
</footer>

</body>
</html>

<?php
if ($resultado instanceof mysqli_result) {
    $resultado->free();
}

$conexion->close();
?>