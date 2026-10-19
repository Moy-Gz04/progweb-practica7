-- ===============================================================
-- PRACTICA INTEGRADORA - PROGRAMACION WEB
-- CRUD de usuarios: tablas, vista y procedimientos almacenados
-- Base de datos: bd_integradora   (version para AWS-RDS; no toca bd_prosoft)
--
-- El script es RE-EJECUTABLE: si la tabla usuario ya existe (de la
-- Practica 5) solo le agrega pais, longitud y latitud, sin borrar
-- los usuarios que ya tiene.
--
-- Banderas que devuelven los procedimientos:
--   ban = 1  -> operacion correcta
--   ban = 0  -> validacion fallida o error (ver columna mensaje)
-- ===============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS bd_integradora
    CHARACTER SET utf8mb4;

USE bd_integradora;


-- ===============================================================
-- 1. TABLA ROL
-- ===============================================================
CREATE TABLE IF NOT EXISTS rol
(
    rol_cve_rol         INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    rol_nombre          VARCHAR(30)  NOT NULL,
    rol_descripcion     VARCHAR(150) NOT NULL
);

INSERT INTO rol (rol_nombre, rol_descripcion)
SELECT 'ADMINISTRADOR', 'Responsable general del sistema y de la tienda de instrumentos'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_nombre = 'ADMINISTRADOR');

INSERT INTO rol (rol_nombre, rol_descripcion)
SELECT 'VENTAS', 'Atencion y venta de instrumentos y equipo de audio'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_nombre = 'VENTAS');

INSERT INTO rol (rol_nombre, rol_descripcion)
SELECT 'ALMACEN', 'Recepcion, acomodo y control de existencias de instrumentos'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_nombre = 'ALMACEN');

INSERT INTO rol (rol_nombre, rol_descripcion)
SELECT 'CLIENTE', 'Usuario cliente sin permisos administrativos'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_nombre = 'CLIENTE');


-- ===============================================================
-- 2. TABLA USUARIO (relacionada con rol) + pais, longitud, latitud
-- ===============================================================
CREATE TABLE IF NOT EXISTS usuario
(
    usu_cve_usuario         INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    usu_nombre              VARCHAR(30) NOT NULL,
    usu_apellido_paterno    VARCHAR(30) NOT NULL,
    usu_apellido_materno    VARCHAR(30) NOT NULL,
    usu_telefono            VARCHAR(15),
    usu_correo              VARCHAR(50),
    usu_fecha_registro      DATETIME NOT NULL,
    usu_usuario             VARCHAR(15) NOT NULL,
    usu_password            VARCHAR(15) NOT NULL,
    rol_cve_rol             INT NOT NULL,
    usu_pais                VARCHAR(40)   NOT NULL DEFAULT 'México',
    usu_longitud            DECIMAL(10,6) NOT NULL DEFAULT -99.133209,
    usu_latitud             DECIMAL(10,6) NOT NULL DEFAULT 19.432608,

    FOREIGN KEY (rol_cve_rol) REFERENCES rol(rol_cve_rol)
);

-- Si la tabla ya existia (Practica 5) se agregan las columnas nuevas.
-- Los usuarios anteriores quedan con México / Ciudad de México.
DROP PROCEDURE IF EXISTS sp_AgregaColumna;

DELIMITER $$

CREATE PROCEDURE sp_AgregaColumna
(
    IN p_tabla      VARCHAR(64),
    IN p_columna    VARCHAR(64),
    IN p_definicion VARCHAR(200)
)
BEGIN
    IF NOT EXISTS
    (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = p_tabla
          AND column_name  = p_columna
    )
    THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_tabla,
                          ' ADD COLUMN ', p_columna, ' ', p_definicion);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sp_AgregaColumna('usuario', 'usu_pais',
                      'VARCHAR(40) NOT NULL DEFAULT ''México''');
CALL sp_AgregaColumna('usuario', 'usu_longitud',
                      'DECIMAL(10,6) NOT NULL DEFAULT -99.133209');
CALL sp_AgregaColumna('usuario', 'usu_latitud',
                      'DECIMAL(10,6) NOT NULL DEFAULT 19.432608');

