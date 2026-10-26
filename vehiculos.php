```php
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
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$sql = "SELECT id_vehiculo, placa, tipo_vehiculo, marca, color
        FROM vehiculos
        ORDER BY id_vehiculo DESC";

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
            max-width: 1150px;
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

        .mensaje {
            padding: 14px;
            background: #fff4e5;
            border-radius: 6px;
            margin-bottom: 16px;
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
        <a href="vehiculos.php" class="activo">Vehículos</a>
        <a href="parqueadero.php">Parqueadero</a>
        <a href="historial.php">Historial</a>
    </nav>

    <main>
        <section class="panel">
            <h2>Vehículos registrados</h2>
            <p>Consulta los vehículos almacenados en el sistema.</p>

            <?php if ($errorConsulta): ?>
                <div class="mensaje">
                    No fue posible consultar los vehículos. Verifica la
                    estructura de la base de datos.
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado->num_rows > 0): ?>
                                <?php while ($vehiculo = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$vehiculo['id_vehiculo']) ?></td>
                                        <td><?= htmlspecialchars($vehiculo['placa']) ?></td>
                                        <td><?= htmlspecialchars($vehiculo['tipo_vehiculo']) ?></td>
                                        <td><?= htmlspecialchars($vehiculo['marca'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($vehiculo['color'] ?? '') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No hay vehículos registrados.</td>
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
```