
<?php
$servidor = "127.0.0.1";
$usuario = "root";
$contrasena = "Martik1885";
$base_datos = "autos_colombia";
$puerto = 3306;

mysqli_report(MYSQLI_REPORT_OFF);

$conexion = new mysqli(
    $servidor,
    $usuario,
    $contrasena,
    $base_datos,
    $puerto
);

if ($conexion->connect_error) {
    die("No fue posible conectar con la base de datos.");
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["registrar_entrada"])) {
        $id_vehiculo = (int) $_POST["id_vehiculo"];

        $consulta = $conexion->prepare(
            "INSERT INTO registros_parqueo (id_vehiculo)
             SELECT id_vehiculo FROM vehiculos
             WHERE id_vehiculo = ?
             AND NOT EXISTS (
                 SELECT 1 FROM registros_parqueo
                 WHERE id_vehiculo = ?
                 AND fecha_salida IS NULL
             )"
        );

        $consulta->bind_param("ii", $id_vehiculo, $id_vehiculo);
        $consulta->execute();

        if ($consulta->affected_rows > 0) {
            $mensaje = "Entrada registrada correctamente.";
        } else {
            $mensaje = "No se registró la entrada. Verifica el vehículo o si ya está dentro.";
        }

        $consulta->close();
    }

    if (isset($_POST["registrar_salida"])) {
        $id_registro = (int) $_POST["id_registro"];

        $consulta = $conexion->prepare(
            "UPDATE registros_parqueo
             SET fecha_salida = NOW()
             WHERE id_registro = ?
             AND fecha_salida IS NULL"
        );

        $consulta->bind_param("i", $id_registro);
        $consulta->execute();

        $mensaje = $consulta->affected_rows > 0
            ? "Salida registrada correctamente."
            : "No se pudo registrar la salida.";

        $consulta->close();
    }
}

if (isset($_POST["guardar_observacion"])) {
    $id_registro = (int) $_POST["id_registro"];
    $observaciones = trim($_POST["observaciones"] ?? "");

    $consulta = $conexion->prepare(
        "UPDATE registros_parqueo
         SET observaciones = ?
         WHERE id_registro = ?
         AND fecha_salida IS NULL"
    );

    $consulta->bind_param(
        "si",
        $observaciones,
        $id_registro
    );

    $consulta->execute();

    $mensaje = $consulta->affected_rows >= 0
        ? "Observación procesada."
        : "No fue posible guardar la observación.";

    $consulta->close();
}

$vehiculos = $conexion->query(
    "SELECT id_vehiculo, placa, tipo_vehiculo
     FROM vehiculos ORDER BY placa"
);

$registros = $conexion->query(
    "SELECT r.id_registro, v.placa, v.tipo_vehiculo,
            r.fecha_entrada, r.observaciones
     FROM registros_parqueo r
     INNER JOIN vehiculos v
        ON r.id_vehiculo = v.id_vehiculo
     WHERE r.fecha_salida IS NULL
     ORDER BY r.fecha_entrada DESC"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entradas y salidas - Autos Colombia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }

        main {
            max-width: 1000px;
            margin: auto;
        }

        section {
            background: white;
            padding: 20px;
            margin: 18px 0;
            border-radius: 10px;
        }

        input, select, button {
            padding: 10px;
            margin: 6px 0;
        }

        button {
            background: #12345a;
            color: white;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #12345a;
            color: white;
        }

        .mensaje {
            background: #e0f2fe;
            padding: 12px;
            border-radius: 6px;
        }

        a {
            color: #12345a;
        }
    </style>
</head>
<body>
<main>
    <h1>Autos Colombia</h1>
    <h2>Control de entradas y salidas</h2>

    <?php if ($mensaje !== ""): ?>
        <p class="mensaje">
            <?= htmlspecialchars($mensaje) ?>
        </p>
    <?php endif; ?>

    <section>
        <h3>Registrar entrada</h3>

        <form method="POST">
            <label for="id_vehiculo">Selecciona el vehículo:</label>
            <br>
            <select id="id_vehiculo" name="id_vehiculo" required>
                <option value="">Seleccionar placa</option>

                <?php while ($v = $vehiculos->fetch_assoc()): ?>
                    <option value="<?= (int) $v["id_vehiculo"] ?>">
                        <?= htmlspecialchars($v["placa"]) ?>
                        - <?= htmlspecialchars($v["tipo_vehiculo"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <br>
            <button type="submit" name="registrar_entrada">
                Registrar entrada
            </button>
        </form>
    </section>

    <section>
        <h3>Vehículos actualmente en el parqueadero</h3>

        <table>
            <thead>
                <tr>
                    <th>Placa</th>
                    <th>Tipo</th>
                    <th>Fecha de entrada</th>
                    <th>Observaciones</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($registros && $registros->num_rows > 0): ?>
                    <?php while ($r = $registros->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r["placa"]) ?></td>
                            <td><?= htmlspecialchars($r["tipo_vehiculo"]) ?></td>
                            <td><?= htmlspecialchars($r["fecha_entrada"]) ?></td>
                            <td>

<td>
    <form method="POST">
        <input
            type="hidden"
            name="id_registro"
            value="<?= (int) $r["id_registro"] ?>"
        >

        <textarea
            name="observaciones"
            rows="3"
            maxlength="255"
            placeholder="Escribe una novedad..."
        ><?= htmlspecialchars($r["observaciones"] ?? "") ?></textarea>

        <br>

        <button type="submit" name="guardar_observacion">
            Guardar observación
        </button>
    </form>
</td>

    <form method="POST">
        <input
            type="hidden"
            name="id_registro"
            value="<?= (int) $r["id_registro"] ?>"
        >

        <button type="submit" name="registrar_salida">
            Registrar salida
        </button>
    </form>
</td>

                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            No hay vehículos dentro del parqueadero.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <p><a href="vehiculos.php">Consultar vehículos registrados</a></p>
</main>
</body>
</html>

<?php
$conexion->close();
?>
