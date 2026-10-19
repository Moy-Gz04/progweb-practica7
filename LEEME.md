# Práctica Integradora — Programación Web

CRUD de usuarios con **PHP + MySQL + SOAP/XML + REST/JSON + Bootstrap 5 + reporte PDF**.

## Estructura

```
practica5/
├── sql/bd_integradora.sql        Script completo: tablas, vista y procedimientos
├── servicioweb/                  Servicio SOAP (PHP)
│   ├── servicioweb.php           Publica la clase como servicio
│   ├── clsservicios.php          Métodos: sp_Acceso, sp_InsUsuario, sp_UpdUsuario,
│   │                             sp_DelUsuario, vwRptUsuarios, rptRoles
│   └── config.php                Datos de conexión a MySQL (local / RDS)
├── apiweb/                       APIs REST consumidas por el servidor
│   ├── consultaPais.php          REST Countries (la API Key vive en Apache)
│   ├── testApiKey.php            Comprueba que PHP ve la API Key
│   └── consultaClima.php         Open-Meteo (lat/lon -> clima actual)
├── sitioweb/                     Aplicación web (Bootstrap 5)
│   ├── login.php / index.php     Acceso (usa sp_Acceso por SOAP)
│   ├── directorio.php            CRUD con modales, validaciones y confirmaciones
│   ├── ajax.php                  Recibe el CRUD del navegador y llama al SOAP
│   ├── reportePdf.php            Vista imprimible -> Guardar como PDF
│   ├── soap.php                  Cliente SOAP y utilidades
│   └── assets/                   app.js, mapa.js, estilo.css
│       ├── futurista.css         Tema futurista (se carga solo si la cookie tema != claro)
│       ├── vendor/               d3, topojson y mapamundi (locales, sin CDN)
│       ├── fonts/                Sora, Bricolage Grotesque e Instrument Sans (locales)
│       └── flags/                Banderas SVG de los 12 países de la lista
└── pruebas/Practica-Integradora.postman_collection.json   13 peticiones con tests
```

## Puesta en marcha en localhost (XAMPP)

1. **Base de datos.** Ejecutar `sql/bd_integradora.sql` en MySQL Workbench con un usuario
   administrador (root). Es re-ejecutable: si `usuario` ya existe (Práctica 5) solo le agrega
   `usu_pais`, `usu_longitud` y `usu_latitud`, sin borrar a nadie.
2. **Archivos.** Copiar la carpeta `practica5` a `C:\xampp\htdocs\progweb\practica5`.
3. **API Key de REST Countries** (nunca en el código). En `C:\xampp\apache\conf\extra\httpd-xampp.conf`,
   dentro de `<IfModule env_module>`:
   ```apache
   # API KEY PARA REST COUNTRIES
   SetEnv RESTCOUNTRIES_API_KEY "tu_api_key"
   ```
   Reiniciar Apache. En `php.ini` debe estar activa `extension=curl` (y `soap`, `mysqli`).
4. **Conexión a MySQL.** `servicioweb/config.php` apunta a `localhost` con el usuario `prosoft`
   (el de la Práctica 5). Para RDS se cambia `host` (o se usan las variables `DB_HOST`, `DB_USER`,
   `DB_PASS`, `DB_NAME`).

URLs: 
- Sitio: `http://localhost/progweb/practica5/sitioweb/`
- Servicio SOAP: `http://localhost/progweb/practica5/servicioweb/servicioweb.php`
- Prueba de la llave: `http://localhost/progweb/practica5/apiweb/testApiKey.php`
- País: `http://localhost/progweb/practica5/apiweb/consultaPais.php?pais=Mexico`
- Clima: `http://localhost/progweb/practica5/apiweb/consultaClima.php?lat=19.432608&lon=-99.133209`

Usuario de prueba: `jgomez` / `prosoft2026` (ADMINISTRADOR).

## Despliegue en AWS (EC2 + RDS)

- **Base:** `sql/00_crear_bd_aws_admin.sql` (una vez, con el usuario admin del RDS: crea `bd_integradora` y da permisos a `prosoft`) y luego `sql/bd_integradora_aws.sql`. La base nueva evita tocar `bd_prosoft` de las prácticas anteriores.
- **Servidor:** copiar `practica5` a `C:\xampp\htdocs\progweb\practica5`, poner en `servicioweb/config.php` el endpoint del RDS y `bd_integradora`, agregar la API Key con `SetEnv` en `httpd-xampp.conf`, activar `curl`, `soap`, `mysqli` y `mbstring` en `php.ini`, abrir el puerto 80 (firewall de Windows y Security Group) y reiniciar Apache.
- La IP pública de la EC2 cambia cada vez que se reinicia el laboratorio; la colección de Postman usa la variable `base_url` para ajustarla.

