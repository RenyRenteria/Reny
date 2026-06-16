<?php

namespace App\Http\Controllers;

use App\Services\PublicCmsContentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(Request $request, PublicCmsContentService $cms): View
    {
        return view('welcome', $cms->music($request->user()));
    }

    public function videos(Request $request, PublicCmsContentService $cms): View
    {
        return view('videos', $cms->videos($request->user()));
    }

    public function photos(Request $request, PublicCmsContentService $cms): View
    {
        return view('photos', $cms->photos($request->user()));
    }

    public function community(Request $request, PublicCmsContentService $cms): View
    {
        return view('community', $cms->community($request->user()));
    }

    public function store(Request $request, PublicCmsContentService $cms): View
    {
        return view('store', $cms->store($request->user()));
    }
}
