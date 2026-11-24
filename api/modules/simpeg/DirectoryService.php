<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';

final class DirectoryService
{
    /** @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    public static function positions(int $page, int $perPage): array
    {
        return self::paginate(
            'SELECT
                CAST(mj.kode_jabatan AS CHAR) AS kode_jabatan,
                mj.nama_jabatan,
                mj.nama_unit_kerja,
                mj.level,
                mj.lingkup,
                mj.group_jabatan,
                mj.kuota,
                CAST(mj.parent_kode_jabatan AS CHAR) AS parent_kode_jabatan,
                CAST(j.id_jab AS CHAR) AS id_jab,
                j.id_peg,
                j.unit_kerja AS kode_unit,
                k.nama_kantor AS nama_unit,
                j.tmt_jabatan,
                j.sampai_tgl,
                j.status_jab,
                DATE_FORMAT(COALESCE(j.updated_at, j.created_at), \'%Y-%m-%dT%H:%i:%s+07:00\') AS assignment_updated_at
             FROM tb_master_jabatan mj
             LEFT JOIN tb_jabatan j
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
                AND LOWER(TRIM(j.status_jab)) = \'aktif\'
             LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
             ORDER BY mj.kode_jabatan ASC, j.unit_kerja ASC, j.id_jab ASC',
            "SELECT COUNT(*)
             FROM tb_master_jabatan mj
             LEFT JOIN tb_jabatan j
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
                AND LOWER(TRIM(j.status_jab)) = 'aktif'",
            [],
            $page,
            $perPage,
            null
        );
    }

    /** @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    public static function employees(int $page, int $perPage, ?string $updatedSince): array
    {
        $where = $updatedSince === null ? '' : 'WHERE COALESCE(p.updated_at, p.created_at) >= :updated_since';
        $params = $updatedSince === null ? [] : ['updated_since' => $updatedSince];

        return self::paginate(
            'SELECT
                p.id_peg,
                p.nama,
                CAST(p.status_kepeg AS CHAR) AS status_pegawai,
                CAST(p.status_aktif AS CHAR) AS status_aktif,
                DATE_FORMAT(COALESCE(p.updated_at, p.created_at), \'%Y-%m-%dT%H:%i:%s+07:00\') AS updated_at
             FROM tb_pegawai p
             ' . $where . '
             ORDER BY p.id_peg ASC',
            'SELECT COUNT(*) FROM tb_pegawai p ' . $where,
            $params,
            $page,
            $perPage,
            $updatedSince
        );
    }

    /** @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    public static function assignments(int $page, int $perPage, ?string $updatedSince): array
    {
        $where = $updatedSince === null ? '' : 'WHERE COALESCE(j.updated_at, j.created_at) >= :updated_since';
        $params = $updatedSince === null ? [] : ['updated_since' => $updatedSince];

        return self::paginate(
            'SELECT
                CAST(j.id_jab AS CHAR) AS id_jab,
                j.id_peg,
                CAST(j.kode_jabatan AS CHAR) AS kode_jabatan,
                CAST(mj.parent_kode_jabatan AS CHAR) AS parent_kode_jabatan,
                mj.nama_jabatan,
                mj.level,
                mj.group_jabatan,
                j.unit_kerja AS kode_unit,
                k.nama_kantor AS unit_kerja,
                j.tmt_jabatan,
                j.sampai_tgl,
                j.status_jab,
                DATE_FORMAT(COALESCE(j.updated_at, j.created_at), \'%Y-%m-%dT%H:%i:%s+07:00\') AS updated_at
             FROM tb_jabatan j
             LEFT JOIN tb_master_jabatan mj
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
             LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
             ' . $where . '
             ORDER BY j.id_jab ASC',
            'SELECT COUNT(*) FROM tb_jabatan j ' . $where,
            $params,
            $page,
            $perPage,
            $updatedSince
        );
    }

    /** @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    public static function units(int $page, int $perPage, ?string $updatedSince): array
    {
        $where = $updatedSince === null ? '' : 'WHERE COALESCE(k.date_update, k.date_reg) >= DATE(:updated_since)';
        $params = $updatedSince === null ? [] : ['updated_since' => $updatedSince];

        return self::paginate(
            "SELECT
                k.kode_kantor_detail AS kode_unit,
                k.nama_kantor AS nama_unit,
                CASE k.level
                    WHEN 'KP' THEN 'PUSAT'
                    WHEN 'KW' THEN 'KANWIL'
                    WHEN 'KC' THEN 'KC'
                    WHEN 'KK' THEN 'KK'
                    ELSE k.level
                END AS jenis_unit,
                k.kode_cabang,
                DATE_FORMAT(COALESCE(k.date_update, k.date_reg), '%Y-%m-%dT00:00:00+07:00') AS updated_at
             FROM tb_kantor k
             " . $where . '
             ORDER BY k.kode_kantor_detail ASC',
            'SELECT COUNT(*) FROM tb_kantor k ' . $where,
            $params,
            $page,
            $perPage,
            $updatedSince
        );
    }

    /**
     * @param array<string, string> $params
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private static function paginate(
        string $selectSql,
        string $countSql,
        array $params,
        int $page,
        int $perPage,
        ?string $updatedSince
    ): array {
        global $pdo;

        $count = $pdo->prepare($countSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $statement = $pdo->prepare($selectSql . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);

        return [
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
                'updated_since' => $updatedSince,
            ],
        ];
    }
}
