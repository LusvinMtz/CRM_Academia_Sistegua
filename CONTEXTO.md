# Contexto del proyecto — Academia Sistegua

Documento vivo: se actualiza al terminar cada avance. Resume qué hace el sistema, cómo está
construido, qué está listo y qué falta.

## 1. Qué es

Plataforma web de **Academia Sistegua**, la academia de capacitación de una empresa de
**materiales de construcción liviana** (tabla yeso, cielo falso, perfilería). Sirve para
organizar y controlar las **capacitaciones a clientes** (instaladores, contratistas,
ferreterías, arquitectos), desde la **invitación por correo** hasta la **asistencia** y la
**entrega del certificado**.

Sedes de demostración: **Ciudad de Guatemala**, **Quetzaltenango** y **Chiquimula**
(ajustar a las sucursales reales antes de producción).

## 2. Tecnología y cómo levantarlo

| Tema | Detalle |
|---|---|
| Carpeta | `CRM_Academia_Sistegua/` |
| Origen | Parte del sistema del Colegio Esteca PC (`CRM_Demo_esteca`), adaptado al giro |
| Backend y vistas | Laravel 12 + Blade (sin Node, sin build) |
| Diseño | CSS de la plantilla Metronic en `public/assets/css/style.bundle.css`, ajustes propios en `app.css`, íconos Keenicons |
| Base de datos | MySQL 8, base `academia_sistegua` (usuario root, contraseña en `.env`) |
| Pruebas | MySQL, base `academia_sistegua_test` — `php artisan test` |
| Paquetes | spatie/laravel-permission, maatwebsite/excel, barryvdh/laravel-dompdf, chillerlan/php-qrcode, laravel-lang |
| PHP | 8.2 |

Levantar: `php artisan serve --port=8002` → http://127.0.0.1:8002
Usuario inicial: `admin@sistegua.com` / `Admin12345` (cambiarla en Mi perfil).

Datos institucionales (nombre, sedes, lema) en `config/academia.php`.
Logo: `public/assets/media/logos/academia-logo.png` y `academia-icon.png`.
**Pendiente: los logos son todavía los del proyecto anterior; hay que reemplazar esos dos archivos.**

## 3. Modelo de dominio

Se conserva la arquitectura del sistema original, simplificada a un solo público:

- **Contactos**: un único tipo, `cliente`. Campos propios: `empresa` y `oficio`
  (instalador, contratista, ferretería, arquitecto…). La estructura de `Contacto::TIPOS`
  permite abrir otro tipo (p. ej. distribuidores) sin tocar rutas ni controladores.
- **Eventos**: un único tipo, `capacitacion`, presencial o virtual.
- **Grupos**: segmentan a los clientes por oficio y sede.
- **Invitaciones**: una por persona y capacitación; guardan envío, respuesta y asistencia.

## 4. Estado por módulo

| Módulo | Contenido | Estado |
|---|---|---|
| Acceso | Usuarios, roles y permisos, catálogo de Guatemala (22 departamentos, 340 municipios INE) | ✅ |
| Clientes | Sedes, directorio, grupos, carga y exportación Excel | ✅ |
| Capacitaciones | Presenciales o virtuales, estados, duplicar, archivo `.ics` | ✅ |
| Invitaciones | Correo, confirmación pública, recordatorios, plantillas | ✅ |
| Correo de envío | Cuenta SMTP editable desde *Administración → Correo de envío* (contraseña cifrada), modo de prueba y correo de prueba | ✅ |
| WhatsApp | Envío por WasenderAPI (invitaciones, recordatorios, avisos y constancias). Token, código de país y pausa en *Administración → WhatsApp* (token cifrado), modo de prueba y mensaje de prueba | ✅ |
| Asistencia | Lista manual, **QR del evento y QR personal**, listas PDF/Excel | ✅ |
| Certificados | **Editor visual de diseños** + entrega en PDF, envío por correo y verificación pública | ✅ |
| Reportes | Por capacitación y por persona, con exportación a Excel | ✅ |
| Tablero | Indicadores, gráfica mensual y avisos de pendientes | ✅ |

Pruebas automáticas: **98**, todas pasan.

**Datos de demostración** (`php artisan migrate:fresh --seed`): 400 clientes con apellidos
típicos de cada región, 13 grupos, 25 capacitaciones del último año con ~2,000 invitaciones
y asistencia simulada.

## 5. Editor visual de certificados

Es la función propia de este proyecto (el sistema anterior tenía el certificado fijo en código).

- **Dónde**: menú *Formación → Certificados*. Permiso nuevo: módulo `certificados`.
- **Modelo** `PlantillaCertificado` (`plantillas_certificado`): nombre, orientación
  (horizontal/vertical), imagen de fondo opcional y un JSON `elementos`.
- **Elementos**: `texto`, `imagen` (logo o archivo subido), `linea` y `marco`. Cada uno guarda
  su posición y tamaño **en porcentaje de la hoja**, de modo que el lienzo del navegador y el
  PDF coinciden.
- **Editor** (`resources/views/certificados/editor.blade.php`): JavaScript propio, sin
  librerías. Arrastrar para mover, tirador lateral para el ancho, panel de propiedades
  (texto, tamaño, color, alineación, negrita, cursiva, mayúsculas), orden de capas, duplicar,
  eliminar, subir firmas/sellos y fondo.
- **Variables** (`PlantillaCertificado::VARIABLES`): `{nombre}`, `{empresa}`, `{oficio}`,
  `{curso}`, `{fecha}`, `{duracion}`, `{facilitador}`, `{sede}`, `{lugar}`, `{academia}`,
  `{codigo}`.