## Flujo de integración (explicación técnica)

```
NAVEGADOR (Bootstrap 5, modales, fetch)
   │  1) formulario validado + confirmación  ->  POST ajax.php (token CSRF)
   ▼
sitioweb/ajax.php  ── valida otra vez ──►  SoapClient
   │  2) petición XML (SOAP) con sp_InsUsuario / sp_UpdUsuario / sp_DelUsuario
   ▼
servicioweb/servicioweb.php (SoapServer) -> clsservicios.php
   │  3) consulta preparada: CALL sp_...(?,?,...)  /  SELECT * FROM vwrptusuarios
   ▼
MySQL  (tabla usuario ─ rol, procedimientos con validaciones y banderas ban/mensaje)
```

- El reporte (`directorio.php`) no consulta MySQL: llama a `vwRptUsuarios()` por SOAP.
- **Temas:** el sitio trae dos aspectos que se cambian con el botón de la barra (se recuerda en la cookie `tema`). *Futurista* (por defecto): globo 3D que gira y se arrastra; clic en un pin lo hace girar hasta ahí. *Claro* (Atlas): mapamundi plano.
- **Mapa:** cada usuario es un pin en su latitud/longitud (los que comparten ubicación se agrupan con un número). Clic en un pin filtra el directorio; al pasar sobre una fila se resalta su pin. Todo el mapa se dibuja en el navegador con archivos locales, así que funciona igual en AWS.
- **Botón País:** el navegador pide `apiweb/consultaPais.php`; PHP agrega el encabezado
  `Authorization: Bearer <API Key>` (leída de `getenv`) y consulta REST Countries. La llave
  nunca llega al navegador. La búsqueda se hace con el nombre en inglés del país
  (en español la API confunde "Estados Unidos" con "Estados Unidos Mexicanos").
- **Botón Clima:** el navegador pide `apiweb/consultaClima.php?lat=..&lon=..` con las
  coordenadas del usuario; PHP consulta Open-Meteo y devuelve temperatura, humedad y condición en español.
- **Botón PDF:** abre `reportePdf.php` en otra pestaña (encabezado, datos y pie, sin acciones);
  el botón “Imprimir / Guardar como PDF” genera el archivo.

## Procedimientos almacenados

| Procedimiento | Qué valida | Respuesta |
|---|---|---|
| `sp_InsUsuario` | nombre, paterno, teléfono, correo, usuario (4–15, único), contraseña ≥ 6, rol existente, país, latitud −90..90, longitud −180..180 | `ban`, `mensaje`, `cve` |
| `sp_UpdUsuario` | lo anterior + que exista, usuario único entre los demás, contraseña vacía = conservar; no deja el sistema sin administrador | `ban`, `mensaje`, `cve` |
| `sp_DelUsuario` | que exista y que no sea el único administrador | `ban`, `mensaje`, `cve` |
| `sp_Acceso` | usuario y contraseña | datos del usuario o `0` |

`ban = 1` correcto, `ban = 0` error (el texto va en `mensaje`). El SOAP los devuelve como `BAN`, `MENSAJE`, `CVE`.

## Pruebas

- **Postman:** importar `pruebas/Practica-Integradora.postman_collection.json` y ejecutar en orden
  (la variable `base_url` cambia entre localhost y AWS). Con Newman:
  `newman run pruebas/Practica-Integradora.postman_collection.json`
- **Web:** registrar, modificar, eliminar, botón País, botón Clima y botón PDF.
- **MySQL:** `SELECT * FROM vwrptusuarios;` antes y después de cada operación.

## Notas de seguridad

- Las contraseñas de `usuario` se guardan en texto plano porque así está definida la tabla desde la
  Práctica 5 (`sp_Acceso` las compara directo). Para producción irían con hash.
- Las consultas del servicio usan sentencias preparadas (sin concatenar SQL).
- El CRUD exige sesión y token CSRF; no se puede eliminar al usuario con la sesión abierta.
