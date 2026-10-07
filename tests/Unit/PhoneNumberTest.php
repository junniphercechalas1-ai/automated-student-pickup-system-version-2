<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_local_and_international_philippine_formats_are_normalized(): void
    {
        $this->assertSame('+639171234567', PhoneNumber::normalize('0917 123 4567'));
        $this->assertSame('+639171234567', PhoneNumber::normalize('639171234567'));
        $this->assertSame('+639171234567', PhoneNumber::normalize('+63 917 123 4567'));
        $this->assertSame('+639171234567', PhoneNumber::normalize('9171234567'));
    }

    public function test_empty_and_non_philippine_numbers_are_not_corrupted(): void
    {
        $this->assertSame('', PhoneNumber::normalize(null));
        $this->assertSame('+14155552671', PhoneNumber::normalize('+1 (415) 555-2671'));
    }
}