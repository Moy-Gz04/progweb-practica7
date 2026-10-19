-- Ejecutar UNA VEZ con el usuario admin del RDS dbprogweb (MySQL Workbench).
-- Crea la base de la integradora sin tocar bd_prosoft (Practicas 5, 5.1 y 5.1.1)
-- y le da permisos al usuario prosoft, que es el que usa el servicio web.
CREATE DATABASE IF NOT EXISTS bd_integradora CHARACTER SET utf8mb4;
GRANT ALL PRIVILEGES ON bd_integradora.* TO 'prosoft'@'%';
FLUSH PRIVILEGES;
