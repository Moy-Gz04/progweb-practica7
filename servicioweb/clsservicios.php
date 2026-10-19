<?php

/*
 * En PHP 8.1+ mysqli lanza EXCEPCIONES cuando falla la conexion o la
 * consulta. Como este servicio SOAP debe responder SIEMPRE con un XML
 * valido, apagamos ese modo y detectamos los fallos con if().
 */
mysqli_report(MYSQLI_REPORT_OFF);

class clsservicios
{
    /* ----------------------------------------------------------
       Utilidades internas (no son operaciones SOAP: son privadas)
       ---------------------------------------------------------- */

    // Abre la conexion con los datos de config.php. Regresa false si falla.
    private function conectar(&$error)
    {
        $cfg  = require __DIR__ . '/config.php';
        $conn = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['bd']);

        if (!$conn)
        {
            $error = 'Error de conexion: ' . mysqli_connect_error();
            return false;
        }

        mysqli_set_charset($conn, 'utf8mb4');
        return $conn;
    }

    // Ejecuta una consulta preparada (CALL o SELECT) y regresa sus renglones.
    // En caso de error regresa un renglon con BAN = 0 y el MENSAJE.
    private function ejecutar($sql, $tipos = '', $params = array())
    {
        try
        {
            $error = '';
            $conn  = $this->conectar($error);

            if (!$conn)
            {
                return array(array('BAN' => '0', 'MENSAJE' => $error));
            }

            $st = mysqli_prepare($conn, $sql);

            if (!$st)
            {
                $msg = mysqli_error($conn);
                mysqli_close($conn);
                return array(array('BAN' => '0', 'MENSAJE' => 'Error al preparar la consulta: ' . $msg));
            }

            if ($tipos !== '')
            {
                mysqli_stmt_bind_param($st, $tipos, ...$params);
            }

            if (!mysqli_stmt_execute($st))
            {
                $msg = mysqli_stmt_error($st);
                mysqli_stmt_close($st);
                mysqli_close($conn);
                return array(array('BAN' => '0', 'MENSAJE' => 'Error en la consulta: ' . $msg));
            }

            $filas = array();
            $res   = mysqli_stmt_get_result($st);

            if ($res instanceof mysqli_result)
            {
                while ($fila = mysqli_fetch_assoc($res))
                {
                    $filas[] = $fila;
                }
                mysqli_free_result($res);
            }

            mysqli_stmt_close($st);
            mysqli_close($conn);

            return $filas;
        }
        catch (Throwable $e)
        {
            return array(array('BAN' => '0', 'MENSAJE' => 'Error del servicio: ' . $e->getMessage()));
        }
    }

    // Normaliza el renglon (ban, mensaje, cve) que regresan los SP del CRUD.
    private function respuestaSp($filas)
    {
        if (isset($filas[0]['BAN']))
        {
            return $filas;     // ya viene con error de conexion / consulta
        }

        if (!isset($filas[0]['ban']))
        {
            return array(array('BAN' => '0', 'MENSAJE' => 'El procedimiento no regreso resultado.', 'CVE' => '0'));
        }

        return array(array(
            'BAN'     => (string)$filas[0]['ban'],
            'MENSAJE' => $filas[0]['mensaje'],
            'CVE'     => (string)$filas[0]['cve']
        ));
    }

    /* ----------------------------------------------------------
       Operaciones SOAP
       ---------------------------------------------------------- */

    // Metodo de prueba (no toca la base de datos)
    public function nombre($nom)
    {
        return 'Bienvenido ' . $nom . ', estas usando el servicio web';
    }

    // Login: CALL sp_Acceso
    public function sp_Acceso($usu, $pwd)
    {
        $filas = $this->ejecutar('CALL sp_Acceso(?,?)', 'ss', array($usu, $pwd));

        if (isset($filas[0]['BAN']))
        {
            return $filas;
        }

        if (isset($filas[0]['usu_ban']) && $filas[0]['usu_ban'] == '1')
        {
            return array(array(
                'BAN' => '1',
                'CVE' => $filas[0]['usu_cve_usuario'],
                'NOM' => $filas[0]['usu_nombre'],
                'USU' => $filas[0]['usu_usuario'],
                'ROL' => $filas[0]['rol_nombre']
            ));
        }

        return array(array('BAN' => '0', 'MENSAJE' => 'Usuario o contraseña incorrectos.'));
    }

    // Alta de usuario: CALL sp_InsUsuario
    public function sp_InsUsuario($nom, $pat, $mat, $tel, $cor, $usu, $pwd, $rol, $pais, $lon, $lat)
    {
        $filas = $this->ejecutar(
            'CALL sp_InsUsuario(?,?,?,?,?,?,?,?,?,?,?)',
            'sssssssisss',
            array($nom, $pat, $mat, $tel, $cor, $usu, $pwd, (int)$rol, $pais, (string)$lon, (string)$lat)
        );

        return $this->respuestaSp($filas);
    }

    // Modificacion de usuario: CALL sp_UpdUsuario (pwd vacia = no cambiarla)
    public function sp_UpdUsuario($cve, $nom, $pat, $mat, $tel, $cor, $usu, $pwd, $rol, $pais, $lon, $lat)
    {
        $filas = $this->ejecutar(
            'CALL sp_UpdUsuario(?,?,?,?,?,?,?,?,?,?,?,?)',
            'isssssssisss',
            array((int)$cve, $nom, $pat, $mat, $tel, $cor, $usu, $pwd, (int)$rol, $pais, (string)$lon, (string)$lat)
        );

        return $this->respuestaSp($filas);
    }

    // Baja de usuario: CALL sp_DelUsuario
    public function sp_DelUsuario($cve)
    {
        $filas = $this->ejecutar('CALL sp_DelUsuario(?)', 'i', array((int)$cve));

        return $this->respuestaSp($filas);
    }

    // Reporte de usuarios: SELECT sobre la vista vwrptusuarios
    public function vwRptUsuarios()
    {
        return $this->ejecutar('SELECT * FROM vwrptusuarios ORDER BY cve');
    }

    // Catalogo de roles (para el combo del formulario)
    public function rptRoles()
    {
        return $this->ejecutar('SELECT rol_cve_rol AS cve, rol_nombre AS nombre FROM rol ORDER BY rol_cve_rol');
    }
}
