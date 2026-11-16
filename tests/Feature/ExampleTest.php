<?php

test('halaman utama menampilkan beranda publik', function () {
    $response = $this->get('/');

    $response->assertOk();
});
