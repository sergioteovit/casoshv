<?php
session_start();

// 1. SEGURIDAD: Si no está logueado o es un rol 'Invitado', bloquear acceso
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] === 'Invitado') {
    // Los invitados no pueden crear casos, los regresamos al listado
    header("Location: lista_casos.php");
    exit;
}

$rolUsuario = $_SESSION['rol']; // Guardamos el rol ('Editor' o 'Administrador')
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caso Clínico</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }
        .form-container {
            max-width: 1000px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .header-title {
            color: #2c3e50;
            font-weight: 700;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .section-title {
            background-color: #eef2f5;
            padding: 10px 15px;
            border-left: 4px solid #3498db;
            border-radius: 4px;
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 40px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .btn-remove {
            cursor: pointer;
        }
        .animate-fade {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-control:focus, .form-check-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .art-item-container {
            transition: all 0.2s ease;
        }
        .art-item-container:hover {
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
        }
    </style>
</head>
<body class="py-4 py-md-5">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
      <div class="container">
        <a class="navbar-brand" href="lista_casos.php">
            <i class="bi bi-heart-pulse-fill text-danger me-2"></i>Casos Clínicos
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav me-auto">
            <li class="nav-item">
              <a class="nav-link" href="lista_casos.php">Lista de Casos</a>
            </li>
            <?php if ($rolUsuario === 'Administrador'): ?>
            <li class="nav-item">
              <a class="nav-link" href="gestion_usuarios.php">Gestión de Usuarios</a>
            </li>
            <?php endif; ?>
          </ul>

          <div class="d-flex align-items-center text-white">
              <span class="me-3 small">
                  <i class="bi bi-person-circle text-primary me-1"></i> 
                  <?= htmlspecialchars($_SESSION['nombre_completo']) ?> 
                  <span class="badge bg-secondary ms-1"><?= $rolUsuario ?></span>
              </span>
              <a href="perfil.php" class="btn btn-sm btn-outline-light me-2">Mi Perfil</a>
              <a href="logout.php" class="btn btn-sm btn-danger">Salir</a>
          </div>
        </div>
      </div>
    </nav>
    <div class="container my-4">
    
<div class="container main-container bg-white p-4 p-md-5 rounded-4 shadow-sm border">
    
    <div class="text-center mb-5">
        <h1 class="fw-bold text-primary mb-2">NUEVO CASO CLÍNICO</h1>
        <p class="text-muted">Formulario para la estandarización y recolección de casos médicos</p>
    </div>
        
    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>No se pudo guardar el caso</h5>
            <p class="mb-0 small">Detalle del error del sistema: <code><?= htmlspecialchars($_GET['error']) ?></code></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="formulario-caso">

        <!-- INFORMACIÓN GENERAL -->
        <h2 class="section-title">INFORMACIÓN GENERAL</h2>
        <div class="mb-3">
            <label class="form-label fw-bold">Identificador del Caso</label>
            <input type="text" 
                   name="identificador" 
                   id="identificador" 
                   class="form-control" 
                   placeholder="<?= $rolUsuario === 'Administrador' ? 'Ej. MED-2026-001' : 'Asignado automáticamente por el sistema' ?>" 
                   <?= $rolUsuario === 'Administrador' ? 'required' : 'disabled' ?>>

            <?php if ($rolUsuario !== 'Administrador'): ?>
                <div class="form-text text-muted small">
                    <i class="bi bi-lock-fill text-warning me-1"></i> 
                    Tu rol de <strong><?= $rolUsuario ?></strong> no tiene permisos para asignar o modificar el identificador manualmente.
                </div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Título del Caso Clínico:</label>
            <input type="text" class="form-control" name="titulo_caso" placeholder="Ej. Paciente masculino de 45 años con dolor torácico" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">DEPARTAMENTO(S) QUE ELABORA(N) EL CASO:</label>
            <div class="row g-2">
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Departamento de Anatomía" id="dep1"><label class="form-check-label" for="dep1">Departamento de Anatomía</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Biología Celular y Tisular" id="dep2"><label class="form-check-label" for="dep2">Biología Celular y Tisular</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Bioquímica" id="dep3"><label class="form-check-label" for="dep3">Bioquímica</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Inmunología" id="dep19"><label class="form-check-label" for="dep19">Inmunología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Embriología y Genética" id="dep4"><label class="form-check-label" for="dep4">Embriología y Genética</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Farmacología" id="dep5"><label class="form-check-label" for="dep5">Farmacología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Fisiología" id="dep6"><label class="form-check-label" for="dep6">Fisiología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Microbiología y Parasitología" id="dep7"><label class="form-check-label" for="dep7">Microbiología y Parasitología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Salud Pública" id="dep8"><label class="form-check-label" for="dep8">Salud Pública</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Departamento de Cirugía" id="dep9"><label class="form-check-label" for="dep9">Departamento de Cirugía</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Historia y Filosofía de la Medicina" id="dep10"><label class="form-check-label" for="dep10">Historia y Filosofía de la Medicina</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Innovación en Material Biológico Humano" id="dep11"><label class="form-check-label" for="dep11">Innovación en Material Biológico Humano</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Integración de Ciencias Médicas" id="dep12"><label class="form-check-label" for="dep12">Integración de Ciencias Médicas</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Psiquiatría y Salud Mental" id="dep13"><label class="form-check-label" for="dep13">Psiquiatría y Salud Mental</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Salud Digital" id="dep14"><label class="form-check-label" for="dep14">Salud Digital</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Áreas de Enseñanza y Servicio Social" id="dep15"><label class="form-check-label" for="dep15">Áreas de Enseñanza y Servicio Social</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Coordinación de Ciencias Básicas" id="dep16"><label class="form-check-label" for="dep16">Coordinación de Ciencias Básicas</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="SECISS" id="dep17"><label class="form-check-label" for="dep17">SECISS</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="SEM" id="dep18"><label class="form-check-label" for="dep18">SEM</label></div></div>
                
                <div class="col-12 mt-2 d-flex align-items-center gap-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="checkbox-otro" name="departamentos" value="Otro">
                        <label class="form-check-label fw-bold" for="checkbox-otro">Otro</label>
                    </div>
                    <input type="text" class="form-control form-control-sm w-50 d-none" id="departamento-otro-texto" name="departamento_otro_texto" placeholder="Especificar departamento...">
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">AUTOR(ES):</label>
            <div id="autores-container">
                <div class="input-group mb-2 autor-input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" name="autores" placeholder="Nombre completo del autor">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar autor"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-autor" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otro autor
            </button>
        </div>

        <!-- CLASIFICACIÓN DEL CASO -->
        <h2 class="section-title">CLASIFICACIÓN DEL CASO</h2>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label fw-bold">SEXO:</label>
                <div class="d-flex gap-3 mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexo" id="sexo_m" value="Masculino">
                        <label class="form-check-label" for="sexo_m">Masculino</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexo" id="sexo_f" value="Femenino">
                        <label class="form-check-label" for="sexo_f">Femenino</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label fw-bold" for="edad">EDAD:</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="edad" name="edad" placeholder="Ej. 35" min="0" max="200">
                    <span class="input-group-text">años</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="padecimiento">PADECIMIENTO:</label>
                <select class="form-select" id="padecimiento" name="padecimiento">
                    <option value="" selected disabled>-- Selecciona una opción --</option>
                    <option value="Infecciones respiratorias agudas">Infecciones respiratorias agudas</option>
                    <option value="Úlceras, gastritis y duodenitis">Úlceras, gastritis y duodenitis</option>
                    <option value="Accidentes de transporte en vehículos con motor">Accidentes de transporte en vehículos con motor</option>
                    <option value="Neumonías y bronconeumonías">Neumonías y bronconeumonías</option>
                    <option value="Infecciones intestinales por otros organismos y las mal definidas">Infecciones intestinales por otros organismos y las mal definidas</option>
                    <option value="Otitis media aguda">Otitis media aguda</option>
                    <option value="Hiperplasia de la próstata">Hiperplasia de la próstata</option>
                    <option value="Mordeduras por perro">Mordeduras por perro</option>
                    <option value="Infección de vías urinarias">Infección de vías urinarias</option>
                    <option value="Hipertensión arterial">Hipertensión arterial</option>
                    <option value="Dengue no grave">Dengue no grave</option>
                    <option value="Asma">Asma</option>
                    <option value="Gingivitis y enfermedad periodontal">Gingivitis y enfermedad periodontal</option>
                    <option value="Obesidad">Obesidad</option>
                    <option value="Faringitis y amigdalitis estreptocócicas">Faringitis y amigdalitis estreptocócicas</option>
                    <option value="Insuficiencia venosa periférica">Insuficiencia venosa periférica</option>
                    <option value="Conjuntivitis">Conjuntivitis</option>
                    <option value="Diabetes Tipo 2">Diabetes Tipo 2</option>
                    <option value="Intoxicación por picadura de alacrán">Intoxicación por picadura de alacrán</option>
                    <option value="Infección asociada a la atención de la salud">Infección asociada a la atención de la salud</option>
                    <option value="Vulvovaginitis">Vulvovaginitis</option>
                    <option value="Candidiasis urogenital">Candidiasis urogenital</option>
                    <option value="Depresión">Depresión</option>
                    <option value="Otro">Otro (Especificar)</option>
                </select>
                <input type="text" class="form-control mt-2 d-none" id="padecimiento-otro-texto" name="padecimiento_otro_texto" placeholder="Especificar padecimiento...">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Residencia:</label>
            <input type="text" class="form-control" name="residencia" placeholder="Ej. Ciudad de México">
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold">GRUPO DE EDAD (AÑOS):</label>
            <div class="row g-2">
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="<1" id="e1"><label class="form-check-label" for="e1">&lt;1</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="1 a 4" id="e2"><label class="form-check-label" for="e2">1 a 4</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="5 a 9" id="e3"><label class="form-check-label" for="e3">5 a 9</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="10 a 14" id="e4"><label class="form-check-label" for="e4">10 a 14</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="15 a 19" id="e5"><label class="form-check-label" for="e5">15 a 19</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="20 a 24" id="e6"><label class="form-check-label" for="e6">20 a 24</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="25 a 44" id="e7"><label class="form-check-label" for="e7">25 a 44</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="45 a 49" id="e8"><label class="form-check-label" for="e8">45 a 49</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="50 a 59" id="e9"><label class="form-check-label" for="e9">50 a 59</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="60 a 64" id="e10"><label class="form-check-label" for="e10">60 a 64</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value=">65" id="e11"><label class="form-check-label" for="e11">&gt;65</label></div></div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">SISTEMA(S) ANATÓMICO(S) PRINCIPAL(ES) INVOLUCRADO(S):</label>
            <div class="row g-2">
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Circulatorio" id="s1"><label class="form-check-label" for="s1">Circulatorio</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Digestivo" id="s2"><label class="form-check-label" for="s2">Digestivo</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Endócrino" id="s3"><label class="form-check-label" for="s3">Endócrino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Linfático e inmunológico" id="s4"><label class="form-check-label" for="s4">Linfático e inmunológico</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Muscular" id="s5"><label class="form-check-label" for="s5">Muscular</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Nervioso" id="s6"><label class="form-check-label" for="s6">Nervioso</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Óseo" id="s7"><label class="form-check-label" for="s7">Óseo</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Reproductor femenino" id="s8"><label class="form-check-label" for="s8">Reproductor femenino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Reproductor masculino" id="s9"><label class="form-check-label" for="s9">Reproductor masculino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Respiratorio" id="s10"><label class="form-check-label" for="s10">Respiratorio</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Tegumentario" id="s11"><label class="form-check-label" for="s11">Tegumentario</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Urinario" id="s12"><label class="form-check-label" for="s12">Urinario</label></div></div>
            </div>
        </div>

        <!-- ASIGNATURAS RELACIONADAS -->
        <h2 class="section-title">ASIGNATURA(S) RELACIONADA(S) AL CASO</h2>
        
        <div class="row g-4 mb-4">
            <!-- Primer Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded">
                    <h6 class="fw-bold text-primary mb-3">Primer Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Anatomía Humana" id="a1_1"><label class="form-check-label" for="a1_1">Anatomía Humana</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Bioquímica y Biología Molecular" id="a1_2"><label class="form-check-label" for="a1_2">Bioquímica y Biología Molecular</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Biología Celular e Histología Médica" id="a1_3"><label class="form-check-label" for="a1_3">Biología Celular e Histología Médica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Embriología Humana" id="a1_4"><label class="form-check-label" for="a1_4">Embriología Humana</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Básico-Clínica I" id="a1_5"><label class="form-check-label" for="a1_5">Integración Básico-Clínica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Informática Biomédica I" id="a1_6"><label class="form-check-label" for="a1_6">Informática Biomédica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Psicología Médica y Salud Mental" id="a1_7"><label class="form-check-label" for="a1_7">Psicología Médica y Salud Mental</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Digital I" id="a1_8"><label class="form-check-label" for="a1_8">Salud Digital I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Pública y Comunidad" id="a1_9"><label class="form-check-label" for="a1_9">Salud Pública y Comunidad</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-1" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-1">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-1-otro-texto" name="asignaturas_otra_1" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Segundo Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-success mb-3">Segundo Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Farmacología" id="a2_1"><label class="form-check-label" for="a2_1">Farmacología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Fisiología" id="a2_2"><label class="form-check-label" for="a2_2">Fisiología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Inmunología" id="a2_3"><label class="form-check-label" for="a2_3">Inmunología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Básico-Clínica II" id="a2_4"><label class="form-check-label" for="a2_4">Integración Básico-Clínica II</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Introducción a la Cirugía" id="a2_5"><label class="form-check-label" for="a2_5">Introducción a la Cirugía</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Microbiología y Parasitología" id="a2_6"><label class="form-check-label" for="a2_6">Microbiología y Parasitología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Promoción de la Salud en el Ciclo de Vida" id="a2_7"><label class="form-check-label" for="a2_7">Promoción de la Salud en el Ciclo de Vida</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Digital II" id="a2_8"><label class="form-check-label" for="a2_8">Salud Digital II</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-2" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-2">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-2-otro-texto" name="asignaturas_otra_2" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Tercer Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-danger mb-3">Tercer Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Anatomía Patológica" id="a3_1"><label class="form-check-label" for="a3_1">Anatomía Patológica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Antropología Médica e Interculturalidad" id="a3_2"><label class="form-check-label" for="a3_2">Antropología Médica e Interculturalidad</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Defensa del Huésped" id="a3_3"><label class="form-check-label" for="a3_3">Defensa del Huésped</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Dinámica Corporal" id="a3_4"><label class="form-check-label" for="a3_4">Dinámica Corporal</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Epidemiología Clínica y Medicina Basada en Evidencias" id="a3_5"><label class="form-check-label" for="a3_5">Epidemiología Clínica y Medicina Basada en Evidencias</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Farmacología Terapéutica" id="a3_6"><label class="form-check-label" for="a3_6">Farmacología Terapéutica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Genética Clínica" id="a3_7"><label class="form-check-label" for="a3_7">Genética Clínica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Homeóstasis Hemodinámica" id="a3_8"><label class="form-check-label" for="a3_8">Homeóstasis Hemodinámica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Iniciación Clínica" id="a3_9"><label class="form-check-label" for="a3_9">Iniciación Clínica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Clínico-básica I" id="a3_10"><label class="form-check-label" for="a3_10">Integración Clínico-básica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Psicológica y Comunicación" id="a3_11"><label class="form-check-label" for="a3_11">Medicina Psicológica y Comunicación</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Metabolismo" id="a3_12"><label class="form-check-label" for="a3_12">Metabolismo</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Mental" id="a3_13"><label class="form-check-label" for="a3_13">Salud Mental</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-3" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-3">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-3-otro-texto" name="asignaturas_otra_3" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Cuarto Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-warning mb-3" style="color: #d39e00 !important;">Cuarto Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Ambiente Trabajo y Salud" id="a4_1"><label class="form-check-label" for="a4_1">Ambiente Trabajo y Salud</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Bioética Médica y Profesionalismo" id="a4_2"><label class="form-check-label" for="a4_2">Bioética Médica y Profesionalismo</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Historia y Filosofía de la Medicina" id="a4_3"><label class="form-check-label" for="a4_3">Historia y Filosofía de la Medicina</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Clínico-básica II" id="a4_4"><label class="form-check-label" for="a4_4">Integración Clínico-básica II</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Clínico-Quirúrgica" id="a4_5"><label class="form-check-label" for="a4_5">Medicina Clínico-Quirúrgica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina de Urgencias" id="a4_6"><label class="form-check-label" for="a4_6">Medicina de Urgencias</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Legal" id="a4_7"><label class="form-check-label" for="a4_7">Medicina Legal</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud del Adulto Mayor" id="a4_8"><label class="form-check-label" for="a4_8">Salud del Adulto Mayor</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud de la Mujer" id="a4_9"><label class="form-check-label" for="a4_9">Salud de la Mujer</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud del Niño y Adolescente" id="a4_10"><label class="form-check-label" for="a4_10">Salud del Niño y Adolescente</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-4" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-4">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-4-otro-texto" name="asignaturas_otra_4" placeholder="Especificar...">
                    </div>
                </div>
            </div>
        </div>

        <!-- PRESENTACIÓN DEL CASO -->
        <h2 class="section-title">PRESENTACIÓN DEL CASO</h2>
        
        <div class="mb-3">
            <label for="resumen" class="form-label fw-bold">RESUMEN DEL CASO:</label>
            <textarea class="form-control" id="resumen" name="resumen" rows="3" placeholder="Descripción general: Paciente (Masculino/Femenino) de (__) años de edad, acude a..."></textarea>
        </div>

        <div class="mb-3">
            <label for="historia" class="form-label fw-bold">Historia Clínica / Antecedentes:</label>
            <textarea class="form-control" id="historia" name="historia" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label for="exploracion" class="form-label fw-bold">Exploración física y Auscultación:</label>
            <textarea class="form-control" id="exploracion" name="exploracion" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label for="laboratorios" class="form-label fw-bold">Laboratorios, Imágenes y otros exámenes:</label>
            <textarea class="form-control mb-2" id="laboratorios" name="laboratorios" rows="3" placeholder="Describe los hallazgos principales de laboratorio o gabinete..."></textarea>
            
            <!-- Componente de Archivos Adjuntos -->
            <div class="border p-3 rounded bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-bold"><i class="bi bi-paperclip"></i> Archivos adjuntos (Imágenes, PDFs, etc.)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-lab-file">
                        <i class="bi bi-plus-circle me-1"></i> Agregar archivo(s)
                    </button>
                    <!-- Input oculto para manejar el explorador de archivos -->
                    <input type="file" class="d-none" id="input-lab-files" multiple accept="image/*,.pdf,.doc,.docx">
                </div>
                <!-- Contenedor para la lista de archivos -->
                <ul class="list-group list-group-flush mt-2" id="lab-files-list">
                    <!-- Los archivos se renderizan aquí dinámicamente -->
                </ul>
            </div>
        </div>

        <div class="mb-4">
            <label for="diagnostico_tratamiento" class="form-label fw-bold">Diagnóstico, Tratamiento y seguimiento:</label>
            <textarea class="form-control" id="diagnostico_tratamiento" name="diagnostico_tratamiento" rows="3"></textarea>
        </div>

        <!-- DIAGNÓSTICOS DIFERENCIALES (Dinámico) -->
        <h2 class="section-title">DIAGNÓSTICOS DIFERENCIALES</h2>
        
        <div id="diagnosticos-container">
            <div class="card card-diagnostic mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Diagnóstico Diferencial 1</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="diag_dif_1" placeholder="Nombre del diagnóstico diferencial...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-success"><i class="bi bi-plus-circle me-1"></i> Puntos a favor:</label>
                            <textarea class="form-control" name="diag_dif_favor_1" rows="2" placeholder="Hallazgos que apoyan..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-danger"><i class="bi bi-dash-circle me-1"></i> Puntos en contra:</label>
                            <textarea class="form-control" name="diag_dif_contra_1" rows="2" placeholder="Hallazgos que descartan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="button" id="btn-add-diagnostico" class="btn btn-outline-secondary mb-4">
            <i class="bi bi-journal-plus me-1"></i> Agregar otro diagnóstico
        </button>

        <!-- PREGUNTAS Y RESPUESTAS (Dinámico) -->
        <h2 class="section-title">PREGUNTAS Y POSIBLES RESPUESTAS</h2>
        
        <div id="preguntas-container">
            <div class="card card-question mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Pregunta 1</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Enunciado de la pregunta:</label>
                        <input type="text" class="form-control" name="pregunta_1" placeholder="Escribe la pregunta aquí...">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <!-- Opción 1 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 1</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_1" placeholder="Texto de la opción 1">
                                <textarea class="form-control form-control-sm" name="p1_razon_1" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 2 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 2</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_2" placeholder="Texto de la opción 2">
                                <textarea class="form-control form-control-sm" name="p1_razon_2" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 3 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 3</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_3" placeholder="Texto de la opción 3">
                                <textarea class="form-control form-control-sm" name="p1_razon_3" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 4 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 4</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_4" placeholder="Texto de la opción 4">
                                <textarea class="form-control form-control-sm" name="p1_razon_4" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-success"><i class="bi bi-check-circle me-1"></i>Respuesta Correcta:</label>
                        <select class="form-select border-success" name="p1_correcta">
                            <option value="" selected disabled>-- Selecciona la opción correcta --</option>
                            <option value="Opción 1">Opción 1</option>
                            <option value="Opción 2">Opción 2</option>
                            <option value="Opción 3">Opción 3</option>
                            <option value="Opción 4">Opción 4</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="button" id="btn-add-pregunta" class="btn btn-outline-primary mb-4">
            <i class="bi bi-patch-question me-1"></i> Agregar otra pregunta
        </button>

        <!-- MATERIAL Y COMPLEMENTOS -->
        <h2 class="section-title">MATERIAL COMPLEMENTARIO Y EXTRAS</h2>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Material que complementa el caso (Enlaces o Referencias):</label>
            <div id="enlaces-container">
                <div class="input-group mb-2 enlace-input-group">
                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                    <input type="text" class="form-control" name="enlaces_material" placeholder="Ej. https://... o referencia bibliográfica">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar enlace"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-enlace" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otro enlace
            </button>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">TABLA SOAP:</label>
            <div class="row g-3">
                <!-- Subjetivo -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_subjetivo" class="form-label fw-bold text-primary small">S - Subjetivo:</label>
                        <textarea class="form-control" id="soap_subjetivo" name="soap_subjetivo" rows="3" placeholder="Síntomas reportados por el paciente, historia contada..."></textarea>
                    </div>
                </div>
                <!-- Objetivo -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_objetivo" class="form-label fw-bold text-success small">O - Objetivo:</label>
                        <textarea class="form-control" id="soap_objetivo" name="soap_objetivo" rows="3" placeholder="Signos vitales, exploración física, hallazgos de laboratorio y gabinete..."></textarea>
                    </div>
                </div>
                <!-- Análisis -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_analisis" class="form-label fw-bold text-warning small" style="color: #d39e00 !important;">A - Análisis:</label>
                        <textarea class="form-control" id="soap_analisis" name="soap_analisis" rows="3" placeholder="Evaluación del cuadro, razonamiento clínico, impresión diagnóstica..."></textarea>
                    </div>
                </div>
                <!-- Plan -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_plan" class="form-label fw-bold text-danger small">P - Plan:</label>
                        <textarea class="form-control" id="soap_plan" name="soap_plan" rows="3" placeholder="Tratamiento, seguimiento, estudios adicionales, interconsultas..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">SIGNOS VITALES:</label>
            <div class="row g-3">
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Tensión Arterial (mmHg)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-heart-pulse text-danger"></i></span>
                        <input type="text" class="form-control" name="signos_ta" placeholder="Ej. 120/80">
                    </div>
                </div>
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Frec. Cardiaca (lpm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-activity text-danger"></i></span>
                        <input type="number" class="form-control" name="signos_fc" placeholder="Ej. 80">
                    </div>
                </div>
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Frec. Respiratoria (rpm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-lungs text-info"></i></span>
                        <input type="number" class="form-control" name="signos_fr" placeholder="Ej. 16">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg">
                    <label class="form-label small fw-bold text-muted">Temperatura (°C)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-thermometer-half text-warning"></i></span>
                        <input type="number" step="0.1" class="form-control" name="signos_temp" placeholder="Ej. 36.5">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg">
                    <label class="form-label small fw-bold text-muted">Sat. de Oxígeno (%)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-droplet-fill text-primary"></i></span>
                        <input type="number" min="0" max="100" class="form-control" name="signos_sato2" placeholder="Ej. 98">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">SOMATOMETRÍA:</label>
            <div class="row g-3">
                <div class="col-sm-6 col-md-6">
                    <label class="form-label small fw-bold text-muted">Peso (kg)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person-fill text-secondary"></i></span>
                        <input type="number" step="0.1" class="form-control" name="somatometria_peso" placeholder="Ej. 70.5">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6">
                    <label class="form-label small fw-bold text-muted">Talla (cm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-rulers text-secondary"></i></span>
                        <input type="number" step="0.1" class="form-control" name="somatometria_talla" placeholder="Ej. 175">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label for="prompts" class="form-label fw-bold">PROMPTS DE IA:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-robot"></i></span>
                <textarea class="form-control" id="prompts" name="prompts" rows="3" placeholder="Ej. Actúa como un paciente simulado con dolor abdominal..."></textarea>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">TIPO DE INTERROGATORIO AL PACIENTE VIRTUAL:</label>
            <div class="d-flex flex-wrap gap-4 mt-1">
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_directo" value="direct">
                    <label class="form-check-label" for="int_directo">Interrogatorio Directo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_indirecto" value="indirect">
                    <label class="form-check-label" for="int_indirecto">Interrogatorio Indirecto</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_hibrido" value="hybrid">
                    <label class="form-check-label" for="int_hibrido">Interrogatorio Híbrido</label>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label for="nota_medica" class="form-label fw-bold">NOTA MÉDICA:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-file-earmark-medical text-primary"></i></span>
                <textarea class="form-control" id="nota_medica" name="nota_medica" rows="4" placeholder="Redacte la nota médica (ingreso, evolución, indicaciones, etc.)..."></textarea>
            </div>
        </div>

        <!-- SEMIOLOGÍA MÉDICA GENERADA POR IA -->
        <h2 class="section-title">SEMIOLOGÍA MÉDICA GENERADA POR IA <i class="bi bi-robot ms-2 text-primary"></i></h2>
        
        <div class="row g-3 mb-4">
            <!-- Anamnesis -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_anamnesis" class="form-label fw-bold text-secondary small"><i class="bi bi-chat-left-text me-1"></i> Anamnesis:</label>
                    <textarea class="form-control" id="ia_anamnesis" name="ia_anamnesis" rows="4" placeholder="Información generada por IA referente al interrogatorio e historial del paciente..."></textarea>
                </div>
            </div>
            <!-- Examen Físico -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_examen_fisico" class="form-label fw-bold text-secondary small"><i class="bi bi-activity me-1"></i> Examen Físico:</label>
                    <textarea class="form-control" id="ia_examen_fisico" name="ia_examen_fisico" rows="4" placeholder="Información generada por IA sobre la exploración física..."></textarea>
                </div>
            </div>
            <!-- Impresión Diagnóstica -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_impresion_diagnostica" class="form-label fw-bold text-secondary small"><i class="bi bi-clipboard2-pulse me-1"></i> Impresión Diagnóstica:</label>
                    <textarea class="form-control" id="ia_impresion_diagnostica" name="ia_impresion_diagnostica" rows="4" placeholder="Conclusión y razonamiento diagnóstico generado por la IA..."></textarea>
                </div>
            </div>
            <!-- Plan de Tratamiento -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_plan_tratamiento" class="form-label fw-bold text-secondary small"><i class="bi bi-capsule me-1"></i> Plan de Tratamiento:</label>
                    <textarea class="form-control" id="ia_plan_tratamiento" name="ia_plan_tratamiento" rows="4" placeholder="Sugerencias de manejo, recetas o intervenciones dadas por la IA..."></textarea>
                </div>
            </div>
        </div>

        <!-- SIMULACIÓN -->
        <h2 class="section-title">SIMULACIÓN <i class="bi bi-person-video ms-2 text-primary"></i></h2>
        
        <div class="mb-4">
            <label class="form-label fw-bold">Preguntas para la entrevista al paciente virtual:</label>
            <div id="simulacion-preguntas-container">
                <div class="input-group mb-2 simulacion-input-group">
                    <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
                    <input type="text" class="form-control" name="simulacion_preguntas" placeholder="Escribe una pregunta para la entrevista al paciente virtual...">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-simulacion-pregunta" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otra pregunta
            </button>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Preguntas del evaluador al médico para la metacognición:</label>
            <div id="metacognicion-preguntas-container">
                <div class="input-group mb-2 metacognicion-input-group">
                    <span class="input-group-text"><i class="bi bi-lightbulb text-warning"></i></span>
                    <input type="text" class="form-control" name="metacognicion_preguntas" placeholder="Escribe una pregunta de metacognición...">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-metacognicion-pregunta" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otra pregunta
            </button>
        </div>
        
        <!-- INSTRUMENTO ART_es -->
        <h2 class="section-title mt-5">INSTRUMENTO Assessment of Reasoning Tool – Reconstructed Versión en español adaptada (ART_es) <i class="bi bi-clipboard2-data ms-2 text-primary"></i></h2>
        <p class="text-muted mb-4">Complete los campos de evaluación (1 al 5) para cada ítem de las diferentes dimensiones.</p>
        
        <!-- DIMENSIÓN 1 -->
        <div class="mb-4 p-4 border rounded bg-light">
            <h4 class="fw-bold text-primary mb-3">DIMENSIÓN 1: Recolección de información dirigida por hipótesis</h4>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 1</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i1_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i1_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i1_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i1_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i1_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 2</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i2_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i2_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i2_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i2_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i2_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-0 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 3</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i3_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i3_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i3_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i3_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d1_i3_5" rows="2"></textarea></div>
                </div>
            </div>
        </div>

        <!-- DIMENSIÓN 2 -->
        <div class="mb-4 p-4 border rounded bg-light">
            <h4 class="fw-bold text-primary mb-3">DIMENSIÓN 2: Representación del problema</h4>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 1</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i1_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i1_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i1_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i1_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i1_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 2</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i2_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i2_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i2_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i2_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i2_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-0 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 3</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i3_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i3_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i3_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i3_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d2_i3_5" rows="2"></textarea></div>
                </div>
            </div>
        </div>

        <!-- DIMENSIÓN 3 -->
        <div class="mb-4 p-4 border rounded bg-light">
            <h4 class="fw-bold text-primary mb-3">DIMENSIÓN 3: Diagnóstico diferencial priorizado</h4>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 1</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i1_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i1_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i1_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i1_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i1_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 2</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i2_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i2_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i2_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i2_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i2_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-0 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 3</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i3_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i3_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i3_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i3_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d3_i3_5" rows="2"></textarea></div>
                </div>
            </div>
        </div>

        <!-- DIMENSIÓN 4 -->
        <div class="mb-4 p-4 border rounded bg-light">
            <h4 class="fw-bold text-primary mb-3">DIMENSIÓN 4: Evaluación diagnóstica</h4>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 1</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i1_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i1_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i1_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i1_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i1_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 2</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i2_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i2_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i2_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i2_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i2_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-0 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 3</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i3_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i3_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i3_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i3_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d4_i3_5" rows="2"></textarea></div>
                </div>
            </div>
        </div>

        <!-- DIMENSIÓN 5 -->
        <div class="mb-4 p-4 border rounded bg-light">
            <h4 class="fw-bold text-primary mb-3">DIMENSIÓN 5: Conciencia de tendencias cognitivas y factores emocionales</h4>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 1</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i1_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i1_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i1_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i1_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i1_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-3 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 2</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i2_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i2_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i2_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i2_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i2_5" rows="2"></textarea></div>
                </div>
            </div>
            <div class="bg-white p-3 border rounded mb-0 shadow-sm art-item-container">
                <h5 class="fw-bold mb-2 text-secondary">Item 3</h5>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">1</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i3_1" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">2</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i3_2" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">3</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i3_3" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">4</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i3_4" rows="2"></textarea></div>
                    <div class="col-12 col-sm-6 col-md flex-grow-1"><label class="form-label fw-bold small text-muted">5</label><textarea class="form-control form-control-sm border-primary" name="art_d5_i3_5" rows="2"></textarea></div>
                </div>
            </div>
        </div>

        <hr class="my-5">

        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm" id="btn-guardar">
            <i class="bi bi-cloud-arrow-up me-2"></i> GUARDAR CASO CLÍNICO
        </button>
        
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Lógica de JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // Contadores para asegurar names únicos
    let contadorPreguntas = 1;
    let contadorDiagnosticos = 1;

    // Referencias al DOM principales
    const btnAddPregunta = document.getElementById('btn-add-pregunta');
    const btnAddDiagnostico = document.getElementById('btn-add-diagnostico');
    const btnAddAutor = document.getElementById('btn-add-autor');
    const btnAddEnlace = document.getElementById('btn-add-enlace');
    const btnAddSimulacion = document.getElementById('btn-add-simulacion-pregunta');
    const btnAddMetacognicion = document.getElementById('btn-add-metacognicion-pregunta');
    const containerPreguntas = document.getElementById('preguntas-container');
    const containerDiagnosticos = document.getElementById('diagnosticos-container');
    const containerAutores = document.getElementById('autores-container');
    const containerEnlaces = document.getElementById('enlaces-container');
    const containerSimulacion = document.getElementById('simulacion-preguntas-container');
    const containerMetacognicion = document.getElementById('metacognicion-preguntas-container');
    const formulario = document.getElementById('formulario-caso');

    // Lógica para el select "Otro" en Padecimiento
    const selectPadecimiento = document.getElementById('padecimiento');
    const inputPadecimientoOtro = document.getElementById('padecimiento-otro-texto');
    selectPadecimiento.addEventListener('change', (e) => {
        if(e.target.value === 'Otro') {
            inputPadecimientoOtro.classList.remove('d-none');
            inputPadecimientoOtro.focus();
        } else {
            inputPadecimientoOtro.classList.add('d-none');
            inputPadecimientoOtro.value = ''; // Limpiar si se desmarca
        }
    });

    // Lógica para el checkbox "Otro" en departamentos
    const checkboxOtro = document.getElementById('checkbox-otro');
    const inputOtroTexto = document.getElementById('departamento-otro-texto');
    checkboxOtro.addEventListener('change', (e) => {
        if(e.target.checked) {
            inputOtroTexto.classList.remove('d-none');
            inputOtroTexto.focus();
        } else {
            inputOtroTexto.classList.add('d-none');
            inputOtroTexto.value = ''; // Limpiar si se desmarca
        }
    });

    // Lógica dinámica para checkboxes "Otra" en Asignaturas
    for (let i = 1; i <= 4; i++) {
        const checkAsig = document.getElementById(`checkbox-asig-${i}`);
        const textAsig = document.getElementById(`asig-${i}-otro-texto`);
        if (checkAsig && textAsig) {
            checkAsig.addEventListener('change', (e) => {
                if(e.target.checked) {
                    textAsig.classList.remove('d-none');
                    textAsig.focus();
                } else {
                    textAsig.classList.add('d-none');
                    textAsig.value = '';
                }
            });
        }
    }

    // Añadir Autor dinámicamente
    btnAddAutor.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 autor-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" name="autores" placeholder="Nombre completo del autor">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar autor"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerAutores.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Pregunta dinámicamente
    btnAddPregunta.addEventListener('click', () => {
        contadorPreguntas++;
        const html = `
            <div class="card card-question mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Pregunta ${contadorPreguntas}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Enunciado de la pregunta:</label>
                        <input type="text" class="form-control" name="pregunta_${contadorPreguntas}" placeholder="Escribe la pregunta aquí...">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 1</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_1" placeholder="Texto de la opción 1">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_1" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 2</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_2" placeholder="Texto de la opción 2">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_2" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 3</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_3" placeholder="Texto de la opción 3">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_3" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 4</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_4" placeholder="Texto de la opción 4">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_4" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-success"><i class="bi bi-check-circle me-1"></i>Respuesta Correcta:</label>
                        <select class="form-select border-success" name="p${contadorPreguntas}_correcta">
                            <option value="" selected disabled>-- Selecciona la opción correcta --</option>
                            <option value="Opción 1">Opción 1</option>
                            <option value="Opción 2">Opción 2</option>
                            <option value="Opción 3">Opción 3</option>
                            <option value="Opción 4">Opción 4</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        containerPreguntas.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Diagnóstico Diferencial dinámicamente
    btnAddDiagnostico.addEventListener('click', () => {
        contadorDiagnosticos++;
        const html = `
            <div class="card card-diagnostic mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Diagnóstico Diferencial ${contadorDiagnosticos}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="diag_dif_${contadorDiagnosticos}" placeholder="Nombre del diagnóstico diferencial...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-success"><i class="bi bi-plus-circle me-1"></i> Puntos a favor:</label>
                            <textarea class="form-control" name="diag_dif_favor_${contadorDiagnosticos}" rows="2" placeholder="Hallazgos que apoyan..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-danger"><i class="bi bi-dash-circle me-1"></i> Puntos en contra:</label>
                            <textarea class="form-control" name="diag_dif_contra_${contadorDiagnosticos}" rows="2" placeholder="Hallazgos que descartan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        containerDiagnosticos.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Enlace dinámicamente
    btnAddEnlace.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 enlace-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                <input type="text" class="form-control" name="enlaces_material" placeholder="Ej. https://... o referencia bibliográfica">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar enlace"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerEnlaces.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Pregunta de Simulación dinámicamente
    btnAddSimulacion.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 simulacion-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
                <input type="text" class="form-control" name="simulacion_preguntas" placeholder="Escribe una pregunta para la entrevista al paciente virtual...">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerSimulacion.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Pregunta de Metacognición dinámicamente
    btnAddMetacognicion.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 metacognicion-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-lightbulb text-warning"></i></span>
                <input type="text" class="form-control" name="metacognicion_preguntas" placeholder="Escribe una pregunta de metacognición...">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerMetacognicion.insertAdjacentHTML('beforeend', html);
    });

    // Delegación de Eventos para ELIMINAR bloques dinámicos
    document.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('btn-remove')) {
            // Maneja si hicieron clic en el ícono o en el botón
            let btn = e.target.closest('.btn-remove');
            
            // Busca el contenedor padre (pregunta, diagnóstico, autor, enlace, simulación o metacognición)
            let bloque = btn.closest('.question-block') || btn.closest('.autor-input-group') || btn.closest('.enlace-input-group') || btn.closest('.simulacion-input-group') || btn.closest('.metacognicion-input-group');

            if (bloque) {
                bloque.style.transition = "opacity 0.2s ease-out, transform 0.2s ease-out";
                bloque.style.opacity = "0";
                bloque.style.transform = "scale(0.98)";
                setTimeout(() => bloque.remove(), 200);
            }
        }
    });

    // ==========================================
    // LÓGICA DE ARCHIVOS ADJUNTOS (LABORATORIOS)
    // ==========================================
    const btnAddLabFile = document.getElementById('btn-add-lab-file');
    const inputLabFiles = document.getElementById('input-lab-files');
    const labFilesList = document.getElementById('lab-files-list');
    let archivosLaboratorio = []; // Array en memoria para los archivos

    if(btnAddLabFile && inputLabFiles) {
        // Dispara el clic del input oculto
        btnAddLabFile.addEventListener('click', () => inputLabFiles.click());

        // Detecta cuando el usuario selecciona archivos
        inputLabFiles.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                // Evita duplicados comparando nombre y tamaño
                if (!archivosLaboratorio.some(f => f.name === file.name && f.size === file.size)) {
                    archivosLaboratorio.push(file);
                }
            });
            renderizarListaArchivos();
            inputLabFiles.value = ''; // Resetea para permitir volver a elegir el mismo si se borró
        });
    }

    // Función para dibujar la lista de archivos en el HTML
    function renderizarListaArchivos() {
        labFilesList.innerHTML = '';
        if (archivosLaboratorio.length === 0) {
            labFilesList.innerHTML = '<li class="list-group-item bg-transparent text-muted small text-center border-0 py-1">No hay archivos seleccionados</li>';
            return;
        }

        archivosLaboratorio.forEach((file, index) => {
            // Icono dinámico según tipo
            let icon = 'bi-file-earmark';
            if(file.type.startsWith('image/')) icon = 'bi-file-image text-primary';
            else if(file.type === 'application/pdf') icon = 'bi-file-pdf text-danger';
            
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2); // Tamaño en MB

            const li = document.createElement('li');
            li.className = 'list-group-item bg-transparent d-flex justify-content-between align-items-center px-2 py-1 animate-fade border-bottom';
            li.innerHTML = `
                <div class="d-flex align-items-center text-truncate pe-2">
                    <i class="bi ${icon} fs-5 me-2"></i>
                    <span class="text-truncate small fw-medium" title="${file.name}">${file.name}</span>
                    <span class="text-muted ms-2 small" style="font-size: 0.75rem;">(${sizeMB} MB)</span>
                </div>
                <button type="button" class="btn btn-sm text-danger btn-remove-file p-0 ms-2" data-index="${index}" title="Quitar archivo">
                    <i class="bi bi-x-circle fs-5"></i>
                </button>
            `;
            labFilesList.appendChild(li);
        });

        // Asignar evento de borrado a los nuevos botones "x"
        document.querySelectorAll('.btn-remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                archivosLaboratorio.splice(index, 1); // Borra del array
                renderizarListaArchivos(); // Redibuja
            });
        });
    }
    
    // Inicializa la vista vacía
    if(labFilesList) renderizarListaArchivos();


    // ==========================================
    // CAPTURA Y ENVÍO DE DATOS
    // ==========================================
    formulario.addEventListener('submit', (e) => {
        e.preventDefault();

        // Creamos un FormData basado en los datos actuales del formulario index.html
        const datosParaEnviar = new FormData(formulario);
        const datosDelCaso = {};
        
        const btnSubmit = document.getElementById('btn-guardar');
        const originalHTML = btnSubmit.innerHTML;

        // Recorremos los campos fijos y dinámicos presentes en el formulario de index.html
        for (let [key, value] of datosParaEnviar.entries()) {
            // Saltamos el procesamiento si es de tipo File (los archivos se manejan aparte)
            if (value instanceof File) {
                continue;
            }

            // --- OPTIMIZACIÓN PARA CAMPOS QUE DEBEN SER ARREGLOS ---
            // Detectamos si el campo es un checkbox múltiple o si termina en corchetes [] 
            // o si es parte de las listas dinámicas creadas (como las preguntas de discusión)
            if (key.endsWith('[]') || key === 'departamentos' || key === 'sistemas_anatomicos' || key === 'asignaturas' || key === 'preguntas') {
                if (!datosDelCaso[key]) {
                    datosDelCaso[key] = [];
                }
                if (value.trim() !== "") {
                    datosDelCaso[key].push(value);
                }
            } else {
                // Para los campos de texto fijos independientes e indexados (como diag_dif_1, diag_dif_favor_1, etc.)
                // Si el campo ya existe, lo convertimos en un arreglo para no perder información
                if (datosDelCaso[key]) {
                    if (!Array.isArray(datosDelCaso[key])) {
                        datosDelCaso[key] = [datosDelCaso[key]];
                    }
                    datosDelCaso[key].push(value);
                } else {
                    datosDelCaso[key] = value;
                }
            }
        }

        // Agregar referencias a los nombres de archivos en el JSON final para visibilidad
        if(archivosLaboratorio.length > 0) {
            datosDelCaso["archivos_adjuntos"] = archivosLaboratorio.map(f => f.name);
        }

        // Animación visual del botón (Reutilizando tus variables nativas del index.html)
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
        btnSubmit.disabled = true;

        // Empaquetamos todo el objeto estructurado en el FormData definitivo
        const payloadFinal = new FormData();
        payloadFinal.append('datos_json', JSON.stringify(datosDelCaso));

        // Adjuntar los archivos físicos reales del arreglo global
        archivosLaboratorio.forEach((file) => {
            payloadFinal.append('archivos[]', file);
        });

        console.log("=== DATOS ENVIADOS EN FORMATO ESTRUCTURADO ===");
        console.log(datosDelCaso);

        // Petición HTTP Fetch enviando los datos organizados al backend remoto PHP
        fetch('guardar_caso.php', {
            method: 'POST',
            body: payloadFinal
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                btnSubmit.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> ¡Guardado con éxito!';
                btnSubmit.classList.replace('btn-success', 'btn-primary');
                
                setTimeout(() => {
                    // Restauramos el botón a su estado original
                    btnSubmit.innerHTML = originalHTML;
                    btnSubmit.classList.replace('btn-primary', 'btn-success');
                    btnSubmit.disabled = false;
                    
                    // Limpiamos el formulario y estados
                    formulario.reset(); 
                    archivosLaboratorio = []; 
                    document.getElementById('lab-files-list').innerHTML = ''; 
                    
                    window.location.href = "lista_casos.php?msg=success";
                }, 3000);
            } else {
                alert("Error del servidor: " + data.message);
                btnSubmit.innerHTML = originalHTML;
                btnSubmit.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
            alert("No se pudo conectar con el servidor.");
            btnSubmit.innerHTML = originalHTML;
            btnSubmit.disabled = false;
        });
        
    });
});
</script>

</body>
</html>