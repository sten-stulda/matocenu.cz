<?php

namespace App\Http\Controllers;

use App\Domain\Questions\Services\HomepageData;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomepageData $data): View
    {
        return view('pages.home', $data->build(auth()->user()));
    }
}
