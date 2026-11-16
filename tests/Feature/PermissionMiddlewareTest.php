<?php

namespace Tests\Feature;

use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    public function test_protected_ppdb_verification_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/ppdb/999999/verifikasi', [
            'status_pendaftaran' => 'diterima',
        ])->assertUnauthorized();
    }
}
