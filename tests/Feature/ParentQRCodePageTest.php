<?php

namespace Tests\Feature;

use Tests\TestCase;

class ParentQRCodePageTest extends TestCase
{
    public function test_parent_qr_code_page_displays_blue_hero(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify hero section elements
        $response->assertSee('class="parent-qr-hero"', false);
        $response->assertSee('My QR Code');
        $response->assertSee('Present this QR code to the guard or staff at the school gate.');
        // Verify back arrow functionality
        $response->assertSee('parent-qr-back-arrow', false);
    }

    public function test_parent_qr_code_page_displays_student_info_card(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify student info card
        $response->assertSee('class="parent-qr-info-card"', false);
        // Verify student section
        $response->assertSee('class="parent-qr-student-section"', false);
        $response->assertSee('Juan Dela Cruz');
        $response->assertSee('Grade 5 - Mabini');
        $response->assertSee('Student ID: STU-001');
        // Verify authorized parent section
        $response->assertSee('class="parent-qr-parent-section"', false);
        $response->assertSee('Authorized Parent');
        $response->assertSee('Maria Santos');
        $response->assertSee('Mother');
        $response->assertSee('+63 912 345 6789');
    }

    public function test_parent_qr_code_page_displays_qr_section(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify QR code section
        $response->assertSee('class="parent-qr-code-section"', false);
        $response->assertSee('Your QR Code');
        $response->assertSee('Use this code for verification at the school entrance.');
        // Verify QR canvas is present
        $response->assertSee('parentQrCodeCanvas', false);
    }

    public function test_parent_qr_code_page_displays_security_notice(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify security notice
        $response->assertSee('class="parent-qr-security-notice"', false);
        $response->assertSee('Security Notice');
        $response->assertSee('Sharing, screenshot, or downloading this QR code is not allowed.');
    }

    public function test_parent_qr_code_page_displays_important_info(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify important box
        $response->assertSee('class="parent-qr-important-box"', false);
        $response->assertSee('Important');
        $response->assertSee('Do not share your QR code with other people.');
        $response->assertSee('Use this code only for your child.');
        $response->assertSee('If you have trouble displaying this code, ask the guard or staff for assistance.');
    }

    public function test_parent_qr_code_page_maintains_navigation(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                    'student_name' => 'Juan Dela Cruz',
                    'student_class' => 'Grade 5 - Mabini',
                    'student_id' => 'STU-001',
                    'student_qr_code' => 'QR123456789',
                    'relationship' => 'Mother',
                    'mobile_number' => '+63 912 345 6789',
                ],
            ],
        ])->get('/parent/qr-code');

        $response->assertOk();
        // Verify bottom navigation
        $response->assertSee('parent-portal-bottom-nav', false);
        $response->assertSee('href="/parent/dashboard"', false);
        $response->assertSee('href="/parent/qr-code"', false);
        $response->assertSee('href="/parent/pickup-history"', false);
        $response->assertSee('href="/parent/profile"', false);
        // Verify My Student nav item is NOT present
        $response->assertDontSee('href="/parent/student"', false);
    }
}
