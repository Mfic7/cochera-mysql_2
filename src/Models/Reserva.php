<?php

namespace App\Models;

use App\Database;

class Reserva
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, e.codigo AS espacio_codigo, e.numero AS espacio_numero
             FROM reservas r JOIN espacios e ON e.id = r.espacio_id WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByCodigo(string $codigo): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, e.codigo AS espacio_codigo FROM reservas r JOIN espacios e ON e.id = r.espacio_id WHERE r.codigo = :codigo'
        );
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Búsqueda usada por el "perfil del cliente": localizar su reserva sin necesidad
     * de cuenta, validando código + celular como par de identificación.
     */
    public static function findByCodigoAndCelular(string $codigo, string $celular): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, e.codigo AS espacio_codigo FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             WHERE r.codigo = :codigo AND r.cliente_celular = :celular'
        );
        $stmt->execute(['codigo' => $codigo, 'celular' => $celular]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function generarCodigo(\PDO $pdo): string
    {
        do {
            $codigo = 'RES-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $pdo->prepare('SELECT 1 FROM reservas WHERE codigo = :codigo');
            $stmt->execute(['codigo' => $codigo]);
        } while ($stmt->fetch());

        return $codigo;
    }

    public static function actualizarEstado(int $id, string $estado, ?string $nota = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE reservas SET estado = :estado WHERE id = :id');
        $stmt->execute(['estado' => $estado, 'id' => $id]);
    }

    public static function listar(array $filtros = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::connection();
        $where = [];
        $params = [];

        if (!empty($filtros['fecha'])) {
            $where[] = 'DATE(r.fecha_hora_inicio) = :fecha';
            $params['fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'r.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $pdo->prepare(
            "SELECT r.*, e.codigo AS espacio_codigo FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             $whereSql ORDER BY r.fecha_hora_inicio DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS n FROM reservas r $whereSql");
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['n'];

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function delDia(?string $fecha = null): array
    {
        $fecha = $fecha ?? date('Y-m-d');
        $stmt = Database::connection()->prepare(
            "SELECT r.*, e.codigo AS espacio_codigo FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             WHERE DATE(r.fecha_hora_inicio) = :fecha AND r.estado != 'vencida'
             ORDER BY r.fecha_hora_inicio ASC"
        );
        $stmt->execute(['fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    public static function recientes(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.*, e.codigo AS espacio_codigo FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             ORDER BY r.created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}