

SELECT
    kh.tanggal AS created,
    k.kode_kantor AS kode_cabang,
    k.no_rekening,
    n.nama_nasabah,
    n.gelar_h,
    n.gelar_pend,

    k.tgl_realisasi,
    k.jml_angsuran,
    k.tgl_jatuh_tempo,
    k.jml_pinjaman,
    k.type_kredit,
    k.suku_bunga_per_tahun,

    kh.baki_debet,
    kh.kolektibilitas,

    a.agunan_id,

    CASE
        WHEN a.agunan_id IS NULL THEN '8'
        ELSE a.jenis_agunan_detail
    END AS jenis_agunan,

    CASE
        WHEN a.agunan_id IS NULL THEN 'z'
        ELSE a.ikatan_agunan_detail
    END AS ikatan_agunan,

    COALESCE(a.nilai_agunan_detail, 0) AS nilai_agunan,
    COALESCE(a.kend_tahun_pembuatan, '') AS tahun_kendaraan,

    CASE
        WHEN a.agunan_id IS NULL THEN 'TANPA AGUNAN'

        WHEN a.jenis_agunan_detail = '6' THEN COALESCE(
            NULLIF(
                TRIM(CONCAT(
                    IF(
                        TRIM(COALESCE(a.tanah_no_shm, '')) NOT IN ('', '0'),
                        CONCAT('No SHM: ', a.tanah_no_shm, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.tanah_no_shgb, '')) <> '',
                        CONCAT('No SHGB: ', a.tanah_no_shgb, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.tanah_luas_tanah, '')) NOT IN ('', '0'),
                        CONCAT(
                            'Luas tanah/bangunan: ',
                            a.tanah_luas_tanah,
                            ' m2. '
                        ),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.tanah_nama_pemilik, '')) <> '',
                        CONCAT('Nama pemilik: ', a.tanah_nama_pemilik, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.tanah_desa, '')) <> '',
                        CONCAT('Desa/kelurahan: ', a.tanah_desa, '. '),
                        ''
                    )
                )),
                ''
            ),
            NULLIF(TRIM(a.deskripsi_ringkas), ''),
            'TANPA KETERANGAN'
        )

        WHEN a.jenis_agunan_detail = '5' THEN COALESCE(
            NULLIF(
                TRIM(CONCAT(
                    IF(
                        TRIM(COALESCE(a.kend_jenis_kendaraan, '')) <> '',
                        CONCAT('Jenis kendaraan: ', a.kend_jenis_kendaraan, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_merk, '')) <> '',
                        CONCAT('Merk: ', a.kend_merk, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_tahun_pembuatan, '')) <> '',
                        CONCAT('Tahun: ', a.kend_tahun_pembuatan, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_no_polisi, '')) <> '',
                        CONCAT('No polisi: ', a.kend_no_polisi, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_no_rangka, '')) <> '',
                        CONCAT('No rangka: ', a.kend_no_rangka, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_no_mesin, '')) <> '',
                        CONCAT('No mesin: ', a.kend_no_mesin, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_no_bpkb, '')) <> '',
                        CONCAT('No BPKB: ', a.kend_no_bpkb, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.kend_atas_nama, '')) <> '',
                        CONCAT('Nama pemilik: ', a.kend_atas_nama, '. '),
                        ''
                    )
                )),
                ''
            ),
            NULLIF(TRIM(a.deskripsi_ringkas), ''),
            'TANPA KETERANGAN'
        )

        WHEN a.jenis_agunan_detail = '8' THEN COALESCE(
            NULLIF(
                TRIM(CONCAT(
                    IF(
                        TRIM(COALESCE(a.sk_no_sk_cpns, '')) <> '',
                        CONCAT('No SK: ', a.sk_no_sk_cpns, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.sk_no_sk_pengangkatan, '')) <> '',
                        CONCAT(
                            'No SK pengangkatan: ',
                            a.sk_no_sk_pengangkatan,
                            '. '
                        ),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.sk_no_sk_golongan, '')) <> '',
                        CONCAT(
                            'No SK golongan: ',
                            a.sk_no_sk_golongan,
                            '. '
                        ),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.sk_no_taspen, '')) <> '',
                        CONCAT('No Taspen: ', a.sk_no_taspen, '. '),
                        ''
                    ),
                    IF(
                        TRIM(COALESCE(a.sk_no_karpeg, '')) <> '',
                        CONCAT('No Karpeg: ', a.sk_no_karpeg, '. '),
                        ''
                    )
                )),
                ''
            ),
            NULLIF(TRIM(a.deskripsi_ringkas), ''),
            'TANPA KETERANGAN'
        )

        ELSE COALESCE(
            NULLIF(TRIM(a.deskripsi_ringkas), ''),
            'TANPA KETERANGAN'
        )
    END AS deskripsi_ringkas

