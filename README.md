# 🎓 Sistema de Gestión Académica para Liceos

Sistema web completo para la administración integral de instituciones educativas de nivel medio. Desarrollado en PHP con MySQL, permite gestionar usuarios, calificaciones, materias, periodos académicos y más.

## 📋 Descripción

Plataforma web que centraliza la gestión académica y administrativa de liceos, facilitando el control de estudiantes, profesores, calificaciones y estructura educativa desde una interfaz moderna y fácil de usar.

## ✨ Características Principales

### 👥 Gestión de Usuarios
- Sistema de roles (Administrador, Profesor, Alumno)
- Autenticación segura con contraseñas encriptadas
- Perfiles personalizados por rol
- Gestión de perfiles de usuario

### 📚 Gestión Académica
- Organización por grados y secciones
- Catálogo de materias
- Periodos académicos y actividades
- Asignación de profesores a materias/secciones
- Registro y consulta de calificaciones

### 🎯 Funcionalidades por Rol

#### Administrador
- Control total del sistema
- Gestión de usuarios (crear, editar, activar/desactivar)
- Configuración de estructura académica
- Asignación de profesores y estudiantes
- Lista completa de estudiantes
- Reportes y estadísticas

#### Profesor
- Carga de calificaciones por actividad
- Gestión de actividades evaluativas
- Lista de estudiantes asignados
- Consulta de grupos y materias
- Reportes de rendimiento

#### Alumno
- Consulta de calificaciones en tiempo real
- Visualización por periodo y materia
- Filtrado de notas
- Actualización de perfil personal

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript
- **Framework CSS:** Bootstrap 5.3
- **Íconos:** Font Awesome 6.0
- **Tablas:** DataTables
- **Librerías JS:** jQuery 3.7

## 📦 Requisitos del Sistema

### Servidor
- PHP >= 7.4.0
- MySQL >= 5.7
- Apache/Nginx con mod_rewrite habilitado
- Extensiones PHP requeridas:
  - pdo
  - pdo_mysql
  - json
  - mbstring
  - session

## 🚀 Instalación

### 1. Clonar o Descargar el Proyecto
```bash
git clone https://github.com/tu-usuario/sistema-academico.git
cd sistema-academico
```

### 2. Configurar Base de Datos

1. Crear una base de datos MySQL:
```sql
CREATE DATABASE sistema_escolar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importar el esquema:
```bash
mysql -u root -p sistema_escolar < "base de datos/sistema academico final.sql"
```

3. Configurar conexión en `/includes/conexion.php`:
```php
<?php
$host = 'localhost';
$db = 'sistema_escolar';
$user = 'tu_usuario';
$pass = 'tu_contraseña';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
```

### 3. Instalar Dependencias (Opcional)
```bash
composer install
```

### 4. Configurar Permisos
```bash
chmod 755 -R .
chmod 777 includes/logs
```

### 5. Acceder al Sistema

Abrir en el navegador:
```
http://localhost/sistema-academico/
```

## 👤 Usuarios por Defecto

Después de importar la base de datos, puedes acceder con:

**Administrador:**
- Usuario: `admin`
- Contraseña: `admin123`

**Profesor:**
- Usuario: `profesor`
- Contraseña: `profesor123`

**Alumno:**
- Usuario: `alumno`
- Contraseña: `alumno123`

> ⚠️ **Importante:** Cambia estas contraseñas inmediatamente después del primer acceso.

## 📁 Estructura del Proyecto

```
sistema-academico/
├── base de datos/          # Scripts SQL
│   └── sistema academico final.sql
├── css/                    # Hojas de estilo
│   └── main.css
├── images/                 # Imágenes y recursos
│   └── liceo_logo.png
├── includes/               # Archivos de configuración
│   ├── conexion.php       # Configuración BD
│   ├── security.php       # Funciones de seguridad
│   └── logs/              # Logs del sistema
├── js/                     # JavaScript
│   ├── main.js
│   ├── jquery-3.7.0.min.js
│   └── plugins/
├── Roles/                  # Módulos por rol
│   ├── admin.php
│   ├── profesores.php
│   ├── alumno.php
│   ├── gestionar_materias.php
│   ├── gestionar_alumnos.php
│   ├── lista_estudiantes_admin.php
│   ├── lista_estudiantes_profesor.php
│   ├── mi_perfil.php
│   └── ...
├── registro/               # Módulo de registro
├── index.php              # Página de login
├── procesar_login.php     # Autenticación
├── composer.json          # Dependencias
├── .gitignore
└── README.md
```

## 🔒 Seguridad

- ✅ Contraseñas encriptadas con `password_hash()`
- ✅ Preparación de consultas SQL (PDO)
- ✅ Protección contra inyección SQL
- ✅ Validación de sesiones
- ✅ Control de acceso basado en roles
- ✅ Sanitización de datos de entrada

## 📊 Módulos Principales

### Administrador
- Gestión de Materias
- Gestión de Periodos y Actividades
- Gestión de Alumnos
- Lista de Estudiantes
- Gestión de Secciones
- Gestión de Profesores
- Registro de Usuarios
- Mi Perfil

### Profesor
- Cargar Notas
- Gestionar Actividades
- Lista de Alumnos
- Reportes
- Mi Perfil

### Alumno
- Ver Calificaciones
- Actividades
- Mi Perfil

## 📝 Licencia

Este proyecto está bajo la Licencia MIT. Ver archivo `LICENSE` para más detalles.

## 👨‍💻 Autor

- Ruben Felipe Lara Urbina - Desarrollo Inicial
- 

## 📧 Soporte

Para reportar bugs o solicitar características, abre un issue en el repositorio.




