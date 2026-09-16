<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Login_User;
use App\Models\Role;
use App\Models\User_Info;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
        $numbers = collect(range(1, 1001))->map(fn ($number) => "M-{$number}/26")->implode("\n");

        $this->postJson(route('registration-search.results'), ['reg_nos' => $numbers])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reg_nos');
    }

    public function test_xlsx_download_contains_a_real_excel_workbook_with_the_searched_participant(): void
    {
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);

        $response = $this->post(route('registration-search.xlsx'), ['reg_nos' => 'M-1588/16']);

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame('PK', substr(file_get_contents($response->baseResponse->getFile()->getPathname()), 0, 2));
    }

    public function test_authenticated_user_can_open_the_search_page(): void
    {
        $role = Role::create(['name' => 'Super Admin']);
        $user = Login_User::create([
            'user_id' => 'SA000000001',
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
            'role' => $role->id,
            'password' => 'secret',
        ]);

        $this->actingAs($user)
            ->get(route('registration-search.index'))
            ->assertOk()
            ->assertSee('Search by Reg No')
            ->assertSee('Choose download format')
            ->assertSee('Download in PDF')
            ->assertSee('Download in XLSX');
    }

    public function test_pdf_download_returns_a_pdf_with_the_searched_participant(): void
    {
        $branch = Branch::create(['branch' => 'Banani Branch']);
        $this->participant($branch->id, 'M-1588/16', 'Muhammad Rezaul Haque', 2239);

        $response = $this->post(route('registration-search.pdf'), ['reg_nos' => 'M-1588/16']);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF', substr($response->getContent(), 0, 4));
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