FROM kredit k
JOIN nasabah n
    ON n.nasabah_id = k.nasabah_id
JOIN kredit_history kh
    ON kh.no_rekening = k.no_rekening
    AND kh.tanggal = '2026-11-03'
    AND kh.kode_kantor = '001'

LEFT JOIN (
    SELECT DISTINCT
        no_rekening,
        agunan_id
    FROM kre_agunan_relasi
) kar
    ON kar.no_rekening = k.no_rekening

LEFT JOIN kre_agunan a
    ON a.agunan_id = kar.agunan_id

WHERE kh.baki_debet >= 1

ORDER BY
    k.no_rekening,
    a.agunan_id;



-- query data master kredit yang belum realisasi tapi join ke aguanan
SELECT
    k.no_rekening,
    a.agunan_id,
    a.jenis_agunan_detail AS jenis_agunan,
    a.ikatan_agunan_detail AS ikatan_agunan,
    a.nilai_agunan_detail AS nilai_agunan,
    a.deskripsi_ringkas
FROM kredit k
LEFT JOIN (
    SELECT DISTINCT
        no_rekening,
        agunan_id
    FROM kre_agunan_relasi
) kar
    ON kar.no_rekening = k.no_rekening
LEFT JOIN kre_agunan a
    ON a.agunan_id = kar.agunan_id
WHERE k.flag_pelunasan = '0'
  AND k.status = '1'
  AND NOT EXISTS (
      SELECT 1
      FROM kretrans m
      WHERE m.no_rekening = k.no_rekening
        AND m.my_kode_trans = 100
  )
ORDER BY
    k.no_rekening,
    a.agunan_id;

// nominatif agunan ini masih filter pati aja broo
SELECT
    k.kode_kantor AS kode_cabang,
    k.no_rekening,
    k.nasabah_id,
    n.nama_nasabah,
    n.no_id AS nik_nasabah,
    n.alamat AS alamat_nasabah,
    n.no_id_pasangan AS nik_pasangan,
    k.type_kredit AS jenis_kredit,
    k.jml_pinjaman AS plafon,
    k.tgl_realisasi AS tanggal_realisasi,
    k.tgl_jatuh_tempo AS tanggal_jatuh_tempo,


    a.agunan_id AS id_agunan,
    COALESCE(kja.deskripsi_jenis_agunan, 'Belum dipetakan') AS jenis_agunan,
    -- COALESCE(kia.deskripsi_ikatan_hukum, 'Belum dipetakan') AS ikatan_agunan,
    a.nilai_agunan_detail AS nilai_agunan,
    a.deskripsi_ringkas As deskripsi_agunan,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM kretrans kt
            WHERE kt.no_rekening = k.no_rekening
              AND FLOOR(kt.my_kode_trans / 100) = 3
              AND kt.keterangan LIKE '%Pelunasan%'
        ) THEN 'LUNAS'

        WHEN NOT EXISTS (
            SELECT 1
            FROM kretrans kt
            WHERE kt.no_rekening = k.no_rekening
              AND FLOOR(kt.my_kode_trans / 100) = 1
        ) THEN 'BELUM REALISASI'

        WHEN k.status = '1' THEN 'KREDIT AKTIF'

        ELSE 'STATUS LAINNYA'
    END AS status_kredit

FROM kredit k
JOIN nasabah n
    ON n.nasabah_id = k.nasabah_id
JOIN kre_agunan_relasi kar
    ON kar.no_rekening = k.no_rekening
JOIN kre_agunan a
    ON a.agunan_id = kar.agunan_id
LEFT JOIN kre_kode_jenis_agunan kja
    ON kja.kode_jenis_agunan = a.jenis_agunan_detail
LEFT JOIN kre_kode_ikatan_hukum_agunan kia
    ON kia.kode_ikatan_hukum = a.ikatan_agunan_detail

WHERE k.kode_kantor = '003'

ORDER BY
    k.no_rekening,
    a.agunan_id;