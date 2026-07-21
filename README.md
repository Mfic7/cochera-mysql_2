# Mi Cochera — Sistema de reservas de cochera (MVP)

Sistema de reserva de espacios de cochera con backend PHP orientado a clases
(API JSON) y frontend en HTML/CSS/JS vanilla, pensado para correr sobre XAMPP
y exponerse por ngrok para demos.

## Requisitos

- XAMPP con Apache + PHP 8.2+ + MariaDB (ya incluido en este entorno).
- Proyecto ubicado en `c:\xampp\htdocs\cochera-mysql_2` (para que la URL sea
  `http://localhost/cochera-mysql_2/...`).

## Puesta en marcha

1. **Iniciar Apache y MySQL** desde el Panel de Control de XAMPP.
2. **Crear la base de datos**, desde phpMyAdmin (`http://localhost/phpmyadmin`)
   o por línea de comandos:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root < database\seed.sql
   ```
   Esto crea la base `mi_cochera` con 30 espacios, un usuario admin y los
   métodos de pago/configuración por defecto.
3. **Configurar credenciales**, si son distintas a las de una instalación
   XAMPP estándar (usuario `root` sin contraseña): editar
   `config/config.php` (ya creado a partir de `config/config.example.php`).
4. Abrir `http://localhost/cochera-mysql_2/` — página de reserva del cliente.
5. Abrir `http://localhost/cochera-mysql_2/admin/login.php` — panel de administración.

### Credenciales de administrador por defecto

- **Correo:** `admin@micochera.com`
- **Contraseña:** `admin123`

Cámbiala apenas puedas: no hay UI para esto en el MVP, así que genera un
nuevo hash y actualízalo directamente en la tabla `usuarios_admin`:

```
C:\xampp\php\php.exe -r "echo password_hash('tu-nueva-clave', PASSWORD_DEFAULT);"
```

## Exponer por ngrok

```
ngrok http 80
```

Luego visita la URL de ngrok seguida de `/cochera-mysql_2/` (ej.
`https://xxxx.ngrok-free.app/cochera-mysql_2/`). No hace falta cambiar nada en el
código: `config/config.php` → `app_base_path` ya asume que el proyecto vive
bajo `/cochera-mysql_2`, y todas las llamadas a la API se arman a partir de eso tanto
en `localhost` como detrás del túnel.

## Qué incluye este MVP

- Reserva de espacios con verificación de disponibilidad en tiempo real y
  bloqueo temporal de 5 minutos (configurable) mientras el cliente completa
  el pago.
- Control de concurrencia a nivel de base de datos (transacción +
  `SELECT ... FOR UPDATE`) para evitar que dos clientes reserven el mismo
  espacio a la vez — primero en confirmar, se lo lleva.
- Pago del 50% de adelanto mediante comprobante manual (Yape/Plin/
  Transferencia), validado por el administrador desde el panel.
- Estados de reserva completos con historial auditable (quién cambió qué y
  cuándo): pendiente de pago, en validación, adelanto pagado, pago completo,
  cancelada, vencida.
- Panel de administración: dashboard con KPIs, ocupación en vivo, gráficos de
  ingresos y métodos de pago, gestión de reservas y pagos, y CRUD básico de
  espacios / métodos de pago / configuración del negocio.

## Fuera de alcance en esta primera versión

- Animación del vehículo llegando al establecimiento.
- Licenciamiento/suscripción con bloqueo real de acceso (la tarjeta de
  "Suscripción" es solo informativa).
- Integración real con pasarelas de pago (Yape/Plin/tarjetas) — el pago se
  valida manualmente por comprobante, por decisión explícita para este MVP.
- Exportación de reportes / PDF, y los módulos de Calendario, Clientes,
  Vehículos y Usuarios (quedan como "Próximamente" en el sidebar).

## Notas técnicas

- La expiración de los bloqueos de 5 minutos es **perezosa**: se recalcula en
  cada lectura de disponibilidad, sin depender de un cron. `scripts/expire_holds.php`
  es un respaldo opcional que puedes programar en el Task Scheduler de
  Windows si quieres una limpieza periódica adicional, pero no es necesario
  para que el sistema funcione correctamente.
- `src/`, `config/`, `database/`, `scripts/` y `storage/` están bloqueados a
  acceso HTTP directo vía `.htaccess`. Antes de exponer por ngrok, confirma
  que `Require all denied` esté disponible (`mod_authz_core` está habilitado
  por defecto en el Apache de XAMPP).

  ## Cambio de la ruta de acceso del proyecto en la configurcion
  // Cambiar la variable:
// 'cochera-mysql_2/cochera-mysql_2'
// por:
// 'cochera-mysql_2'
