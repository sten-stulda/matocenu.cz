<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\PersonalDataExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivacyExportController extends Controller
{
    public function __invoke(Request $request, PersonalDataExporter $exporter): Response
    {
        $data = $exporter->export($request->user());
        $name = 'matocenu-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $name,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
