```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autos Colombia | Inicio</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .bienvenida {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e0e7ef;
            margin-bottom: 25px;
        }

        .bienvenida h2 {
            color: #0d3761;
            margin-bottom: 12px;
        }

        .bienvenida p {
            line-height: 1.7;
        }

        .modulos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .tarjeta {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e0e7ef;
            box-shadow: 0 3px 10px rgba(13, 55, 97, 0.05);
        }

        .tarjeta h3 {
            color: #0d3761;
            margin-bottom: 12px;
        }

        .tarjeta p {
            line-height: 1.6;
            margin-bottom: 20px;
            color: #526273;
        }

        .boton {
            display: inline-block;
            background: #1a8a52;
            color: white;
            padding: 11px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
        }

        .boton:hover {
            background: #146b40;
        }

        footer {
            text-align: center;
            padding: 25px;
            margin-top: 40px;
            color: #627184;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <header>
        <h1>Autos Colombia</h1>
        <p>Sistema de información para la gestión del parqueadero</p>
    </header>

    <nav>
        <a href="index.php" class="activo">Inicio</a>
        <a href="vehiculos.php">Vehículos</a>
        <a href="parqueadero.php">Parqueadero</a>
        <a href="historial.php">Historial</a>
    </nav>

    <main>
        <section class="bienvenida">
            <h2>Bienvenido a Autos Colombia</h2>

            <p>
                Administra los vehículos registrados y controla sus
                movimientos de entrada y salida del parqueadero desde
                un solo lugar.
            </p>
        </section>

        <section class="modulos">

            <article class="tarjeta">
                <h3>Vehículos</h3>
                <p>
                    Consulta los vehículos registrados y revisa sus
                    datos de identificación.
                </p>
                <a href="vehiculos.php" class="boton">
                    Consultar vehículos
                </a>
            </article>

            <article class="tarjeta">
                <h3>Control del parqueadero</h3>
                <p>
                    Gestiona las entradas, las salidas y las observaciones
                    de los vehículos.
                </p>
                <a href="parqueadero.php" class="boton">
                    Abrir parqueadero
                </a>
            </article>

            <article class="tarjeta">
                <h3>Historial de movimientos</h3>
                <p>
                    Consulta los registros de entrada y salida almacenados
                    en el sistema.
                </p>
                <a href="historial.php" class="boton">
                    Consultar historial
                </a>
            </article>

        </section>
    </main>

    <footer>
        Autos Colombia — Sistema de gestión de parqueadero
    </footer>

</body>
</html>
```