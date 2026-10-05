# Sistema Web CCDB - Centro Cultural Don Bosco

Sistema Web de Control y Seguimiento de Pasantes y Modalidades de Titulación.

## Estructura del Proyecto

- `database_setup.sql`: Script DDL completo de base de datos MySQL/MariaDB con tablas, llaves foráneas, índices, vista `vista_horas_proceso`, datos maestros y pasante/usuario semilla.
- `config/`:
  - `database.php`: Conexión Singleton con PDO MySQL utf8mb4.
  - `auth.php`: Helper de sesión, auditoría y control de accesos basados en roles.
- `assets/`:
  - `css/login.css`: Estilos visuales del Login institucional.
  - `css/style.css`: Estilos del Dashboard, Sidebar, tablas, progress bar y componentes UI/UX.
  - `img/logo_donbosco.svg`: Emblema vectorial Don Bosco CCDB.
- `includes/`:
  - `header.php`, `sidebar.php`, `navbar.php`, `footer.php`.
- `views/`:
  - `pasantes/`: Listado con filtros, nuevo pasante (con carga asíncrona de carreras), edición.
  - `asistencias/`: Marcado QR/CI interactivo, historial con filtros por fecha/pasante, progreso de 1,000 horas.
  - `modalidades/`: Lista de modalidades, asignación de procesos, reporte de avance imprimible.
  - `usuarios/`: Creación y listado de administradores/supervisores.
  - `sanciones/`: Registro de faltas/atrasos y descuentos de horas acumuladas.
- `api/`:
  - `get_carreras.php`: Endpoint JSON para selección dinámica según universidad.
- `login.php` / `logout.php` / `index.php`: Flujo principal y Dashboard.

## Instrucciones de Instalación en XAMPP

1. Iniciar **Apache** y **MySQL** desde el Panel de Control de XAMPP.
2. Abrir **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Importar el archivo `database_setup.sql` ubicado en la raíz del proyecto. Esto creará la base de datos `sistema_pasantes_ccdb`, todas sus tablas, la vista de horas y los datos iniciales.
4. Ubicar la carpeta del proyecto en `htdocs` de XAMPP o configurar un VirtualHost / Alias apuntando al directorio.
5. Acceder en el navegador a: `http://localhost/Proyecto_CCDB/login.php`.
6. Credenciales de acceso:
   - **Usuario**: `admin`
   - **Contraseña**: `Admin123`
