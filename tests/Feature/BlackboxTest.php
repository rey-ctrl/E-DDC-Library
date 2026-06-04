<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlackboxTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard admin user inside the in-memory SQLite database
        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('pnjtekinfor'),
        ]);
    }

    # =========================================================================
    # TABEL 1: PENGUJIAN FITUR AUTENTIKASI (LOGIN & LOGOUT)
    # =========================================================================

    /**
     * TC-01 & TC-02: Test login validation with empty and invalid fields.
     */
    public function test_login_validation_errors(): void
    {
        // TC-01: Empty fields
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);

        // TC-02: Invalid email format
        $response = $this->post('/login', [
            'email' => 'admin_salah',
            'password' => 'pnjtekinfor',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * TC-03: Test login with incorrect credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@gmail.com',
            'password' => 'salahpassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    /**
     * TC-04: Test login with valid credentials.
     */
    public function test_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@gmail.com',
            'password' => 'pnjtekinfor',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);
    }

    /**
     * TC-05: Test logout functionality.
     */
    public function test_logout_functionality(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    # =========================================================================
    # TABEL 2: OTORISASI & HAK AKSES HALAMAN
    # =========================================================================

    /**
     * TC-06: Guest cannot access page to add book and is redirected to login.
     */
    public function test_tambah_buku_requires_authentication(): void
    {
        $response = $this->get('/buku/tambah');

        $response->assertRedirect('/login');
    }

    /**
     * TC-07: Authenticated admin can access page to add book.
     */
    public function test_tambah_buku_renders_for_authenticated_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/buku/tambah');

        $response->assertStatus(200);
        $response->assertViewIs('tambah_buku');
    }

    # =========================================================================
    # TABEL 3: PENGUJIAN FITUR PENCARIAN & KLASIFIKASI BUKU
    # =========================================================================

    /**
     * TC-08: Search/Classification with empty keyword and filters.
     */
    public function test_search_with_empty_keyword_and_filters(): void
    {
        // Mock API Flask search endpoint response
        Http::fake([
            '127.0.0.1:5000/api/buku/search*' => Http::response([
                'data' => [
                    [
                        'biblio_id' => 1,
                        'Book_Title' => 'Dasar Pemrograman Web',
                        'Author' => 'Budi',
                        'Year_Published' => '2022',
                        'Pages' => '200 hlm.',
                        'Book_Code' => '005.1',
                        'Call_Number' => '005.1 BUD d',
                        'Publisher' => '-',
                        'Image' => null,
                        'Description' => 'Belajar HTML, CSS, JS',
                        'Notes' => '',
                        'has_notes' => false,
                        'DDC_Bersih' => 5,
                        'Multilabel' => [
                            [
                                'label' => 'Teknik Informatika & Komputer',
                                'probabilitas' => 98.4,
                                'metode' => 'text_classifier'
                            ]
                        ]
                    ]
                ],
                'pagination' => [
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 24,
                    'total_pages' => 1
                ]
            ], 200)
        ]);

        $response = $this->get('/klasifikasi');

        $response->assertStatus(200);
        $response->assertViewIs('hasil_klasifikasi');
        $response->assertSee('Dasar Pemrograman Web');
        $response->assertSee('Teknik Informatika &amp; Komputer', false);
    }

    /**
     * TC-09, TC-10, TC-11 & TC-12: Search with keyword and specific filters.
     */
    public function test_search_with_keyword_and_filters(): void
    {
        Http::fake([
            '127.0.0.1:5000/api/buku/search*' => Http::response([
                'data' => [
                    [
                        'biblio_id' => 2,
                        'Book_Title' => 'Statistika untuk Rekayasa',
                        'Author' => 'Siti',
                        'Year_Published' => '2023',
                        'Pages' => '150 hlm.',
                        'Book_Code' => '519.5',
                        'Call_Number' => '519.5 SIT s',
                        'Publisher' => '-',
                        'Image' => null,
                        'Description' => 'Analisis data statistik',
                        'Notes' => '',
                        'has_notes' => false,
                        'DDC_Bersih' => 519,
                        'Multilabel' => [
                            [
                                'label' => 'Matematika',
                                'probabilitas' => 90.0,
                                'metode' => 'text_classifier'
                            ]
                        ]
                    ]
                ],
                'pagination' => [
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 24,
                    'total_pages' => 1
                ]
            ], 200)
        ]);

        $response = $this->post('/klasifikasi', [
            'keyword' => 'statistika',
            'filters' => ['Matematika'],
            'filter_mode' => 'or',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('hasil_klasifikasi');
        $response->assertSee('Statistika untuk Rekayasa');
        $response->assertSee('Matematika');
    }

    /**
     * TC-13: Fetch detailed book information via JSON/AJAX.
     */
    public function test_get_book_detail_json(): void
    {
        Http::fake([
            '127.0.0.1:5000/api/buku/detail/123' => Http::response([
                'biblio_id' => 123,
                'Book_Title' => 'Pemrograman Python',
                'Author' => 'Andi',
                'Edition' => 'Edisi Pertama',
                'ISBN' => '123-456-789',
                'Year_Published' => '2021',
                'Pages' => '300 hlm.',
                'Series' => '-',
                'Book_Code' => '005.13',
                'Call_Number' => '005.13 AND p',
                'Notes' => 'Buku teks',
                'has_notes' => true,
                'Publisher' => '-',
                'Place' => '-',
                'Image' => null,
                'Description' => 'Pengenalan Python',
                'DDC_Bersih' => 5,
                'Multilabel' => [
                    [
                        'label' => 'Teknik Informatika & Komputer',
                        'probabilitas' => 95.0,
                        'metode' => 'text_classifier'
                    ]
                ]
            ], 200)
        ]);

        $response = $this->get('/buku/123');

        $response->assertStatus(200);
        $response->assertJson([
            'biblio_id' => 123,
            'Book_Title' => 'Pemrograman Python',
            'Author' => 'Andi',
            'ISBN' => '123-456-789',
        ]);
    }

    # =========================================================================
    # TABEL 4: PENGUJIAN FITUR TAMBAH BUKU (POST)
    # =========================================================================

    /**
     * TC-14: Test validation errors when adding book with empty inputs.
     */
    public function test_tambah_buku_validation_fails(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->from('/buku/tambah')
            ->post('/buku/tambah', [
                'title' => '',
                'sor' => '',
                'classification' => '',
            ]);

        $response->assertRedirect('/buku/tambah');
        $response->assertSessionHasErrors(['title', 'sor', 'classification']);
    }

    /**
     * TC-15: Test adding book successfully with valid input.
     */
    public function test_tambah_buku_success(): void
    {
        Http::fake([
            '127.0.0.1:5000/api/buku/store' => Http::response([
                'success' => true,
                'message' => 'Buku \'Belajar Laravel\' berhasil ditambahkan.',
                'biblio_id' => 999
            ], 201)
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post('/buku/tambah', [
                'title' => 'Belajar Laravel',
                'sor' => 'Taylor Otwell',
                'classification' => '005.3',
                'publish_year' => '2024',
                'isbn_issn' => '978-602-123',
                'call_number' => '005.3 TAY b',
                'edition' => 'Edisi Pertama',
                'collation' => '250 hal',
                'series_title' => 'Framework Series',
                'spec_detail_info' => 'Detail Laravel',
                'notes' => 'Catatan penting',
            ]);

        $response->assertRedirect('/buku/tambah');
        $response->assertSessionHas('success', 'Buku \'Belajar Laravel\' berhasil ditambahkan.');
    }

    /**
     * TC-16: Test adding book when Flask API is offline/unavailable.
     */
    public function test_tambah_buku_api_offline(): void
    {
        // Mock API request to throw a ConnectionException
        Http::fake([
            '127.0.0.1:5000/api/buku/store' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            }
        ]);

        $response = $this->actingAs($this->adminUser)
            ->from('/buku/tambah')
            ->post('/buku/tambah', [
                'title' => 'Belajar Laravel',
                'sor' => 'Taylor Otwell',
                'classification' => '005.3',
            ]);

        $response->assertRedirect('/buku/tambah');
        $response->assertSessionHas('error', 'Server AI tidak aktif. Pastikan python api.py sudah berjalan.');
    }

    # =========================================================================
    # TABEL 5: KETAHANAN SISTEM (API OFFLINE) PADA FITUR PENCARIAN
    # =========================================================================

    /**
     * TC-17: Test searching books when external Flask API is offline/unavailable.
     */
    public function test_search_api_offline_displays_warning(): void
    {
        Http::fake([
            '127.0.0.1:5000/api/buku/search*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            }
        ]);

        $response = $this->get('/klasifikasi');

        $response->assertStatus(200);
        $response->assertViewIs('hasil_klasifikasi');
        $response->assertViewHas('apiError', true);
        // The view hasil_klasifikasi should show some warning text about connection
        $response->assertSee('Server AI Tidak Aktif', false);
    }
}
