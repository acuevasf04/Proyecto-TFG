<?php
session_start();
require_once 'config.php';

$error = "";
$login_exitoso = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario  = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, nombre, password_hash FROM Usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION["usuario"]    = $user['nombre'];
            $_SESSION["usuario_id"] = $user['id'];
            $login_exitoso = true;
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        // Fallback si la BD no está disponible
        $error = "No se pudo conectar a la base de datos. Verifica la configuración.";
    }
}

$sesion_activa = isset($_SESSION["usuario"]);

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aromaris – Fábrica de Jabones Artesanales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-aromaris fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="bi bi-flower1 fs-4"></i>
            <span class="fw-bold fs-4">Aromaris</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="#nosotros">Nosotros</a></li>
                <li class="nav-item"><a class="nav-link" href="#productos">Productos</a></li>
                <li class="nav-item"><a class="nav-link" href="#contacto">Contacto</a></li>
                <?php if ($sesion_activa): ?>
                    <li class="nav-item">
                        <a class="nav-link text-warning fw-semibold" href="?logout=1">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm px-3 ms-2" href="#login">
                            <i class="bi bi-person-circle"></i> Acceder
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO -->
<section id="inicio" class="hero-section d-flex align-items-center text-white">
    <div class="container text-center py-5">
        <p class="text-uppercase letter-spacing mb-2 opacity-75">Bienvenido a</p>
        <h1 class="display-3 fw-bold mb-3">Aromaris</h1>
        <p class="lead mb-4">Jabones artesanales elaborados con ingredientes naturales.<br>Tradición, calidad y bienestar para tu piel.</p>
        <a href="#nosotros" class="btn btn-aromaris btn-lg px-5">Conoce nuestra historia</a>
    </div>
</section>

<!-- MENSAJE DE BIENVENIDA SI ESTÁ LOGUEADO -->
<?php if ($sesion_activa): ?>
<div class="alert alert-success alert-dismissible fade show m-0 rounded-0 text-center" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    Bienvenido, <strong><?= htmlspecialchars($_SESSION["usuario"]) ?></strong>. Has iniciado sesión correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- NOSOTROS -->
<section id="nosotros" class="py-6 bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-aromaris-light text-aromaris mb-3 px-3 py-2">Nuestra historia</span>
                <h2 class="fw-bold mb-4">Más de 20 años <br>creando pureza natural</h2>
                <p class="text-muted">Aromaris nació en 2003 con una misión clara: crear jabones artesanales de alta calidad utilizando únicamente ingredientes naturales, libres de químicos agresivos y respetuosos con el medio ambiente.</p>
                <p class="text-muted">Cada barra de jabón que fabricamos pasa por un riguroso proceso de saponificación en frío, conservando todos los nutrientes y propiedades beneficiosas de los aceites vegetales que utilizamos.</p>
                <div class="row g-3 mt-3">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-award-fill text-aromaris fs-4"></i>
                            <div>
                                <div class="fw-bold">+20 años</div>
                                <small class="text-muted">de experiencia</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill text-aromaris fs-4"></i>
                            <div>
                                <div class="fw-bold">+5.000</div>
                                <small class="text-muted">clientes satisfechos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-flower3 text-aromaris fs-4"></i>
                            <div>
                                <div class="fw-bold">100% Natural</div>
                                <small class="text-muted">sin parabenos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-recycle text-aromaris fs-4"></i>
                            <div>
                                <div class="fw-bold">Eco-friendly</div>
                                <small class="text-muted">embalaje sostenible</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="about-img-wrapper rounded-4 overflow-hidden shadow-lg">
                    <div class="about-placeholder d-flex align-items-center justify-content-center">
                        <div class="text-center text-white">
                            <i class="bi bi-droplet-half display-1 mb-3 d-block opacity-75"></i>
                            <p class="opacity-75">Proceso artesanal Aromaris</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCTOS -->
<section id="productos" class="py-6 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-aromaris-light text-aromaris mb-3 px-3 py-2">Nuestros productos</span>
            <h2 class="fw-bold">Líneas de jabones artesanales</h2>
            <p class="text-muted">Elaborados con ingredientes seleccionados de origen natural</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card product-card border-0 shadow-sm h-100 rounded-4">
                    <div class="card-body text-center p-4">
                        <div class="product-icon mb-3">
                            <i class="bi bi-flower2 display-4 text-aromaris"></i>
                        </div>
                        <h5 class="fw-bold">Línea Floral</h5>
                        <p class="text-muted small">Rosa, lavanda y jazmín. Perfectos para pieles sensibles. Enriquecidos con aceite de argán y vitamina E.</p>
                        <span class="badge bg-aromaris-light text-aromaris">Pieles sensibles</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card product-card border-0 shadow-sm h-100 rounded-4">
                    <div class="card-body text-center p-4">
                        <div class="product-icon mb-3">
                            <i class="bi bi-tree display-4 text-aromaris"></i>
                        </div>
                        <h5 class="fw-bold">Línea Herbal</h5>
                        <p class="text-muted small">Árbol de té, romero y menta. Propiedades antisépticas y refrescantes. Ideales para uso diario.</p>
                        <span class="badge bg-aromaris-light text-aromaris">Uso diario</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card product-card border-0 shadow-sm h-100 rounded-4">
                    <div class="card-body text-center p-4">
                        <div class="product-icon mb-3">
                            <i class="bi bi-heart-pulse display-4 text-aromaris"></i>
                        </div>
                        <h5 class="fw-bold">Línea Terapéutica</h5>
                        <p class="text-muted small">Caléndula, avena y aloe vera. Formulados para pieles secas o con necesidades especiales de hidratación.</p>
                        <span class="badge bg-aromaris-light text-aromaris">Hidratación profunda</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- VALORES -->
