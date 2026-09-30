<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Imports\StaffImport;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffImportController extends Controller
{
    public function form(): View
    {
        return view('dashboard.staff.import', ['result' => null]);
    }

    public function import(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ], [
            'file.mimetypes' => 'Isi berkas tidak dikenali sebagai CSV/Excel. Pastikan berkas asli, bukan hasil ubah nama.',
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return back()->withErrors([
                'file' => 'Format berkas harus CSV atau Excel (.csv, .xlsx, .xls).',
            ]);
        }

        $import = new StaffImport;

        try {
            Excel::import($import, $file);
        } catch (\Throwable) {
            return back()->withErrors([
                'file' => 'Berkas gagal diproses. Pastikan format CSV/Excel sesuai template dan tidak rusak.',
            ]);
        }

        Audit::log(
            action: 'staff_imported',
            metadata: [
                'created' => $import->created,
                'updated' => $import->updated,
                'errors' => count($import->errors),
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return view('dashboard.staff.import', [
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

            fputcsv($output, ['NIP', 'NAMA', 'PERAN']);
            fputcsv($output, ['198501152010011002', 'I Komang Purwata, S.Pd.', 'teacher']);
            fputcsv($output, ['199002202015012003', 'Ni Made Ariani, S.Kom.', 'staff']);

            fclose($output);
        }, 'template-import-guru.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