DROP PROCEDURE IF EXISTS sp_AgregaColumna;


-- ===============================================================
-- 3. DATOS DE EJEMPLO (no se repiten si ya existen)
--    Un usuario por cada pais de la tabla de ejemplo de la practica
-- ===============================================================
INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Juan Moises', 'Gomez', 'Aispuro', '771-333-1212',
       'jgomezaispuro@gmail.com', NOW(), 'jgomez', 'prosoft2026', 1,
       'México', -99.133209, 19.432608
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'jgomez');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Lucia', 'Hernandez', 'Torres', '51-987-654-32',
       'lucia.hernandez@gmail.com', NOW(), 'lhernandez', 'itp2026', 2,
       'Perú', -77.042793, -12.046374
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'lhernandez');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Carlos', 'Mendoza', 'Ruiz', '57-300-123-45',
       'carlos.mendoza@gmail.com', NOW(), 'cmendoza', 'itp2026', 3,
       'Colombia', -74.072092, 4.710989
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'cmendoza');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Valentina', 'Rojas', 'Paz', '54-11-4567-89',
       'valentina.rojas@gmail.com', NOW(), 'vrojas', 'itp2026', 4,
       'Argentina', -58.381559, -34.603722
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'vrojas');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Diego', 'Alonso', 'Vega', '34-612-345-67',
       'diego.alonso@gmail.com', NOW(), 'dalonso', 'itp2026', 2,
       'España', -3.703790, 40.416775
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'dalonso');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Yuki', 'Tanaka', 'Sato', '81-90-1234-56',
       'yuki.tanaka@gmail.com', NOW(), 'ytanaka', 'itp2026', 4,
       'Japón', 139.691706, 35.689487
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'ytanaka');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Emily', 'Carter', 'Brooks', '1-202-555-0143',
       'emily.carter@gmail.com', NOW(), 'ecarter', 'itp2026', 4,
       'Estados Unidos', -77.036871, 38.907192
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'ecarter');

INSERT INTO usuario
    (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
     usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
     usu_pais, usu_longitud, usu_latitud)
SELECT 'Rafael', 'Souza', 'Lima', '55-61-9876-54',
       'rafael.souza@gmail.com', NOW(), 'rsouza', 'itp2026', 3,
       'Brasil', -47.882500, -15.794200
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = 'rsouza');


-- ===============================================================
-- 4. VISTA PARA EL REPORTE (no incluye la contrasena)
-- ===============================================================
CREATE OR REPLACE VIEW vwrptusuarios AS
SELECT
    u.usu_cve_usuario                                       AS cve,
    CONCAT(u.usu_nombre, ' ',
           u.usu_apellido_paterno, ' ',
           u.usu_apellido_materno)                          AS nombre,
    u.usu_nombre                                            AS nom,
    u.usu_apellido_paterno                                  AS pat,
    u.usu_apellido_materno                                  AS mat,
    u.usu_usuario                                           AS usuario,
    u.usu_correo                                            AS correo,
    u.usu_telefono                                          AS telefono,
    r.rol_cve_rol                                           AS rol_cve,
    r.rol_nombre                                            AS rol,
    u.usu_pais                                              AS pais,
    u.usu_longitud                                          AS longitud,
    u.usu_latitud                                           AS latitud,
    u.usu_fecha_registro                                    AS fecha_registro
FROM usuario u
INNER JOIN rol r ON r.rol_cve_rol = u.rol_cve_rol;


-- ===============================================================
-- 5. PROCEDIMIENTO sp_Acceso (login)
--    ban '1' = acceso correcto + datos | '0' = incorrecto
-- ===============================================================
DROP PROCEDURE IF EXISTS sp_Acceso;

DELIMITER $$

