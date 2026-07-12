<?php

namespace App\Http\Controllers;

use App\Services\LangParserService;

class LangController extends Controller
{
    public function res(LangParserService $langParser)
    {
        return response()->json($langParser->getResTranslations());
    }
}
