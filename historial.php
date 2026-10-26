
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

$sql = "SELECT
            r.id_registro,
            v.placa,
            v.tipo_vehiculo,
            v.marca,
            r.fecha_entrada,
            r.fecha_salida,
            r.observaciones
        FROM registros_parqueo r
        INNER JOIN vehiculos v
            ON r.id_vehiculo = v.id_vehiculo
        ORDER BY r.fecha_entrada DESC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Historial - Autos Colombia</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }

        main {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            color: #12345a;
        }

        .contenedor {
            background: white;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #12345a;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .dentro {
            color: #166534;
            font-weight: bold;
        }

        .salida {
            color: #1d4ed8;
            font-weight: bold;
        }

        .enlaces {
            margin-top: 20px;
        }

        .enlaces a {
            display: inline-block;
            background: #12345a;
            color: white;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 5px;
            margin: 4px;
        }
    </style>
</head>

<body>
<main>

    <h1>Autos Colombia</h1>
    <h2>Historial de entradas y salidas</h2>

    <div class="contenedor">
        <table>
            <thead>
                <tr>
                    <th>N.º</th>
                    <th>Placa</th>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Fecha de entrada</th>
                    <th>Fecha de salida</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>

                    <?php while ($registro = $resultado->fetch_assoc()): ?>

                        <tr>
                            <td>
                                <?= (int) $registro["id_registro"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($registro["placa"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($registro["tipo_vehiculo"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($registro["marca"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($registro["fecha_entrada"]) ?>
                            </td>

                            <td>
                                <?= $registro["fecha_salida"] !== null
                                    ? htmlspecialchars($registro["fecha_salida"])
                                    : "Pendiente" ?>
                            </td>

                            <td>
                                <?php if ($registro["fecha_salida"] === null): ?>
                                    <span class="dentro">
                                        Dentro del parqueadero
                                    </span>
                                <?php else: ?>
                                    <span class="salida">
                                        Salida registrada
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($registro["observaciones"] ?? "") ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8">
                            Todavía no hay movimientos registrados.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="enlaces">
        <a href="parqueadero.php">Entradas y salidas</a>
        <a href="vehiculos.php">Consultar vehículos</a>
        <a href="index.php">Inicio</a>
    </div>

</main>
</body>
</html>

<?php
$conexion->close();
?>