CREATE PROCEDURE sp_Acceso
(
    IN usuario  VARCHAR(15),
    IN password VARCHAR(15)
)
BEGIN
    IF EXISTS
    (
        SELECT 1
        FROM usuario u
        INNER JOIN rol r ON u.rol_cve_rol = r.rol_cve_rol
        WHERE u.usu_usuario  = usuario
          AND u.usu_password = password
    )
    THEN
        SELECT
            '1' AS usu_ban,
            u.usu_cve_usuario AS usu_cve_usuario,
            CONCAT(u.usu_nombre, ' ',
                   u.usu_apellido_paterno, ' ',
                   u.usu_apellido_materno) AS usu_nombre,
            u.usu_usuario AS usu_usuario,
            r.rol_nombre  AS rol_nombre
        FROM usuario u
        INNER JOIN rol r ON u.rol_cve_rol = r.rol_cve_rol
        WHERE u.usu_usuario  = usuario
          AND u.usu_password = password;
    ELSE
        SELECT '0' AS usu_ban;
    END IF;
END $$

DELIMITER ;


-- ===============================================================
-- 6. PROCEDIMIENTO sp_InsUsuario
--    Valida los datos, inserta y regresa: ban, mensaje, cve
-- ===============================================================
DROP PROCEDURE IF EXISTS sp_InsUsuario;

DELIMITER $$

CREATE PROCEDURE sp_InsUsuario
(
    IN p_nombre     VARCHAR(30),
    IN p_paterno    VARCHAR(30),
    IN p_materno    VARCHAR(30),
    IN p_telefono   VARCHAR(15),
    IN p_correo     VARCHAR(50),
    IN p_usuario    VARCHAR(15),
    IN p_password   VARCHAR(15),
    IN p_rol        INT,
    IN p_pais       VARCHAR(40),
    IN p_longitud   DECIMAL(10,6),
    IN p_latitud    DECIMAL(10,6)
)
proc: BEGIN
    DECLARE v_error VARCHAR(200) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 0 AS ban, CONCAT('Error en la base de datos: ', v_error) AS mensaje, 0 AS cve;
    END;

    SET p_nombre   = TRIM(p_nombre);
    SET p_paterno  = TRIM(p_paterno);
    SET p_materno  = TRIM(IFNULL(p_materno, ''));
    SET p_telefono = TRIM(p_telefono);
    SET p_correo   = TRIM(p_correo);
    SET p_usuario  = TRIM(p_usuario);
    SET p_pais     = TRIM(p_pais);

    IF p_nombre IS NULL OR p_nombre = '' THEN
        SELECT 0 AS ban, 'El nombre es obligatorio.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_paterno IS NULL OR p_paterno = '' THEN
        SELECT 0 AS ban, 'El apellido paterno es obligatorio.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_telefono IS NULL OR p_telefono NOT REGEXP '^[0-9 ()+-]{7,15}$' THEN
        SELECT 0 AS ban, 'El teléfono no es válido (7 a 15 dígitos).' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_correo IS NULL OR p_correo NOT REGEXP '^[^@ ]+@[^@ ]+\\.[^@ ]+$' THEN
        SELECT 0 AS ban, 'El correo electrónico no es válido.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_usuario IS NULL OR p_usuario NOT REGEXP '^[A-Za-z0-9_.]{4,15}$' THEN
        SELECT 0 AS ban, 'El usuario debe tener de 4 a 15 caracteres (letras, números, punto o guion bajo).' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_password IS NULL OR CHAR_LENGTH(p_password) < 6 THEN
        SELECT 0 AS ban, 'La contraseña debe tener al menos 6 caracteres.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM rol WHERE rol_cve_rol = p_rol) THEN
        SELECT 0 AS ban, 'El rol seleccionado no existe.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_pais IS NULL OR p_pais = '' THEN
        SELECT 0 AS ban, 'El país es obligatorio.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_latitud IS NULL OR p_latitud < -90 OR p_latitud > 90 THEN
        SELECT 0 AS ban, 'La latitud debe estar entre -90 y 90.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF p_longitud IS NULL OR p_longitud < -180 OR p_longitud > 180 THEN
        SELECT 0 AS ban, 'La longitud debe estar entre -180 y 180.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;
    IF EXISTS (SELECT 1 FROM usuario WHERE usu_usuario = p_usuario) THEN
        SELECT 0 AS ban, 'Ya existe un usuario con ese nombre de usuario.' AS mensaje, 0 AS cve; LEAVE proc;
    END IF;

    START TRANSACTION;

    INSERT INTO usuario
        (usu_nombre, usu_apellido_paterno, usu_apellido_materno, usu_telefono,
         usu_correo, usu_fecha_registro, usu_usuario, usu_password, rol_cve_rol,
         usu_pais, usu_longitud, usu_latitud)
    VALUES
        (p_nombre, p_paterno, p_materno, p_telefono,
         p_correo, NOW(), p_usuario, p_password, p_rol,
         p_pais, p_longitud, p_latitud);

    SELECT 1 AS ban, 'Usuario registrado correctamente.' AS mensaje, LAST_INSERT_ID() AS cve;

    COMMIT;