<section class="py-6 bg-aromaris-dark text-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">¿Por qué elegir Aromaris?</h2>
            <p class="opacity-75">Nuestro compromiso con la calidad y la naturaleza</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <i class="bi bi-shield-check display-5 mb-3 d-block text-aromaris-accent"></i>
                <h6 class="fw-bold">Sin químicos</h6>
                <p class="small opacity-75">Sin sulfatos, parabenos ni colorantes artificiales.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-patch-check display-5 mb-3 d-block text-aromaris-accent"></i>
                <h6 class="fw-bold">Certificado</h6>
                <p class="small opacity-75">Certificación de cosmética natural y ecológica.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-box-seam display-5 mb-3 d-block text-aromaris-accent"></i>
                <h6 class="fw-bold">Envío nacional</h6>
                <p class="small opacity-75">Entrega a todo el territorio en 24–48 horas.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-hand-thumbs-up display-5 mb-3 d-block text-aromaris-accent"></i>
                <h6 class="fw-bold">Satisfacción</h6>
                <p class="small opacity-75">Garantía de devolución si no quedas satisfecho.</p>
            </div>
        </div>
    </div>
</section>

<!-- CONTACTO + LOGIN -->
<section id="contacto" class="py-6 bg-white">
    <div class="container">
        <div class="row g-5 align-items-start">

            <!-- INFO DE CONTACTO -->
            <div class="col-lg-6">
                <span class="badge bg-aromaris-light text-aromaris mb-3 px-3 py-2">Contáctanos</span>
                <h2 class="fw-bold mb-4">Estamos para ayudarte</h2>
                <div class="d-flex flex-column gap-4">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-geo-alt-fill text-aromaris fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Dirección</div>
                            <p class="text-muted mb-0">Polígono Industrial El Pinar, Nave 12<br>28000, Madrid, España</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-telephone-fill text-aromaris fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Teléfono</div>
                            <p class="text-muted mb-0">+34 91 234 56 78</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-envelope-fill text-aromaris fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Email</div>
                            <p class="text-muted mb-0">info@aromaris.es</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-clock-fill text-aromaris fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Horario</div>
                            <p class="text-muted mb-0">Lunes a Viernes: 8:00 – 18:00<br>Sábados: 9:00 – 14:00</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE LOGIN -->
            <div class="col-lg-6" id="login">
                <div class="card border-0 shadow rounded-4 p-4">
                    <div class="text-center mb-4">
                        <div class="login-avatar mx-auto mb-3">
                            <i class="bi bi-person-circle display-4 text-aromaris"></i>
                        </div>
                        <h4 class="fw-bold">Acceso de empleados</h4>
                        <p class="text-muted small">Introduce tus credenciales para acceder al panel interno</p>
                    </div>

                    <?php if ($login_exitoso): ?>
                        <div class="alert alert-success text-center">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Sesión iniciada correctamente. ¡Bienvenido!
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$sesion_activa): ?>
                    <form method="POST" action="#login" novalidate>
                        <div class="mb-3">
                            <label for="usuario" class="form-label fw-semibold">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-start-0 ps-0 <?= $error ? 'is-invalid' : '' ?>"
                                    id="usuario"
                                    name="usuario"
                                    placeholder="Tu nombre de usuario"
                                    value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                                    required
                                    autocomplete="username"
                                >
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input
                                    type="password"
                                    class="form-control border-start-0 ps-0 <?= $error ? 'is-invalid' : '' ?>"
                                    id="password"
                                    name="password"
                                    placeholder="Tu contraseña"
                                    required
                                    autocomplete="current-password"
                                >
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePass" title="Mostrar contraseña">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-aromaris btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="text-center">
                        <p class="text-muted">Sesión activa como <strong><?= htmlspecialchars($_SESSION["usuario"]) ?></strong></p>
                        <a href="?logout=1" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-aromaris-dark text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <span class="fw-bold fs-5">
                    <i class="bi bi-flower1 me-2"></i>Aromaris
                </span>
                <p class="small opacity-75 mb-0 mt-1">Fábrica de Jabones Artesanales · Desde 2003</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="small opacity-75 mb-0">&copy; <?= date('Y') ?> Aromaris. Todos los derechos reservados.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle mostrar/ocultar contraseña
    document.getElementById("togglePass")?.addEventListener("click", function () {
        const input = document.getElementById("password");
        const icon = document.getElementById("eyeIcon");
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("bi-eye", "bi-eye-slash");
        } else {
            input.type = "password";
            icon.classList.replace("bi-eye-slash", "bi-eye");
        }
    });

    // Scroll suave al hacer clic en "Acceder"
    document.querySelectorAll('a[href="#login"]').forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();
            document.getElementById("login").scrollIntoView({ behavior: "smooth" });
        });
    });
</script>
</body>
</html>
