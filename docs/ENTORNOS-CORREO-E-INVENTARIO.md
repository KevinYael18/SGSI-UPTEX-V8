# SGSI UPTex — Entornos, correo e inventario tecnológico

## 1. Diferencias entre entornos

| Tema | Local sin Docker | Local con Docker | Servidor (red / Internet) |
|---|---|---|---|
| Base de datos | `DB_HOST=127.0.0.1` | `DB_HOST=mysql` (nombre del servicio) | `mysql` o el host del servidor MySQL |
| Correo (`MAIL_HOST`) | `127.0.0.1` (un Mailpit/SMTP en tu equipo) | `mailpit` (contenedor incluido) | SMTP real del proveedor |
| `localhost` | Tu equipo | **El propio contenedor**, no tu equipo | El servidor |
| `APP_URL` | `http://localhost:8000` | `http://localhost:8080` | La dirección real: `http://192.168.x.x:8080` o `https://dominio` |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,localhost:8000` | `localhost:8080` | Todas las direcciones con las que se abre el sistema |

> Una red Wi-Fi **no envía correos**. Laravel necesita un servidor SMTP alcanzable
> desde donde corre (el contenedor `app`).

## 2. Correo

### Desarrollo (Mailpit, incluido en `docker-compose.yml`)
```
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=null
```
Bandeja de prueba: http://localhost:8025

### Producción (SMTP real)
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.proveedor.com
MAIL_PORT=587            # 465 si el proveedor usa SSL implícito
MAIL_ENCRYPTION=tls      # ssl con el puerto 465
MAIL_USERNAME=cuenta@dominio
MAIL_PASSWORD=********   # contraseña de aplicación; nunca la subas a Git
MAIL_FROM_ADDRESS=cuenta@dominio
APP_URL=https://sgsi.dominio.mx
```

### Diagnóstico
```
docker compose exec app php artisan sgsi:correo:diagnostico
docker compose exec app php artisan sgsi:correo:diagnostico --enviar=tu@correo
```
Comprueba, en orden: configuración efectiva (incluida la caché), DNS, puerto TCP,
TLS y autenticación SMTP, y opcionalmente envía un correo real de prueba.

Tras editar `.env`: `docker compose up -d` (el contenedor regenera la caché de configuración al arrancar).

## 3. Inventario tecnológico

* **Edificios → Laboratorios → Computadoras.** Los nombres se configuran desde *Inventario TI*;
  el sistema no trae edificios ni laboratorios precargados.
* **Cada computadora es un activo** (tipo hardware). Por eso sus riesgos, incidentes y
  controles ISO se gestionan en esos módulos seleccionando el activo del equipo.
* **Catálogo de software:** los programas se registran una vez y se vinculan a cada equipo con su
  versión y licencia. Sin información, la licencia queda *pendiente de verificación*
  (nunca se presume ilegal).
* **Importación:** plantilla en *Inventario TI → Importar*. Primero muestra una vista previa;
  los duplicados (inventario, serie o MAC) se **omiten** salvo que elijas «Actualizarlos».
* **Permisos:** reutiliza los del módulo de Activos (`activos.ver`, `activos.crear/editar`, `activos.eliminar`).

## 4. Seguridad de cuentas

* Una cuenta suspendida no puede iniciar sesión, y si ya tenía una sesión abierta, la pierde en
  su siguiente petición (middleware `AsegurarCuentaActiva`). Sus sesiones y tokens se eliminan
  al suspenderla.
* La suspensión, la reactivación (con administrador, fecha y motivo), los inicios de sesión
  fallidos y la recuperación de contraseña quedan en la **bitácora**, que es de solo inserción.

## 5. Ficha técnica inteligente de activos

* En **Activos → Nuevo activo** se elige la clasificación general (Hardware, Software…) y se escribe
  el nombre. El sistema reconoce el tipo (32 tipos: computadora, laptop, servidor, cámara, impresora,
  impresora 3D, equipos de red, PLC, CNC, robots, instrumentos de laboratorio, climatización,
  proyectores, sistemas operativos, antivirus, ofimática, desarrollo…) y muestra solo sus campos.
* Confianza **alta** → se aplica solo; **media** → pide confirmar; **ninguna** → registro general.
  El tipo siempre se puede corregir con «Cambiar». Nunca bloquea el registro.
* Las reglas viven en `app/Support/CatalogoTiposActivo.php` (palabras clave con peso y plantillas de
  campos). El frontend recibe esas mismas reglas por `GET /api/v1/activos/tipos-tecnicos`.
  Para reconocer un nombre nuevo, añade la palabra clave al tipo correspondiente.
* Computadoras, laptops y servidores guardan su información en la misma ficha del Inventario TI
  (software instalado, antivirus, mantenimiento). El resto de tipos usa `activos.caracteristicas`.
* Un activo de software se vincula al catálogo de software: su detalle muestra en qué equipos está
  instalado. Desinstalar un programa conserva el historial (estado «desinstalado»).
* CIA, criticidad, riesgos y permisos funcionan exactamente igual que antes.

### Laboratorios de referencia (opcional)
```
docker compose exec app php artisan sgsi:inventario:laboratorios-referencia --departamento=ID
```
Crea «Edificio B» (Laboratorio 1 y 2) y «Rectoría/Biblioteca» (Laboratorio 4, 5 y 6). Son nombres de
referencia editables desde *Inventario TI*; el comando no duplica registros existentes.
