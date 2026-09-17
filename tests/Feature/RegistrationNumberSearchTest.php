<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Login_User;
use App\Models\Role;
use App\Models\User_Info;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Tests\TestCase;

class RegistrationNumberSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--database' => 'mysql']);
    }

    public function test_it_searches_newline_comma_and_space_separated_registration_numbers_in_input_order(): void
    {
        $this->actingAs($this->loginUser());
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);
        $this->participant($branch->id, 'F-73/437', 'Mrs. Shimul Akhter', 1167);

        $response = $this->postJson(route('registration-search.results'), [
            'reg_nos' => "F-73/437, M-1588/16\nF-73/437 UNKNOWN/1",
        ]);

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('participants.0.reg_no', 'F-73/437')
            ->assertJsonPath('participants.1.reg_no', 'M-1588/16')
            ->assertJsonPath('missing.0', 'UNKNOWN/1')
            ->assertJsonCount(2, 'participants');
    }

    public function test_it_rejects_more_than_one_thousand_unique_registration_numbers(): void
    {
        $this->actingAs($this->loginUser());
        $numbers = collect(range(1, 1001))->map(fn ($number) => "M-{$number}/26")->implode("\n");

        $this->postJson(route('registration-search.results'), ['reg_nos' => $numbers])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reg_nos');
    }

    public function test_xlsx_download_contains_a_real_excel_workbook_with_the_searched_participant(): void
    {
        $this->actingAs($this->loginUser());
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);

        $response = $this->post(route('registration-search.xlsx'), ['reg_nos' => 'M-1588/16']);

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame('PK', substr(file_get_contents($response->baseResponse->getFile()->getPathname()), 0, 2));
    }

    public function test_xlsx_download_embeds_the_participant_photo(): void
    {
        $this->actingAs($this->loginUser());
        Storage::fake('public');
        Storage::disk('public')->put('qt_img/2239.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $branch = Branch::create(['branch' => 'Banani Branch']);
        $participant = $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);
        $participant->update(['image' => 'qt_img/2239.png']);

        $response = $this->post(route('registration-search.xlsx'), ['reg_nos' => 'M-1588/16']);
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());

        $this->assertNotFalse($zip->locateName('xl/media/image1.png'));
        $this->assertStringContainsString('xdr:oneCellAnchor', $zip->getFromName('xl/drawings/drawing1.xml'));
        $this->assertStringContainsString('drawing r:id="rId1"', $zip->getFromName('xl/worksheets/sheet1.xml'));

        $zip->close();
    }

    public function test_xlsx_uses_a_photo_available_from_the_public_storage_path(): void
    {
        $this->actingAs($this->loginUser());
        Storage::fake('public');
        $photo = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAQAAABFaP0WAAAADElEQVR42mNk+M8AAAICAQB7CY+oAAAAAElFTkSuQmCC'
        );
        $publicPhoto = public_path('storage/qt_img/xlsx-public-only-test.png');
        if (! is_dir(dirname($publicPhoto))) {
            mkdir(dirname($publicPhoto), 0777, true);
        }
        file_put_contents($publicPhoto, $photo);

        try {
            $branch = Branch::create(['branch' => 'Banani Branch']);
            $participant = $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);
            $participant->update(['image' => 'qt_img/xlsx-public-only-test.png']);

            $response = $this->post(route('registration-search.xlsx'), ['reg_nos' => 'M-1588/16']);
            $zip = new ZipArchive;
            $zip->open($response->baseResponse->getFile()->getPathname());

            $this->assertSame($photo, $zip->getFromName('xl/media/image1.png'));
            $zip->close();
        } finally {
            @unlink($publicPhoto);
        }
    }

    public function test_xlsx_places_the_participant_number_above_the_photo(): void
    {
        $this->actingAs($this->loginUser());
        Storage::fake('public');
        Storage::disk('public')->put('qt_img/2239.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $participant = $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);
        $participant->update(['image' => 'qt_img/2239.png']);

        $response = $this->post(route('registration-search.xlsx'), ['reg_nos' => 'M-1588/16']);
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $drawing = $zip->getFromName('xl/drawings/drawing1.xml');
        $this->assertStringContainsString('<c r="B2" t="inlineStr" s="2"><is><t>2239</t></is></c>', $sheet);
        $this->assertStringContainsString('<xdr:rowOff>238125</xdr:rowOff>', $drawing);
        $this->assertStringContainsString('<xdr:ext cx="666750" cy="666750"/>', $drawing);
        $this->assertStringContainsString('<xdr:colOff>223838</xdr:colOff>', $drawing);

        $zip->close();
    }

    public function test_authenticated_user_can_open_the_search_page(): void
    {
        $this->actingAs($this->loginUser())
            ->get(route('registration-search.index'))
            ->assertOk()
            ->assertSee('Search by Reg No')
            ->assertSee('Choose download format')
            ->assertSee('Download in PDF')
            ->assertSee('Download in XLSX');
    }

    public function test_pdf_download_returns_a_pdf_with_the_searched_participant(): void
    {
        $this->actingAs($this->loginUser());
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);

        $response = $this->post(route('registration-search.pdf'), ['reg_nos' => 'M-1588/16']);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF', substr($response->getContent(), 0, 4));
    }

    public function test_guest_is_redirected_to_login_from_the_search_page(): void
    {
        $this->get(route('registration-search.index'))
            ->assertRedirect(route('login'));
    }

    private function loginUser(): Login_User
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        return Login_User::create([
            'user_id' => 'SA'.str_pad((string) Login_User::count(), 9, '0', STR_PAD_LEFT),
            'name' => 'Test Admin',
            'email' => 'admin'.Login_User::count().'@example.test',
            'role' => $role->id,
            'password' => 'secret',
        ]);
    }

    private function participant(int $branchId, string $regNo, string $name, int $sl): User_Info
    {
        return User_Info::create([
            'sl' => $sl,
            'reg_no' => $regNo,
            'name' => $name,
            'phone' => '+8801819134325',
            'gender' => 'Male',
            'age' => 54,
            'occupation' => 'Service',
            'qt_status' => 'Pro-master',
            'branch' => $branchId,
            'image' => null,
        ]);
    }
}