END proc $$

DELIMITER ;


-- ===============================================================
-- 7. PROCEDIMIENTO sp_UpdUsuario
--    Si p_password viene vacia se conserva la contrasena actual.
-- ===============================================================
DROP PROCEDURE IF EXISTS sp_UpdUsuario;

DELIMITER $$

CREATE PROCEDURE sp_UpdUsuario
(
    IN p_cve        INT,
    IN p_nombre     VARCHAR(30),
    IN p_paterno    VARCHAR(30),
    IN p_materno    VARCHAR(30),
    IN p_telefono   VARCHAR(15),
    IN p_correo     VARCHAR(50),
    IN p_usuario    VARCHAR(15),
    IN p_password   VARCHAR(15),
    IN p_rol        INT,
    IN p_pais       VARCHAR(40),
    IN p_longitud   DECIMAL(10,6),
    IN p_latitud    DECIMAL(10,6)
)
proc: BEGIN
    DECLARE v_error VARCHAR(200) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 0 AS ban, CONCAT('Error en la base de datos: ', v_error) AS mensaje, p_cve AS cve;
    END;

    SET p_nombre   = TRIM(p_nombre);
    SET p_paterno  = TRIM(p_paterno);
    SET p_materno  = TRIM(IFNULL(p_materno, ''));
    SET p_telefono = TRIM(p_telefono);
    SET p_correo   = TRIM(p_correo);
    SET p_usuario  = TRIM(p_usuario);
    SET p_pais     = TRIM(p_pais);
    SET p_password = IFNULL(p_password, '');

    IF NOT EXISTS (SELECT 1 FROM usuario WHERE usu_cve_usuario = p_cve) THEN
        SELECT 0 AS ban, 'El usuario que intenta modificar no existe.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_nombre IS NULL OR p_nombre = '' THEN
        SELECT 0 AS ban, 'El nombre es obligatorio.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_paterno IS NULL OR p_paterno = '' THEN
        SELECT 0 AS ban, 'El apellido paterno es obligatorio.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_telefono IS NULL OR p_telefono NOT REGEXP '^[0-9 ()+-]{7,15}$' THEN
        SELECT 0 AS ban, 'El teléfono no es válido (7 a 15 dígitos).' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_correo IS NULL OR p_correo NOT REGEXP '^[^@ ]+@[^@ ]+\\.[^@ ]+$' THEN
        SELECT 0 AS ban, 'El correo electrónico no es válido.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_usuario IS NULL OR p_usuario NOT REGEXP '^[A-Za-z0-9_.]{4,15}$' THEN
        SELECT 0 AS ban, 'El usuario debe tener de 4 a 15 caracteres (letras, números, punto o guion bajo).' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_password <> '' AND CHAR_LENGTH(p_password) < 6 THEN
        SELECT 0 AS ban, 'La contraseña debe tener al menos 6 caracteres.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM rol WHERE rol_cve_rol = p_rol) THEN
        SELECT 0 AS ban, 'El rol seleccionado no existe.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_pais IS NULL OR p_pais = '' THEN
        SELECT 0 AS ban, 'El país es obligatorio.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_latitud IS NULL OR p_latitud < -90 OR p_latitud > 90 THEN
        SELECT 0 AS ban, 'La latitud debe estar entre -90 y 90.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF p_longitud IS NULL OR p_longitud < -180 OR p_longitud > 180 THEN
        SELECT 0 AS ban, 'La longitud debe estar entre -180 y 180.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF EXISTS (SELECT 1 FROM usuario
               WHERE usu_usuario = p_usuario AND usu_cve_usuario <> p_cve) THEN
        SELECT 0 AS ban, 'Ya existe otro usuario con ese nombre de usuario.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    -- No dejar el sistema sin ningun administrador
    IF p_rol <> 1
       AND (SELECT rol_cve_rol FROM usuario WHERE usu_cve_usuario = p_cve) = 1
       AND (SELECT COUNT(*) FROM usuario WHERE rol_cve_rol = 1) = 1 THEN
        SELECT 0 AS ban, 'No se puede quitar el rol al único administrador.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;

    START TRANSACTION;

    UPDATE usuario
    SET usu_nombre           = p_nombre,
        usu_apellido_paterno = p_paterno,
        usu_apellido_materno = p_materno,
        usu_telefono         = p_telefono,
        usu_correo           = p_correo,
        usu_usuario          = p_usuario,
        usu_password         = IF(p_password = '', usu_password, p_password),
        rol_cve_rol          = p_rol,
        usu_pais             = p_pais,
        usu_longitud         = p_longitud,
        usu_latitud          = p_latitud
    WHERE usu_cve_usuario = p_cve;

    SELECT 1 AS ban, 'Usuario modificado correctamente.' AS mensaje, p_cve AS cve;

    COMMIT;
