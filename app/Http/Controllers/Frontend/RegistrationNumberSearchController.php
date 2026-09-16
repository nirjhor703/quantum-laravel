<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User_Info;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class RegistrationNumberSearchController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->ajax() ? 'search.registration-number-content' : 'search.registration-number';

        return view($view);
    }

    public function results(Request $request): JsonResponse
    {
        [$registrationNumbers, $participants, $missing] = $this->search($request);

        return response()->json([
            'count' => $participants->count(),
            'requested_count' => count($registrationNumbers),
            'participants' => $participants->values(),
            'missing' => $missing,
        ]);
    }

    public function pdf(Request $request)
    {
        [, $participants] = $this->search($request);

        return Pdf::loadView('search.registration-number-pdf', compact('participants'))
            ->setPaper('a4', 'landscape')
            ->download('participants-by-registration-number.pdf');
    }

    public function xlsx(Request $request): BinaryFileResponse
    {
        [, $participants] = $this->search($request);
        $file = $this->buildXlsx($participants);

        return response()->download(
            $file,
            'participants-by-registration-number.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function search(Request $request): array
    {
        $request->validate([
            'reg_nos' => ['required', 'string'],
        ], [
            'reg_nos.required' => 'Please enter at least one registration number.',
        ]);

        $registrationNumbers = $this->parseRegistrationNumbers($request->string('reg_nos')->toString());

        if ($registrationNumbers === []) {
            throw ValidationException::withMessages([
                'reg_nos' => 'Please enter at least one registration number.',
            ]);
        }

        if (count($registrationNumbers) > 1000) {
            throw ValidationException::withMessages([
                'reg_nos' => 'A maximum of 1,000 unique registration numbers is allowed.',
            ]);
        }

        $found = User_Info::query()
            ->with('branchs:id,branch')
            ->whereIn('reg_no', $registrationNumbers)
            ->get()
            ->keyBy(fn (User_Info $participant) => mb_strtolower(trim($participant->reg_no)));

        $participants = collect($registrationNumbers)
            ->map(fn (string $regNo) => $found->get(mb_strtolower($regNo)))
            ->filter()
            ->map(function (User_Info $participant): array {
                return [
                    'id' => $participant->id,
                    'sl' => $participant->sl,
                    'image' => $participant->image,
                    'age' => $participant->age,
                    'occupation' => $participant->occupation,
                    'status' => $participant->qt_status,
                    'name' => $participant->name,
                    'branch' => $participant->branchs?->branch,
                    'reg_no' => $participant->reg_no,
                    'mobile' => $participant->phone,
                ];
            });

        $missing = collect($registrationNumbers)
            ->reject(fn (string $regNo) => $found->has(mb_strtolower($regNo)))
            ->values()
            ->all();

        return [$registrationNumbers, $participants, $missing];
    }

    private function parseRegistrationNumbers(string $input): array
    {
        $values = preg_split('/[\s,]+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = [];

        foreach ($values as $value) {
            $normalized = mb_strtolower(trim($value));
            if ($normalized !== '' && ! array_key_exists($normalized, $unique)) {
                $unique[$normalized] = trim($value);
            }
        }

        return array_values($unique);
    }

    private function buildXlsx(Collection $participants): string
    {
        $file = tempnam(sys_get_temp_dir(), 'reg-search-');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Participants" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0"/><xf fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($participants));
        $zip->close();

        return $file;
    }

    private function worksheetXml(Collection $participants): string
    {
        $headers = ['Sl', 'Image ID', 'Age', 'Occupation', 'Status', 'Name', 'Branch', 'Reg No.', 'Mobile'];
        $rows = [$headers];

        foreach ($participants->values() as $index => $participant) {
            $rows[] = [
                $index + 1,
                $participant['sl'],
                $participant['age'],
                $participant['occupation'],
                $participant['status'],
                $participant['name'],
                $participant['branch'],
                $participant['reg_no'],
                $participant['mobile'],
            ];
        }

        $xmlRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$reference}\" t=\"inlineStr\"{$style}><is><t>{$escaped}</t></is></c>";
            }
            $xmlRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="7" customWidth="1"/><col min="2" max="3" width="12" customWidth="1"/><col min="4" max="7" width="22" customWidth="1"/><col min="8" max="9" width="18" customWidth="1"/></cols><sheetData>'.$xmlRows.'</sheetData></worksheet>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}
