# Sistema RSU – Práctica 03 (Mantenimiento de vehículos)

**Alumno:** Piero Ramírez Chávez · **Curso:** Tópicos Avanzados en Desarrollo de Software (USAT) · **Docente:** Mg. Miguel Díaz Vidarte

Sistema de gestión de residuos sólidos (Laravel 12 + AdminLTE). La Práctica 03 agrega **Gestión de vehículos → Mantenimiento**:
mantenimientos, horarios por vehículo y días generados automáticamente, todo por AJAX.
Detalle de cómo se hizo: [docs/PRACTICA_03_MANTENIMIENTO.md](docs/PRACTICA_03_MANTENIMIENTO.md).

## Requisitos

- **XAMPP** con PHP 8.2 o superior (se usa su MySQL). Extensiones de PHP activas: `pdo_mysql`, `mbstring`, `zip`, `gd`, `fileinfo`.
- **Composer** y **Node.js 20+**.

## Instalación (desde el ZIP)

1. Descargar el ZIP (**Code → Download ZIP**), descomprimirlo y abrir la carpeta en **Visual Studio Code**.
2. Abrir **XAMPP Control Panel** y pulsar **Start** en **MySQL**.
3. Crear la base de datos **`rsu_proyecto`** en http://localhost/phpmyadmin (cotejamiento `utf8mb4_unicode_ci`).
4. En VS Code abrir la terminal (**Ctrl + ñ**) y ejecutar, uno por uno:

   ```bash
   composer install
   npm install
   npm run build
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```

   | Comando | Para qué sirve |
   |---|---|
   | `composer install` | Descarga las librerías de PHP (carpeta `vendor`) |
   | `npm install` y `npm run build` | Descarga y compila los estilos y scripts (carpeta `public/build`) |
   | `copy .env.example .env` | Crea la configuración. Ya apunta a MySQL de XAMPP: base `rsu_proyecto`, usuario `root`, sin clave |
   | `php artisan key:generate` | Genera la clave de cifrado de la aplicación |
   | `php artisan migrate --seed` | Crea todas las tablas y carga los datos de prueba (usuarios, vehículos, personal, zonas…) |
   | `php artisan serve` | Enciende el servidor |

5. Abrir **http://127.0.0.1:8000** e iniciar sesión.

## Usuarios de prueba

| Perfil | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@rsu.test` | `Admin12345` |
| Encargado de flota | `flota@rsu.test` | `Perfil12345` |
| Operador | `operador@rsu.test` | `Operador123` |

## Probar la Práctica 03

- Menú **Gestión de vehículos → Mantenimiento**.
- Pruebas automáticas del módulo: `php artisan test --filter=MantenimientoTest`

## Problemas comunes

| Mensaje | Solución |
|---|---|
| `php` no se reconoce como comando | Agregar `C:\xampp\php` a la variable de entorno PATH y abrir otra terminal |
| `could not find driver` | En `C:\xampp\php\php.ini` quitar el `;` de `extension=pdo_mysql` y reiniciar la terminal |
| `ext-zip` o `ext-gd` al hacer `composer install` | En el mismo `php.ini` activar `extension=zip` y `extension=gd` |
| `Unknown database 'rsu_proyecto'` | Falta el paso 3 (crear la base en phpMyAdmin) |
| `Connection refused` | MySQL no está encendido en XAMPP |
| Página sin estilos | Ejecutar `npm run build` |