- **PDF** (`resources/views/pdf/constancia.blade.php`): convierte los porcentajes a puntos
  (carta: 792×612 pt horizontal) y posiciona todo en absoluto, que es lo único que dompdf
  coloca de forma fiable. Una página por asistente.
- **Seguridad**: `PlantillaCertificado::sanear()` filtra lo que llega del editor (solo tipos
  conocidos, valores dentro de rango, colores válidos, texto sin HTML).
- Cada capacitación puede usar un diseño propio (`eventos.plantilla_certificado_id`); si no,
  se usa el marcado como predeterminado.

## 6. WhatsApp (WasenderAPI)

- **Configuración**: `ConfiguracionWhatsapp` (tabla `configuracion_whatsapp`): modo (`api` | `log`), token cifrado,
  código de país (se antepone a teléfonos de 8 dígitos) y pausa entre mensajes. Mismos permisos que el correo (`correo.*`).
- **Cliente**: `App\Services\WhatsApp` (`POST /api/send-message` con `Authorization: Bearer`). Modo de prueba → `storage/logs/whatsapp.log`.
- **Canales por invitación**: `correo_estado` y `whatsapp_estado`; `estado_envio` es el general (enviada si algún canal llegó).
  Cada canal se invita por separado: invitar por WhatsApp no impide invitar después por correo, y viceversa.
- **Botones**: en *Enviar invitaciones* y *Enviar recordatorio* hay "Por WhatsApp" y "Por correo"; en cada invitado,
  enviar/reenviar por cada canal; en asistencia, constancias por WhatsApp o por correo.
- Los **avisos** (cambio, posposición, cancelación) y el **recordatorio automático** salen por los canales por los que se invitó a cada persona.
- **Constancias**: WasenderAPI descarga el PDF de `/constancia/{token}/pdf` (enlace firmado, 30 días). Requiere `APP_URL` público.
- **Protección contra bloqueos** (`proteccion`, activa por defecto): mínimo 5 s entre mensajes y espera al azar entre la pausa
  y el doble (8 → 8–16 s). `WhatsApp::turno()` reparte los turnos en una sola fila para todo el sistema (caché + lock).
  Además conviene activar *Account Protection* en la sesión de WasenderAPI. Si la API responde 429, el trabajo vuelve a la cola.
- **Solo clientes activos**: invitaciones, recordatorios, avisos, reenvíos y envío de constancias excluyen a los inactivos
  aunque estén en los grupos invitados (la descarga de constancias en PDF sí los incluye).

## 7. Cola de envíos

- `QUEUE_CONNECTION=database`: correos y WhatsApp salen en segundo plano.
- **No necesita tarea programada ni ventana abierta**: `App\Services\ProcesadorEnvios` procesa la cola al terminar
  la petición web en la que se encolaron envíos, esperando el turno de cada mensaje.
  - En el hosting (PHP-FPM / LiteSpeed) corre en la misma petición después de entregar la página (el usuario no espera).
  - Con `php artisan serve` lanza en segundo plano `php artisan envios:procesar`.
  - Candado en caché (latido de 90 s) para que no haya dos procesadores; corridas de hasta 25 min.
  - Respaldo: cualquier visita revisa (cada 30 s como máximo) si hay envíos atrasados y los reanuda.
- Si el servidor tiene cron con `schedule:run`, `envios:procesar` corre también cada minuto como refuerzo (opcional).
- Si hay envíos atrasados más de 3 minutos, la ficha del evento y *Administración → WhatsApp* lo avisan.

## 8. Pendientes y decisiones abiertas

- [ ] **Logos reales** de la empresa (hoy siguen los del proyecto anterior).
- [ ] **Nombre y sucursales reales**: hoy "Academia Sistegua" con tres sedes de ejemplo.
- [x] **Correo real**: Gmail `pruebas.sistemas.14@gmail.com`, configurado en *Administración → Correo de envío*
      (tabla `configuracion_correo`; si está vacía se usan los `MAIL_*` del `.env`). Con envíos grandes,
      `QUEUE_CONNECTION=database` y `php artisan queue:work` (tras cambiar el correo: `php artisan queue:restart`).
- [ ] `APP_URL` debe ser la dirección pública para que funcionen los enlaces de los correos.
- [ ] Publicar en un servidor con PHP 8.2 + MySQL.
- [ ] Colores: se heredó la paleta azul marino + dorado; ajustar a la identidad de la empresa.

## 9. Convenciones del código

- Todo el texto de la interfaz en español; validaciones en español (`lang/es`).
- Rutas con segmento de tipo: `/contactos/clientes`, `/eventos/capacitaciones`.
- Confirmaciones de acciones delicadas: `<form data-confirmar="…">`.
- **Sedes, clientes, grupos, usuarios y plantillas de correo no se eliminan**: se desactivan
  (`PATCH …/estado`, permiso `modulo.desactivar`). Lo inactivo conserva su historial, pero no recibe
  invitaciones ni se ofrece en los formularios; los listados muestran por defecto solo lo activo.
- **Capacitaciones con invitaciones enviadas no se eliminan** (`Evento::tieneInvitacionesEnviadas()`):
  se **anulan** o se **posponen**, y en ambos casos se avisa siempre por correo (también al cambiar
  fecha, hora o lugar desde Editar). En pantalla se dice "anulada"; en correos y páginas públicas, "cancelada".
- JS propio mínimo en `public/assets/js/app.js`; CSS propio en `public/assets/css/app.css`.
- Pruebas en `tests/Feature/`.
