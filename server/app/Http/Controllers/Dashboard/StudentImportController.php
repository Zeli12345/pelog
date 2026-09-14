<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function form(): View
    {
        return view('dashboard.students.import', ['result' => null]);
    }

    public function import(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return back()->withErrors([
                'file' => 'Format berkas harus CSV atau Excel (.csv, .xlsx, .xls).',
            ]);
        }

        $import = new StudentsImport;
        Excel::import($import, $file);

        Audit::log(
            action: 'students_imported',
            metadata: [
                'created' => $import->created,
                'updated' => $import->updated,
                'errors' => count($import->errors),
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return view('dashboard.students.import', [
            'result' => [
                'created' => $import->created,
                'updated' => $import->updated,
                'skipped' => $import->skipped,
                'errors' => $import->errors,
            ],
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['NISN', 'NAMA', 'KELAS']);
            fputcsv($output, ['0051234567', 'Budi Pratama', 'X RPL 1']);
            fputcsv($output, ['0051234568', 'Ani Wijaya', 'X TKJ 2']);

            fclose($output);
        }, 'template-import-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
