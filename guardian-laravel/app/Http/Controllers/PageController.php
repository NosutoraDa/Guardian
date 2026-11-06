<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Services\VirusTotalUrlChecker;
use App\Services\VirusTotalFileChecker;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
    }

    public function links(): View
    {
        return view('pages.links', [
            'examples' => config('guardian.examples'),
        ]);
    }

    public function linkReport(string $urlId, VirusTotalUrlChecker $checker): View
    {
        return view('pages.link-report', [
            'urlId' => $urlId,
            'report' => $checker->getUrlReport($urlId),
        ]);
    }

    public function files(): View
    {
        return view('pages.files');
    }

    public function fileReport(string $fileHash, VirusTotalFileChecker $checker): View
    {
        return view('pages.file-report', [
            'fileHash' => $fileHash,
            'report' => $checker->getFileReport($fileHash),
        ]);
    }

    public function tanya(): View
    {
        return view('pages.tanya');
    }
}