END proc $$

DELIMITER ;


-- ===============================================================
-- 8. PROCEDIMIENTO sp_DelUsuario
--    No permite borrar un usuario inexistente ni al unico administrador.
-- ===============================================================
DROP PROCEDURE IF EXISTS sp_DelUsuario;

DELIMITER $$

CREATE PROCEDURE sp_DelUsuario
(
    IN p_cve INT
)
proc: BEGIN
    DECLARE v_error VARCHAR(200) DEFAULT NULL;
    DECLARE v_rol   INT DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 0 AS ban, CONCAT('Error en la base de datos: ', v_error) AS mensaje, p_cve AS cve;
    END;

    SELECT rol_cve_rol INTO v_rol
    FROM usuario
    WHERE usu_cve_usuario = p_cve;

    IF v_rol IS NULL THEN
        SELECT 0 AS ban, 'El usuario que intenta eliminar no existe.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;
    IF v_rol = 1 AND (SELECT COUNT(*) FROM usuario WHERE rol_cve_rol = 1) = 1 THEN
        SELECT 0 AS ban, 'No se puede eliminar al único administrador.' AS mensaje, p_cve AS cve; LEAVE proc;
    END IF;

    START TRANSACTION;

    DELETE FROM usuario WHERE usu_cve_usuario = p_cve;

    SELECT 1 AS ban, 'Usuario eliminado correctamente.' AS mensaje, p_cve AS cve;

    COMMIT;
END proc $$

DELIMITER ;


-- ===============================================================
-- 9. USUARIO DE MYSQL PARA EL SERVICIO WEB (solo MySQL local;
--    en AWS-RDS el usuario 'prosoft' ya existe y este bloque se omite)
-- ===============================================================
-- CREATE USER IF NOT EXISTS 'prosoft'@'localhost' IDENTIFIED BY 'bonita_19';
-- (en RDS) GRANT ALL PRIVILEGES ON bd_integradora.* TO 'prosoft'@'%';
-- FLUSH PRIVILEGES;


-- ===============================================================
-- 10. PRUEBAS DE LOS PROCEDIMIENTOS (descomentar para probar)
-- ===============================================================
-- SELECT * FROM vwrptusuarios;
-- CALL sp_InsUsuario('Prueba','Uno','Dos','771-111-2222','prueba@correo.com','prueba1','clave123',4,'México',-99.133209,19.432608);
-- CALL sp_InsUsuario('Prueba','Uno','Dos','771-111-2222','correo-malo','prueba2','clave123',4,'México',-99.133209,19.432608);   -- ban 0
-- CALL sp_UpdUsuario(<cve>,'Prueba','Uno','Dos','771-111-2222','prueba@correo.com','prueba1','',4,'Perú',-77.042793,-12.046374);
-- CALL sp_DelUsuario(<cve>);

-- FIN DEL SCRIPT
