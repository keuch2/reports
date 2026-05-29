<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

/**
 * Política de contraseñas robusta para Fase 3:
 * - Mínimo 12 caracteres.
 * - Al menos una mayúscula, una minúscula, un número y un carácter especial.
 * - Rechazo de contraseñas presentes en lista local de comprometidas.
 *
 * La lista de comprometidas se carga lazy desde `config/passwords-comunes.txt`
 * (una por línea, minúsculas, sin trim). Si el archivo no existe, solo se
 * aplican las reglas de longitud y complejidad.
 */
final class PasswordPolicyService
{
    public const MIN_LENGTH = 12;

    /** @var array<string, true>|null */
    private static ?array $comprometidas = null;
    private static string $listaPath = __DIR__ . '/../../config/passwords-comunes.txt';

    /** @return list<string> errores en lenguaje natural; vacío = válida */
    public function validar(string $password): array
    {
        $errores = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errores[] = 'Debe tener al menos ' . self::MIN_LENGTH . ' caracteres.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errores[] = 'Debe incluir al menos una minúscula.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errores[] = 'Debe incluir al menos una mayúscula.';
        }
        if (!preg_match('/\d/', $password)) {
            $errores[] = 'Debe incluir al menos un número.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errores[] = 'Debe incluir al menos un carácter especial.';
        }
        if ($this->estaComprometida($password)) {
            $errores[] = 'Esta contraseña aparece en listas conocidas de filtraciones. Elegí otra.';
        }

        return $errores;
    }

    private function estaComprometida(string $password): bool
    {
        if (self::$comprometidas === null) {
            self::$comprometidas = [];
            if (is_file(self::$listaPath)) {
                $handle = fopen(self::$listaPath, 'r');
                if ($handle !== false) {
                    while (($line = fgets($handle)) !== false) {
                        $line = mb_strtolower(trim($line));
                        if ($line !== '') {
                            self::$comprometidas[$line] = true;
                        }
                    }
                    fclose($handle);
                }
            }
        }

        return isset(self::$comprometidas[mb_strtolower($password)]);
    }
}
