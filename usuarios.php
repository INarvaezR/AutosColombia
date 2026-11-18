<?php
require_once __DIR__ . '/conexion.php';

if ($conexion->connect_error) {
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$mensaje = "";
$tipoMensaje = "";
$usuarioEditar = null;

// Registrar o actualizar usuario
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    if ($accion === "guardar") {

        $id = (int)($_POST["id_usuario"] ?? 0);
        $nombres = trim($_POST["nombres"] ?? "");
        $apellidos = trim($_POST["apellidos"] ?? "");
        $documento = trim($_POST["documento"] ?? "");
        $telefono = trim($_POST["telefono"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $direccion = trim($_POST["direccion"] ?? "");
        $estado = $_POST["estado"] ?? "Activo";

        if (
            $nombres === "" ||
            $apellidos === "" ||
            $documento === ""
        ) {
            $mensaje = "Completa los campos obligatorios.";
            $tipoMensaje = "error";

        } elseif (!in_array($estado, ["Activo", "Inactivo"], true)) {
            $mensaje = "El estado seleccionado no es válido.";
            $tipoMensaje = "error";

        } elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "Ingresa un correo electrónico válido.";
            $tipoMensaje = "error";

        } else {

            if ($id > 0) {
                $sql = "UPDATE usuarios
                        SET nombres = ?, apellidos = ?, documento = ?,
                            telefono = ?, correo = ?, direccion = ?, estado = ?
                        WHERE id_usuario = ?";

                $stmt = $conexion->prepare($sql);
                $stmt->bind_param(
                    "sssssssi",
                    $nombres,
                    $apellidos,
                    $documento,
                    $telefono,
                    $correo,
                    $direccion,
                    $estado,
                    $id
                );

            } else {
                $sql = "INSERT INTO usuarios
                        (nombres, apellidos, documento, telefono,
                         correo, direccion, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);
                $stmt->bind_param(
                    "sssssss",
                    $nombres,
                    $apellidos,
                    $documento,
                    $telefono,
                    $correo,
                    $direccion,
                    $estado
                );
            }

            if ($stmt && $stmt->execute()) {
                header("Location: usuarios.php?guardado=1");
                exit;
            } else {
                if ($stmt && $stmt->errno === 1062) {
                    $mensaje = "Ya existe un usuario con ese documento.";
                } else {
                    $mensaje = "No fue posible guardar el usuario. Verifica los datos.";
                }

                $tipoMensaje = "error";
            }

            if ($stmt) {
                $stmt->close();
            }
        }

        $usuarioEditar = [
            "id_usuario" => $id,
            "nombres" => $nombres,
            "apellidos" => $apellidos,
            "documento" => $documento,
            "telefono" => $telefono,
            "correo" => $correo,
            "direccion" => $direccion,
            "estado" => $estado
        ];
    }

    // Cambiar estado del usuario
    if ($accion === "cambiar_estado") {
        $id = (int)($_POST["id_usuario"] ?? 0);

        if ($id > 0) {
            $stmt = $conexion->prepare(
                "UPDATE usuarios
                 SET estado = IF(estado = 'Activo', 'Inactivo', 'Activo')
                 WHERE id_usuario = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: usuarios.php");
        exit;
    }
}

// Consultar usuario para edición
if (isset($_GET["editar"])) {
    $idEditar = (int)$_GET["editar"];

    if ($idEditar > 0) {
        $stmt = $conexion->prepare(
            "SELECT * FROM usuarios WHERE id_usuario = ?"
        );

        $stmt->bind_param("i", $idEditar);
        $stmt->execute();

        $resultadoEditar = $stmt->get_result();
        $usuarioEditar = $resultadoEditar->fetch_assoc();

        $stmt->close();
    }
}

// Valores iniciales del formulario
if (!$usuarioEditar) {
    $usuarioEditar = [
        "id_usuario" => "",
        "nombres" => "",
        "apellidos" => "",
        "documento" => "",
        "telefono" => "",
        "correo" => "",
        "direccion" => "",
        "estado" => "Activo"
    ];
}

// Consultar usuarios
$sql = "SELECT id_usuario, nombres, apellidos, documento,
               telefono, correo, direccion, estado, fecha_registro
        FROM usuarios
        ORDER BY id_usuario DESC";

$resultado = $conexion->query($sql);

if (!$resultado) {
    $mensaje = "No fue posible consultar los usuarios.";
    $tipoMensaje = "error";
}

$totalUsuarios = 0;
$usuariosActivos = 0;
$usuariosInactivos = 0;

if ($resultado) {
    $totalUsuarios = $resultado->num_rows;

    while ($fila = $resultado->fetch_assoc()) {
        if ($fila["estado"] === "Activo") {
            $usuariosActivos++;
        } else {
            $usuariosInactivos++;
        }

        $listaUsuarios[] = $fila;
    }
}

$listaUsuarios = $listaUsuarios ?? [];

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

if (isset($_GET["guardado"])) {
    $mensaje = "Usuario guardado correctamente.";
    $tipoMensaje = "exito";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | Autos Colombia</title>

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
            max-width: 1200px;
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
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
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

        .campo.completo {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: 2px solid #b7d3ee;
            border-color: #0d3761;
        }

        .botones {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
        }

        .boton {
            display: inline-block;
            border: none;
            background: #1a8a52;
            color: white;
            padding: 11px 17px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        .boton:hover {
            background: #146b40;
        }

        .boton-secundario {
            background: #64748b;
        }

        .boton-secundario:hover {
            background: #475569;
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

        .activo {
            background: #dcfce7;
            color: #166534;
        }

        .inactivo {
            background: #fee2e2;
            color: #991b1b;
        }

        .acciones {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .acciones form {
            margin: 0;
        }

        .boton-pequeno {
            padding: 7px 10px;
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
    <a href="usuarios.php" class="activo">Usuarios</a>
    <a href="vehiculos.php">Vehículos</a>
    <a href="celdas.php">Celdas</a>
    <a href="parqueadero.php">Parqueadero</a>
    <a href="historial.php">Historial</a>
</nav>

<main>

    <section class="encabezado">
        <h2>Gestión de usuarios</h2>
        <p>Administra la información de los usuarios vinculados al parqueadero.</p>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= escapar($tipoMensaje) ?>">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="estadisticas">

        <article class="estadistica">
            <span>Total de usuarios</span>
            <h3><?= $totalUsuarios ?></h3>
        </article>

        <article class="estadistica">
            <span>Usuarios activos</span>
            <h3><?= $usuariosActivos ?></h3>
        </article>

        <article class="estadistica">
            <span>Usuarios inactivos</span>
            <h3><?= $usuariosInactivos ?></h3>
        </article>

    </section>

    <section class="panel">

        <h3>
            <?= !empty($usuarioEditar["id_usuario"])
                ? "Actualizar usuario"
                : "Registrar nuevo usuario" ?>
        </h3>

        <form method="POST" action="usuarios.php" class="formulario">

            <input type="hidden" name="accion" value="guardar">

            <input type="hidden" name="id_usuario"
                   value="<?= escapar($usuarioEditar["id_usuario"]) ?>">

            <div class="campo">
                <label for="nombres">Nombres *</label>
                <input type="text" id="nombres" name="nombres"
                       maxlength="80" required
                       value="<?= escapar($usuarioEditar["nombres"]) ?>">
            </div>

            <div class="campo">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos"
                       maxlength="80" required
                       value="<?= escapar($usuarioEditar["apellidos"]) ?>">
            </div>

            <div class="campo">
                <label for="documento">Documento *</label>
                <input type="text" id="documento" name="documento"
                       maxlength="20" required
                       value="<?= escapar($usuarioEditar["documento"]) ?>">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono"
                       maxlength="20"
                       value="<?= escapar($usuarioEditar["telefono"]) ?>">
            </div>

            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo"
                       maxlength="120"
                       value="<?= escapar($usuarioEditar["correo"]) ?>">
            </div>

            <div class="campo">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion"
                       maxlength="180"
                       value="<?= escapar($usuarioEditar["direccion"]) ?>">
            </div>

            <div class="campo">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="Activo"
                        <?= $usuarioEditar["estado"] === "Activo" ? "selected" : "" ?>>
                        Activo
                    </option>
                    <option value="Inactivo"
                        <?= $usuarioEditar["estado"] === "Inactivo" ? "selected" : "" ?>>
                        Inactivo
                    </option>
                </select>
            </div>

            <div class="botones">
                <button type="submit" class="boton">
                    <?= !empty($usuarioEditar["id_usuario"])
                        ? "Guardar cambios"
                        : "Registrar usuario" ?>
                </button>

                <?php if (!empty($usuarioEditar["id_usuario"])): ?>
                    <a href="usuarios.php" class="boton boton-secundario">
                        Cancelar edición
                    </a>
                <?php endif; ?>
            </div>

        </form>

    </section>

    <section class="panel">

        <h3>Usuarios registrados</h3>

        <div class="tabla-contenedor">

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre completo</th>
                        <th>Documento</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($listaUsuarios) > 0): ?>

                    <?php foreach ($listaUsuarios as $fila): ?>
                        <tr>
                            <td><?= escapar($fila["id_usuario"]) ?></td>

                            <td>
                                <?= escapar($fila["nombres"] . " " . $fila["apellidos"]) ?>
                            </td>

                            <td><?= escapar($fila["documento"]) ?></td>
                            <td><?= escapar($fila["telefono"]) ?></td>
                            <td><?= escapar($fila["correo"]) ?></td>

                            <td>
                                <span class="estado <?= $fila["estado"] === "Activo" ? "activo" : "inactivo" ?>">
                                    <?= escapar($fila["estado"]) ?>
                                </span>
                            </td>

                            <td>
                                <div class="acciones">

                                    <a class="boton boton-pequeno"
                                       href="usuarios.php?editar=<?= escapar($fila["id_usuario"]) ?>">
                                        Editar
                                    </a>

                                    <form method="POST" action="usuarios.php">
                                        <input type="hidden" name="accion"
                                               value="cambiar_estado">

                                        <input type="hidden" name="id_usuario"
                                               value="<?= escapar($fila["id_usuario"]) ?>">

                                        <button type="submit"
                                                class="boton boton-pequeno boton-secundario">
                                            <?= $fila["estado"] === "Activo"
                                                ? "Inactivar"
                                                : "Activar" ?>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="vacio">
                            Todavía no hay usuarios registrados.
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
