<?php

namespace Tests\Unit;

use App\Support\StoredFileName;
use PHPUnit\Framework\TestCase;

class StoredFileNameTest extends TestCase
{
    public function test_the_first_file_is_version_one()
    {
        $this->assertSame('logo-1.png', StoredFileName::next('logo', null, 'png'));
        $this->assertSame('logo-1.png', StoredFileName::next('logo', '', 'png'));
    }

    public function test_each_replacement_bumps_the_version()
    {
        $this->assertSame(
            'logo-2.png',
            StoredFileName::next('logo', 'logos/7/logo-1.png', 'png'),
        );

        $this->assertSame(
            'logo-11.png',
            StoredFileName::next('logo', 'logos/7/logo-10.png', 'png'),
        );
    }

    public function test_the_extension_follows_the_new_file_not_the_old_one()
    {
        $this->assertSame(
            'logo-2.webp',
            StoredFileName::next('logo', 'logos/7/logo-1.png', 'webp'),
        );
    }

    public function test_a_path_from_an_older_scheme_restarts_at_one()
    {
        $this->assertSame(
            'logo-1.png',
            StoredFileName::next('logo', 'logos/7/EAUyaOEtdfFnxVeWNV4B.png', 'png'),
        );
    }

    public function test_a_digit_belonging_to_another_prefix_is_not_read_as_a_version()
    {
        $this->assertSame(
            'logo-1.pdf',
            StoredFileName::next('logo', 'invoices/7/invoice-9.pdf', 'pdf'),
        );
    }

    public function test_prefixes_and_extensions_cannot_steer_the_path()
    {
        $this->assertSame(
            'logo-1.png',
            StoredFileName::next('../../logo', null, 'png'),
        );

        $this->assertSame(
            'logo-1.phpsuffix',
            StoredFileName::next('logo', null, '.php/suffix'),
        );
    }

    public function test_empty_segments_fall_back_rather_than_producing_a_bare_name()
    {
        $this->assertSame('file-1.bin', StoredFileName::next('', null, ''));
    }
}
