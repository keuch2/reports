<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\CuentaPublicitariaRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\UsuarioRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\PasswordPolicyService;

final class ClienteController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ClienteRepository::class);

        return Response::html($view->render('admin/clientes_lista', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Clientes',
            'clientes' => $repo->listarActivos(),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    public function mostrarNuevo(Request $request): Response
    {
        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/clientes_nuevo', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Nuevo cliente',
        ]));
    }

    public function crear(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);

        $nombre = trim((string) $request->input('nombre_comercial', ''));
        $correo = trim((string) $request->input('correo_contacto', ''));
        $contacto = trim((string) $request->input('contacto_principal', ''));
        $telefono = trim((string) $request->input('telefono', ''));
        $usuarioCorreo = trim((string) $request->input('usuario_correo', ''));
        $usuarioNombre = trim((string) $request->input('usuario_nombre', ''));
        $usuarioPassword = (string) $request->input('usuario_password', '');

        if ($nombre === '' || $usuarioCorreo === '' || $usuarioPassword === '' || $usuarioNombre === '') {
            $session->flash('error', 'Faltan campos requeridos.');

            return Response::redirect('/admin/clientes/nuevo');
        }
        $erroresPwd = $this->container->get(PasswordPolicyService::class)->validar($usuarioPassword);
        if ($erroresPwd !== []) {
            $session->flash('error', 'Contraseña inválida: ' . implode(' ', $erroresPwd));

            return Response::redirect('/admin/clientes/nuevo');
        }

        $repo = $this->container->get(ClienteRepository::class);
        $clienteId = $repo->crear($nombre, $correo !== '' ? $correo : null, $contacto !== '' ? $contacto : null, $telefono !== '' ? $telefono : null);

        $hash = password_hash($usuarioPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
        $repo->crearUsuarioPrimario($clienteId, $usuarioCorreo, $hash, $usuarioNombre);

        $this->container->get(AuditService::class)->registrar(
            'cliente.creado', $admin, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['nombre' => $nombre, 'usuario_correo' => $usuarioCorreo]
        );

        $session->flash('success', "Cliente {$nombre} creado con usuario {$usuarioCorreo}.");

        return Response::redirect('/admin/clientes/' . $clienteId);
    }

    /**
     * Detalle del cliente: datos + campañas asignadas + UI para asignar/desasignar.
     */
    public function detalle(Request $request): Response
    {
        $id = (int) ($request->attributes['id'] ?? 0);
        $repo = $this->container->get(ClienteRepository::class);
        $cliente = $repo->buscarPorId($id);
        if ($cliente === null) {
            return Response::html('<h1>404</h1>', 404);
        }

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);
        $entidadesRepo = $this->container->get(EntidadesMetaRepository::class);

        // Mostrar todas las cuentas con sus campañas para que el admin pueda elegir.
        $cuentas = $cuentasRepo->listarTodas();
        $cuentasConCampanias = [];
        foreach ($cuentas as $cuenta) {
            $cuentasConCampanias[] = [
                'cuenta' => $cuenta,
                'campanias' => $entidadesRepo->campaniasConSnapshotsDeCuenta((int) $cuenta['id']),
            ];
        }

        return Response::html($view->render('admin/clientes_detalle', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Cliente · ' . $cliente['nombre_comercial'],
            'cliente' => $cliente,
            'usuario_primario' => $repo->buscarUsuarioPrimario($id),
            'campanias_asignadas' => $repo->campaniasAsignadas($id),
            'ids_asignadas' => $repo->idsCampaniasAsignadas($id),
            'cuentas_con_campanias' => $cuentasConCampanias,
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    /**
     * Recibe el listado completo de campaña IDs a asignar al cliente y reemplaza el set.
     * Si el admin desmarca una campaña, queda desasignada.
     */
    public function asignarCampanias(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ClienteRepository::class);

        if ($clienteId <= 0 || $repo->buscarPorId($clienteId) === null) {
            return Response::redirect('/admin/clientes');
        }

        $ids = array_values(array_unique(array_map(
            'intval',
            (array) ($request->post['campanias'] ?? [])
        )));
        $ids = array_filter($ids, static fn (int $id) => $id > 0);

        $repo->reemplazarCampaniasAsignadas($clienteId, $ids);

        $this->container->get(AuditService::class)->registrar(
            'cliente.campanias_asignadas', $admin, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['cantidad' => count($ids), 'campania_ids' => array_values($ids)]
        );

        $session->flash('success', count($ids) . ' campaña(s) asignada(s) al cliente.');

        return Response::redirect('/admin/clientes/' . $clienteId);
    }

    /**
     * Edita los datos comerciales del cliente (nombre, contacto, teléfono, correo).
     */
    public function actualizarDatos(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ClienteRepository::class);

        $cliente = $repo->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::redirect('/admin/clientes');
        }

        $nombre = trim((string) $request->input('nombre_comercial', ''));
        $correo = trim((string) $request->input('correo_contacto', ''));
        $contacto = trim((string) $request->input('contacto_principal', ''));
        $telefono = trim((string) $request->input('telefono', ''));

        if ($nombre === '') {
            $session->flash('error', 'El nombre comercial es obligatorio.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }

        $repo->actualizar(
            $clienteId,
            $nombre,
            $correo !== '' ? $correo : null,
            $contacto !== '' ? $contacto : null,
            $telefono !== '' ? $telefono : null,
        );

        $this->container->get(AuditService::class)->registrar(
            'cliente.actualizado', $admin, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId,
            ['nombre' => $nombre, 'correo' => $correo, 'telefono' => $telefono]
        );

        $session->flash('success', 'Datos del cliente actualizados.');
        return Response::redirect('/admin/clientes/' . $clienteId);
    }

    /**
     * Edita el correo y nombre del usuario primario del cliente.
     */
    public function actualizarUsuario(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ClienteRepository::class);

        if ($repo->buscarPorId($clienteId) === null) {
            return Response::redirect('/admin/clientes');
        }
        $usuario = $repo->buscarUsuarioPrimario($clienteId);
        if ($usuario === null) {
            $session->flash('error', 'El cliente no tiene un usuario asignado.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }

        $correo = trim((string) $request->input('usuario_correo', ''));
        $nombre = trim((string) $request->input('usuario_nombre', ''));

        if ($correo === '' || $nombre === '') {
            $session->flash('error', 'Correo y nombre del usuario son obligatorios.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $session->flash('error', 'El correo del usuario no es válido.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }
        $usuariosRepo = $this->container->get(UsuarioRepository::class);
        if ($usuariosRepo->correoExiste($correo, (int) $usuario['id'])) {
            $session->flash('error', 'Ya existe otro usuario con ese correo.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }

        $repo->actualizarUsuarioPrimario($clienteId, $correo, $nombre);

        $this->container->get(AuditService::class)->registrar(
            'cliente.usuario_actualizado', $admin, $request->ip, $request->userAgent,
            'usuario', (string) $usuario['id'],
            ['cliente_id' => $clienteId, 'correo_nuevo' => $correo]
        );

        $session->flash('success', 'Usuario del cliente actualizado.');
        return Response::redirect('/admin/clientes/' . $clienteId);
    }

    /**
     * Cambia la contraseña del usuario primario del cliente.
     */
    public function cambiarPassword(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ClienteRepository::class);

        $usuario = $repo->buscarUsuarioPrimario($clienteId);
        if ($usuario === null) {
            $session->flash('error', 'El cliente no tiene un usuario asignado.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }

        $nueva = (string) $request->input('password_nueva', '');
        $confirm = (string) $request->input('password_confirm', '');

        if ($nueva !== $confirm) {
            $session->flash('error', 'Las contraseñas no coinciden.');
            return Response::redirect('/admin/clientes/' . $clienteId);
        }
        $errores = $this->container->get(PasswordPolicyService::class)->validar($nueva);
        if ($errores !== []) {
            $session->flash('error', 'Contraseña inválida: ' . implode(' ', $errores));
            return Response::redirect('/admin/clientes/' . $clienteId);
        }

        $hash = password_hash($nueva, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
        $repo->actualizarPasswordUsuarioPrimario($clienteId, $hash);

        $this->container->get(AuditService::class)->registrar(
            'cliente.password_cambiado', $admin, $request->ip, $request->userAgent,
            'usuario', (string) $usuario['id'], ['cliente_id' => $clienteId]
        );

        $session->flash('success', 'Contraseña del cliente actualizada.');
        return Response::redirect('/admin/clientes/' . $clienteId);
    }
}
