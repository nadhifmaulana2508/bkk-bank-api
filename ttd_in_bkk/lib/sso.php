<?php
declare(strict_types=1);

function sso_request(string $method, string $path, ?array $body = null, ?string $token = null): array {
    $url = rtrim((string)envv('SSO_BASE_URL'), '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $error) {
        throw new RuntimeException('SSO tidak dapat dihubungi.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons SSO tidak valid.');
    }
    return ['status'=>$status, 'body'=>$data, 'ok'=>$status >= 200 && $status < 300];
}

function sso_login(string $idPeg, string $password): array {
    return sso_request('POST', '/api/auth/login', [
        'id_peg' => $idPeg,
        'password' => $password,
        'app' => (string)envv('SSO_APP', 'simpeg'),
    ]);
}

function sso_whoami(string $token): array {
    return sso_request('GET', '/api/auth/whoami', null, $token);
}

function sso_extract_token(array $body): ?string {
    $candidates = [
        $body['token'] ?? null,
        $body['access_token'] ?? null,
        $body['data']['token'] ?? null,
        $body['data']['access_token'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function sso_find_profile_candidate(mixed $value): ?array {
    if (!is_array($value)) {
        return null;
    }

    foreach (['employee_id', 'id_peg', 'idPeg', 'nip', 'employeeId', 'username'] as $key) {
        if (isset($value[$key]) && is_scalar($value[$key]) && trim((string)$value[$key]) !== '') {
            return $value;
        }
    }

    foreach ($value as $child) {
        $candidate = sso_find_profile_candidate($child);
        if ($candidate !== null) {
            return $candidate;
        }
    }

    return null;
}

function sso_extract_profile(array $body): ?array {
    $raw = sso_find_profile_candidate($body);
    if ($raw === null) {
        return null;
    }

    $pick = static function (array $source, array $keys): ?string {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key]) && trim((string)$source[$key]) !== '') {
                return trim((string)$source[$key]);
            }
        }
        return null;
    };

    $employeeId = $pick($raw, ['employee_id', 'id_peg', 'idPeg', 'employeeId', 'nip', 'username', 'id']);
    if ($employeeId === null) {
        return null;
    }

    return array_merge($raw, [
        'employee_id' => $employeeId,
        'full_name' => $pick($raw, ['full_name', 'name', 'nama', 'nama_pegawai', 'employee_name']) ?? $employeeId,
        'job_position' => $pick($raw, ['job_position', 'position', 'jabatan', 'nama_jabatan']) ?? '-',
        'unit_kerja' => $pick($raw, ['unit_kerja', 'unit', 'unit_name', 'department', 'nama_unit']) ?? '-',
        'branch_name' => $pick($raw, ['branch_name', 'branch', 'cabang', 'nama_cabang', 'office_name', 'office', 'kantor', 'nama_kantor', 'lokasi_kantor']) ?? '-',
    ]);
}
